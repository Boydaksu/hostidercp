"""Release archive assertions. Output paths must be inside the caller's fresh test workspace."""
import importlib.util
from pathlib import Path
import stat
import sys
import warnings
from zipfile import ZipFile, ZipInfo

spec = importlib.util.spec_from_file_location("release", Path(__file__).resolve().parents[1] / "tools/release/release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


def rejected(operation):
    try:
        operation()
    except (ValueError, OSError, release.BadZipFile):
        return
    raise AssertionError("Unsafe archive accepted")


def entry(name, mode=stat.S_IFREG | 0o644):
    info = ZipInfo(name)
    info.create_system = 3
    info.external_attr = mode << 16
    return info


def run(commit, output):
    archive = output / "hostider.zip"
    manifest, raw, expected = release.build(commit, archive)
    release.build(commit, output / "reproducible.zip")
    assert archive.read_bytes() == (output / "reproducible.zip").read_bytes()
    print("PASS deterministic exact Git source build")
    assert manifest["source_commit"] == commit
    assert all(not p.startswith(("tests/", "docs/", "storage/", "references/")) for p in expected)
    assert ".env" not in expected and "bin/identity-demo.php" not in expected
    release.verify(archive, release.digest(raw))
    rejected(lambda: release.verify(archive, "0" * 64))
    print("PASS inventory exclusions and externally pinned manifest")
    with ZipFile(archive) as source:
        original = [(i, source.read(i)) for i in source.infolist()]
    attacks = {
        "traversal": [(entry("../outside.php"), b"x")],
        "absolute": [(entry("/outside.php"), b"x")],
        "windows-drive": [(entry("C:/outside.php"), b"x")],
        "backslash": [(entry("src\\outside.php"), b"x")],
        "duplicate": [(entry("public/index.php"), b"x")],
        "case-collision": [(entry("PUBLIC/INDEX.PHP"), b"x")],
        "unexpected-php": [(entry("public/unexpected.php"), b"x")],
        "unlisted-php": [(entry("src/Extra.php"), b"x")],
    }
    for label, extra in attacks.items():
        path = output / (label + ".zip")
        with warnings.catch_warnings(), ZipFile(path, "x") as target:
            warnings.simplefilter("ignore", UserWarning)
            for info, data in original + extra:
                target.writestr(info, data)
        rejected(lambda: release.verify(path))
        print("PASS reject " + label)
    for label in ("tamper", "missing", "symlink", "manifest-duplicate"):
        path = output / (label + ".zip")
        with ZipFile(path, "x") as target:
            for info, data in original:
                if info.filename == "public/index.php":
                    if label == "missing":
                        continue
                    if label == "tamper":
                        data = bytes([data[0] ^ 1]) + data[1:]
                    if label == "symlink":
                        info = entry(info.filename, stat.S_IFLNK | 0o777)
                if info.filename == release.MANIFEST and label == "manifest-duplicate":
                    data = b'{"format":1,' + data[1:]
                target.writestr(info, data)
        rejected(lambda: release.verify(path))
        print("PASS reject " + label)
    bad_target = output / "must-not-exist"
    rejected(lambda: release.extract(output / "traversal.zip", bad_target, None))
    assert not bad_target.exists()
    install = output / "installed"
    release.extract(archive, install, release.digest(raw))
    assert all((install / p).read_bytes() == b for p, b in expected.items())
    rejected(lambda: release.extract(archive, install, None))
    assert (install / release.MANIFEST).read_bytes() == raw
    print("PASS fresh extraction and no overwrite")


if __name__ == "__main__":
    run(sys.argv[1], Path(sys.argv[2]))
