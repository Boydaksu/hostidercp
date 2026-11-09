"""Build, verify or extract a HostiderCP release using Python's standard library."""
import argparse
import ctypes
import errno
import io
import os
import shutil
import tempfile
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import stat
import subprocess
import sys
from zipfile import BadZipFile, ZIP_STORED, ZipFile, ZipInfo

MANIFEST = "release-manifest.json"
AUTHENTICITY = "AUTHENTICITY_NOT_ATTESTED"
LIMIT = 64 * 1024 * 1024
ENTRY_LIMIT = 2048
TARGET = {"php": "8.3+ 64-bit", "mariadb": "10.11+"}
CLI = {"bin/console.php", "bin/identity.php", "bin/hosting-preflight.php"}
GENERATED = {"INSTALL.md", "config/environment.example"}
REQUIRED = CLI | GENERATED | {
    "bootstrap/app.php", "bootstrap/autoload.php", "public/index.php", "public/.htaccess",
}
RESERVED = {"CON", "PRN", "AUX", "NUL"} | {f"{p}{n}" for p in ("COM", "LPT") for n in range(1, 10)}


def require(condition, message):
    if not condition:
        raise ValueError(message)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def canonical(value):
    return (json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=True) + "\n").encode()


def safe_path(name):
    require(isinstance(name, str) and len(name) <= 240, "Invalid path length")
    parts = name.split("/")
    require(all(re.fullmatch(r"[A-Za-z0-9_.-]+", p) and p not in (".", "..")
                and not p.endswith(".") and p.split(".")[0].upper() not in RESERVED
                for p in parts), "Unsafe path")


def allowed(name):
    if name in REQUIRED:
        return True
    if re.fullmatch(r"(src|bootstrap)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.php", name):
        return True
    if re.fullmatch(r"migrations/[0-9]{3}_[a-z0-9_]+\.php", name):
        return True
    return bool(re.fullmatch(r"public/assets/[A-Za-z0-9_-]+\.(css|js)", name))


def inventory(names):
    require(REQUIRED <= names, "Required release files missing")
    versions = sorted(int(n[11:14]) for n in names if n.startswith("migrations/"))
    require(versions == list(range(1, 58)), "Expected migrations 001–057")


def strict_object(pairs):
    result = {}
    for key, value in pairs:
        require(key not in result, "Duplicate JSON key")
        result[key] = value
    return result


def validated_pin(pin):
    require(isinstance(pin, str) and re.fullmatch(r"[0-9a-fA-F]{64}", pin), "Expected external SHA-256 pin")
    return pin.lower()


def read_archive(archive):
    if isinstance(archive, bytes):
        data = archive
    else:
        with archive.open("rb") as stream:
            data = stream.read(LIMIT + 1)
    require(len(data) <= LIMIT, "Archive size exceeds limit")
    return data


