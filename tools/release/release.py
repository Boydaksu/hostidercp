"""Build, verify or extract a HostiderCP release using Python's standard library."""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import stat
import subprocess
import sys
from zipfile import BadZipFile, ZIP_STORED, ZipFile, ZipInfo

MANIFEST = "release-manifest.json"
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


def verify(archive, pinned_manifest=None):
    """Check every member against an optional externally pinned manifest digest."""
    require(archive.stat().st_size <= LIMIT, "Archive size exceeds limit")
    with ZipFile(archive) as bundle:
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
        require(isinstance(manifest, dict) and set(manifest) == {"format", "source_commit", "target", "file_count", "files"},
                "Invalid manifest schema")
        require(type(manifest["format"]) is int and manifest["format"] == 1 and manifest["target"] == TARGET,
                "Unsupported release format")
        require(isinstance(manifest["source_commit"], str) and re.fullmatch(r"[0-9a-f]{40}", manifest["source_commit"]),
                "Invalid source commit")
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


def build(commit, output):
    require(re.fullmatch(r"[0-9a-f]{40}", commit), "Use an exact commit SHA")
    require(git("cat-file", "-t", commit).strip() == b"commit", "Expected commit object")
    files = source_files(commit)
    assets = Path(__file__).resolve().parent
    files["INSTALL.md"] = (assets / "INSTALL.md").read_text(encoding="utf-8").encode()
    files["config/environment.example"] = (assets / "environment.example").read_text(encoding="utf-8").encode()
    inventory(set(files))
    require(len({n.casefold() for n in files}) == len(files), "Case-colliding paths")
    manifest = {"format": 1, "source_commit": commit, "target": TARGET, "file_count": len(files),
                "files": {n: {"size": len(b), "sha256": digest(b),
                              "origin": "release-tool" if n in GENERATED else "git"} for n, b in files.items()}}
    files[MANIFEST] = canonical(manifest)
    with ZipFile(output, "x", compression=ZIP_STORED) as archive:
        for name, data in sorted(files.items()):
            entry = ZipInfo(name, (1980, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = (stat.S_IFREG | 0o644) << 16
            archive.writestr(entry, data)
    return verify(output)


def extract(archive, target, pin):
    manifest, raw, files = verify(archive, pin)
    # Exclusive new root. Never merge with a previously extracted tree.
    target.mkdir(mode=0o700)
    for name, data in sorted((files | {MANIFEST: raw}).items()):
        path = target.joinpath(*PurePosixPath(name).parts)
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open("xb") as handle:
            handle.write(data)
    return manifest, raw, files


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    builder = commands.add_parser("build")
    builder.add_argument("commit")
    builder.add_argument("archive", type=Path)
    for name in ("verify", "extract"):
        command = commands.add_parser(name)
        command.add_argument("archive", type=Path)
        if name == "extract":
            command.add_argument("target", type=Path)
        command.add_argument("--manifest-sha256")
    args = parser.parse_args()
    try:
        if args.command == "build":
            manifest, raw, _ = build(args.commit, args.archive)
        elif args.command == "verify":
            manifest, raw, _ = verify(args.archive, args.manifest_sha256)
        else:
            manifest, raw, _ = extract(args.archive, args.target, args.manifest_sha256)
        print(json.dumps({"files": manifest["file_count"], "source_commit": manifest["source_commit"],
                          "manifest_sha256": digest(raw), "authenticity": "NOT_ATTESTED"}, sort_keys=True))
    except (ValueError, OSError, subprocess.CalledProcessError, BadZipFile) as error:
        print(f"Release rejected ({type(error).__name__})", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