def verify(archive, pinned_manifest=None, expected_source=None):
    """Inspect an immutable byte snapshot against optional external expectations."""
    if pinned_manifest is not None:
        pinned_manifest = validated_pin(pinned_manifest)
    if expected_source is not None:
        require(re.fullmatch(r"[0-9a-f]{40}", expected_source), "Invalid expected source SHA")
    with ZipFile(io.BytesIO(read_archive(archive))) as bundle:
        infos = bundle.infolist()
        require(0 < len(infos) <= ENTRY_LIMIT, "Invalid entry count")
        require(sum(i.file_size for i in infos) <= LIMIT, "Expanded size exceeds limit")
        members = {}
        seen = set()
        for entry in infos:
            name = entry.orig_filename
            safe_path(name)
            require(name == entry.filename and name.casefold() not in seen, "Duplicate or ambiguous ZIP name")
            seen.add(name.casefold())
            require(entry.create_system == 3 and entry.external_attr >> 16 == stat.S_IFREG | 0o644,
                    "Only regular 0644 files are allowed")
            require(entry.compress_type == ZIP_STORED and entry.compress_size == entry.file_size and not entry.flag_bits & 1, "Unsupported ZIP encoding")
            require(name == MANIFEST or allowed(name), "Unexpected release file")
            members[name] = entry
        require(MANIFEST in members, "Manifest missing")
        raw = bundle.read(members.pop(MANIFEST))
        if pinned_manifest is not None:
            require(digest(raw) == pinned_manifest, "Manifest SHA-256 mismatch")
        manifest = json.loads(raw, object_pairs_hook=strict_object)
        require(isinstance(manifest, dict), "Invalid manifest schema")
        format_version = manifest.get("format")
        keys = {"format", "source_commit", "target", "file_count", "files"}
        if format_version == 2:
            keys |= {"total_bytes", "provenance", "build_id"}
        require(set(manifest) == keys, "Invalid manifest schema")
        require(type(format_version) is int and format_version in (1, 2) and manifest["target"] == TARGET,
                "Unsupported release format")
        require(isinstance(manifest["source_commit"], str) and re.fullmatch(r"[0-9a-f]{40}", manifest["source_commit"]),
                "Invalid source commit")
        if expected_source is not None:
            require(manifest["source_commit"] == expected_source, "Source commit mismatch")
        files = manifest["files"]
        require(isinstance(files, dict) and type(manifest["file_count"]) is int and
                manifest["file_count"] == len(files) and set(files) == set(members), "Manifest inventory mismatch")
        require(canonical(manifest) == raw, "Manifest is not canonical")
        inventory(set(files))
        verified = {}
        for name, record in files.items():
            require(isinstance(record, dict) and set(record) == {"size", "sha256", "origin"},
                    "Invalid file record")
            origin = "release-tool" if name in GENERATED else "git"
            require(type(record["size"]) is int and record["size"] == members[name].file_size
                    and record["origin"] == origin, "File metadata mismatch")
            data = bundle.read(members[name])
            require(digest(data) == record["sha256"], "File SHA-256 mismatch")
            verified[name] = data
        if format_version == 2:
            provenance = manifest["provenance"]
            require(isinstance(provenance, dict) and set(provenance) ==
                    {"builder_sha256", "templates", "authenticity"}, "Invalid provenance")
            require(isinstance(provenance["builder_sha256"], str)
                    and re.fullmatch(r"[0-9a-f]{64}", provenance["builder_sha256"])
                    and provenance["authenticity"] == AUTHENTICITY, "Invalid tool identity")
            require(provenance["templates"] == {name: digest(verified[name]) for name in GENERATED},
                    "Template digests mismatch")
            require(type(manifest["total_bytes"]) is int
                    and manifest["total_bytes"] == sum(map(len, verified.values())), "Total size mismatch")
            require(manifest["build_id"] == build_identity(manifest), "Build identity mismatch")
        return manifest, raw, verified


def git(*args, input_data=None):
    return subprocess.run(["git", *args], input=input_data, check=True, capture_output=True).stdout


def source_files(commit):
    listing = git("ls-tree", "-rz", "-l", commit)
    selected = []
    for entry in listing.split(b"\0"):
        if not entry:
            continue
        metadata, path = entry.split(b"\t", 1)
        name = path.decode("utf-8")
        if not allowed(name) or name in GENERATED:
            continue
        mode, kind, oid, size = metadata.decode().split()
        safe_path(name)
        require(mode in ("100644", "100755") and kind == "blob", "Unsafe Git member")
        selected.append((name, oid, int(size)))
    require(len(selected) < ENTRY_LIMIT and sum(r[2] for r in selected) <= LIMIT, "Source exceeds release limits")
    blobs = git("cat-file", "--batch", input_data="".join(oid + "\n" for _, oid, _ in selected).encode())
    cursor = 0
    files = {}
    for name, oid, size in selected:
        end = blobs.index(b"\n", cursor)
        require(blobs[cursor:end] == f"{oid} blob {size}".encode(), "Unexpected Git blob header")
        start = end + 1
        data = blobs[start:start + size]
        require(len(data) == size and blobs[start + size:start + size + 1] == b"\n", "Truncated Git blob")
        files[name] = data
        cursor = start + size + 1
    return files


def build_identity(manifest):
    return digest(canonical({key: value for key, value in manifest.items() if key != "build_id"}))


def build(commit, output):
    require(re.fullmatch(r"[0-9a-f]{40}", commit), "Use an exact commit SHA")
    require(git("cat-file", "-t", commit).strip() == b"commit", "Expected commit object")
    files = source_files(commit)
    assets = Path(__file__).resolve().parent
    files["INSTALL.md"] = (assets / "INSTALL.md").read_text(encoding="utf-8").encode()
    files["config/environment.example"] = (assets / "environment.example").read_text(encoding="utf-8").encode()
    inventory(set(files))
    require(len({n.casefold() for n in files}) == len(files), "Case-colliding paths")
    manifest = {"format": 2, "source_commit": commit, "target": TARGET, "file_count": len(files),
                "total_bytes": sum(map(len, files.values())),
                "provenance": {"builder_sha256": digest(Path(__file__).read_bytes()),
                               "templates": {name: digest(files[name]) for name in GENERATED},
                               "authenticity": AUTHENTICITY},
                "files": {n: {"size": len(b), "sha256": digest(b),
                              "origin": "release-tool" if n in GENERATED else "git"} for n, b in files.items()}}
    manifest["build_id"] = build_identity(manifest)
    files[MANIFEST] = canonical(manifest)
    with ZipFile(output, "x", compression=ZIP_STORED) as archive:
        for name, data in sorted(files.items()):
            entry = ZipInfo(name, (1980, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = (stat.S_IFREG | 0o644) << 16
            archive.writestr(entry, data)
    return verify(output)


def is_reparse(info):
    return stat.S_ISLNK(info.st_mode) or bool(
        getattr(info, "st_file_attributes", 0) & stat.FILE_ATTRIBUTE_REPARSE_POINT)


def identity(info):
    require(info.st_ino != 0, "Filesystem cannot identify owned paths")
    return info.st_dev, info.st_ino


def owned_directory(path, expected):
    try:
        info = path.lstat()
        return stat.S_ISDIR(info.st_mode) and not is_reparse(info) and identity(info) == expected
    except OSError:
        return False


def finalizer():
    if os.name == "nt":
        # Windows os.rename refuses an existing destination.
        return os.rename
    if sys.platform != "linux":
        raise ValueError("Atomic no-replace extraction is unsupported on this platform")
    libc = ctypes.CDLL(None, use_errno=True)
    try:
        rename = libc.renameat2
    except AttributeError:
        raise ValueError("Atomic no-replace extraction requires renameat2") from None
    rename.argtypes = (ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_uint)
    rename.restype = ctypes.c_int

    def move(stage, target):
        at_fdcwd = -100
        rename_noreplace = 1
        if rename(at_fdcwd, os.fsencode(stage), at_fdcwd, os.fsencode(target), rename_noreplace) != 0:
            code = ctypes.get_errno()
            raise OSError(code, os.strerror(code))
    return move


def private_directories(path):
    missing = []
    while not path.exists():
        missing.append(path)
        path = path.parent
    for directory in reversed(missing):
        directory.mkdir(mode=0o700)
        directory.chmod(0o700)


def private_file(path, flags):
    return os.open(path, flags | os.O_NOFOLLOW, 0o600)


def write_member(path, data):
    if sys.platform == "linux":
        private_directories(path.parent)
        stream = open(path, "xb", opener=private_file)
    else:
        path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        stream = path.open("xb")
    with stream as handle:
        if sys.platform == "linux":
            os.fchmod(handle.fileno(), 0o600)
        require(handle.write(data) == len(data), "Short write")
        handle.flush()
        os.fsync(handle.fileno())


def check_staging(stage, files):
    checked = set()
    directories = []
    linux = sys.platform == "linux"
    if linux:
        require(stat.S_IMODE(stage.lstat().st_mode) == 0o700, "Unsafe staging root permissions")
    for root, children, names in os.walk(stage, followlinks=False):
        path = Path(root)
        directories.append(path)
        for name in children:
            info = (path / name).lstat()
            require(stat.S_ISDIR(info.st_mode) and not is_reparse(info), "Unsafe staging directory")
            if linux:
                require(stat.S_IMODE(info.st_mode) == 0o700, "Unsafe staging directory permissions")
        for name in names:
            member = path / name
            key = member.relative_to(stage).as_posix()
            info = member.lstat()
            require(stat.S_ISREG(info.st_mode) and not is_reparse(info) and info.st_nlink == 1,
                    "Unsafe staging file")
            if linux:
                require(stat.S_IMODE(info.st_mode) == 0o600, "Unsafe staging file permissions")
            require(key in files and info.st_size == len(files[key]) and member.read_bytes() == files[key],
                    "Staging readback mismatch")
            checked.add(key)
    require(checked == set(files), "Incomplete staging inventory")
    if sys.platform == "linux":
        for path in reversed(directories):
            fd = os.open(path, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
            try:
                os.fsync(fd)
            finally:
                os.close(fd)


def extract(archive, target, pin, expected_source=None):
    pin = validated_pin(pin)
    manifest, raw, payload = verify(archive, pin, expected_source)
    move = finalizer()
    safe_path(target.name)
    parent = Path(os.path.abspath(target.parent))
    require(parent.resolve(strict=True) == parent, "Extraction parent must be canonical")
    for ancestor in (parent, *parent.parents):
        require(not is_reparse(ancestor.lstat()), "Linked extraction parent")
    info = parent.lstat()
    require(stat.S_ISDIR(info.st_mode), "Extraction parent must be a directory")
    if sys.platform == "linux":
        require(info.st_uid == os.geteuid() and not info.st_mode & 0o022, "Extraction parent is not private to its owner")
    parent_id = identity(info)
    target = parent / target.name
    try:
        target.lstat()
    except FileNotFoundError:
        pass
    else:
        raise FileExistsError(errno.EEXIST, "Extraction destination exists")
    stage = Path(tempfile.mkdtemp(prefix=".hostider-incomplete-", dir=parent))
    stage_id = identity(stage.lstat())
    files = payload | {MANIFEST: raw}
    try:
        if sys.platform == "linux":
            stage.chmod(0o700)
        for name, data in sorted(files.items()):
            write_member(stage.joinpath(*PurePosixPath(name).parts), data)
        check_staging(stage, files)
        require(owned_directory(parent, parent_id) and owned_directory(stage, stage_id),
                "Extraction ownership changed")
        move(stage, target)
    finally:
        # Never remove a destination or an unproven path, including after a competing publish.
        if (owned_directory(parent, parent_id) and owned_directory(stage, stage_id)
                and stage.resolve(strict=True).parent == parent):
            shutil.rmtree(stage)
    return manifest, raw, payload


def receipt(archive, manifest, raw):
    return {"source_commit": manifest["source_commit"], "format": manifest["format"],
            "files": manifest["file_count"], "total_bytes": sum(row["size"] for row in manifest["files"].values()),
            "archive_sha256": digest(archive), "manifest_sha256": digest(raw),
            "build_id": manifest.get("build_id"), "authenticity": AUTHENTICITY}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    builder = commands.add_parser("build")
    builder.add_argument("commit")
    builder.add_argument("archive", type=Path)
    builder.add_argument("--receipt", type=Path)
    for name in ("verify", "extract"):
        command = commands.add_parser(name)
        command.add_argument("archive", type=Path)
        if name == "extract":
            command.add_argument("target", type=Path)
        command.add_argument("--manifest-sha256", required=name == "extract")
        command.add_argument("--expected-source")
    args = parser.parse_args()
    try:
        if args.command == "extract":
            validated_pin(args.manifest_sha256)
        if args.command == "build":
            build(args.commit, args.archive)
        archive = read_archive(args.archive)
        if args.command == "extract":
            manifest, raw, _ = extract(archive, args.target, args.manifest_sha256, args.expected_source)
        else:
            manifest, raw, _ = verify(archive, getattr(args, "manifest_sha256", None),
                                      getattr(args, "expected_source", None))
        result = receipt(archive, manifest, raw)
        if args.command == "extract":
            result["publication"] = "ATOMIC_VISIBLE_NOT_CRASH_DURABLE"
        if args.command == "build" and args.receipt is not None:
            with args.receipt.open("xb") as stream:
                stream.write(canonical(result))
        print(json.dumps(result, sort_keys=True))
    except (ValueError, OSError, subprocess.CalledProcessError, BadZipFile) as error:
        print(f"Release rejected ({type(error).__name__})", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
