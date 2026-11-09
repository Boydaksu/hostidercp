"""Exercise release provenance and atomic extraction on a disposable workspace."""
import errno
import importlib.util
import json
import os
from pathlib import Path
import shutil
import stat
import subprocess
import sys
import threading
from unittest.mock import patch
from zipfile import ZipFile

spec = importlib.util.spec_from_file_location("release", Path(__file__).resolve().parents[1] / "tools/release/release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


def rejects(operation, error=(ValueError, OSError, release.BadZipFile)):
    try:
        operation()
    except error as failure:
        return failure
    raise AssertionError("Unsafe operation accepted")


def clean(parent, target):
    assert not target.exists() and not target.is_symlink()
    assert not list(parent.glob(".hostider-incomplete-*"))


def test_extraction(archive, workspace, full_volume):
    manifest, raw, files = release.verify(archive)
    pin = release.digest(raw)
    target = workspace / "target"
    for candidate in (None, "", "0" * 63, "z" * 64, "0" * 64):
        rejects(lambda: release.extract(archive, target, candidate), ValueError)
        clean(workspace, target)
    print("PASS missing malformed and wrong pins never begin extraction")
    wrong_source = "0" * 40
    rejects(lambda: release.extract(archive, target, pin, wrong_source), ValueError)
    clean(workspace, target)
    print("PASS externally expected source mismatch")

    forged = workspace / "forged.zip"
    change = json.loads(raw)
    change["source_commit"] = "1" * 40
    change["build_id"] = release.build_identity(change)
    with ZipFile(archive) as source, ZipFile(forged, "x") as result:
        for entry in source.infolist():
            result.writestr(entry, release.canonical(change) if entry.filename == release.MANIFEST else source.read(entry))
    forged_manifest, forged_raw, _ = release.verify(forged)
    assert forged_manifest["source_commit"] != manifest["source_commit"]
    assert release.receipt(forged.read_bytes(), forged_manifest, forged_raw)["authenticity"] == release.AUTHENTICITY
    rejects(lambda: release.extract(forged, target, pin), ValueError)
    clean(workspace, target)
    print("PASS forged self-consistent manifest is inspection-only with external pin rejection")

    corrupt = workspace / "corrupt.zip"
    corrupt.write_bytes(archive.read_bytes()[:128])
    rejects(lambda: release.extract(corrupt, target, pin), release.BadZipFile)
    clean(workspace, target)
    print("PASS truncated archive leaves no staging or target")

    real_write = release.write_member

    def out_of_space(path, data):
        real_write(path, data)
        raise OSError(errno.ENOSPC, "synthetic disk capacity error")

    with patch.object(release, "write_member", out_of_space):
        assert rejects(lambda: release.extract(archive, target, pin), OSError).errno == errno.ENOSPC
    clean(workspace, target)
    print("PASS injected ENOSPC after partial write cleans owned stage")

    if full_volume is not None:
        assert rejects(lambda: release.extract(archive, full_volume / "target", pin), OSError).errno == errno.ENOSPC
        assert not list(full_volume.iterdir())
        print("PASS actual full tmpfs leaves no target or staging")

    def failure(stage, destination):
        raise OSError(errno.EXDEV, "synthetic finalization failure")

    with patch.object(release, "finalizer", return_value=failure):
        assert rejects(lambda: release.extract(archive, target, pin), OSError).errno == errno.EXDEV
    clean(workspace, target)
    print("PASS atomic finalization error leaves destination absent")

    original = release.finalizer()
    barrier = threading.Barrier(2, timeout=20)
    outcomes = []

    def compete(stage, destination):
        barrier.wait()
        original(stage, destination)

    def attempt():
        try:
            release.extract(archive, target, pin, manifest["source_commit"])
        except FileExistsError:
            outcomes.append("EXISTS")
        except (ValueError, OSError, threading.BrokenBarrierError):
            outcomes.append("FAILED")
        else:
            outcomes.append("PUBLISHED")

    with patch.object(release, "finalizer", return_value=compete):
        threads = [threading.Thread(target=attempt) for _ in range(2)]
        for thread in threads:
            thread.start()
        for thread in threads:
            thread.join(timeout=30)
    assert not any(t.is_alive() for t in threads)
    assert sorted(outcomes) == ["EXISTS", "PUBLISHED"]
    assert not list(workspace.glob(".hostider-incomplete-*"))
    print("PASS actual competing finalizers publish exactly one complete target")

    payload = files | {release.MANIFEST: raw}
    assert all((target / name).read_bytes() == content for name, content in payload.items())
    rejects(lambda: release.extract(archive, target, pin), FileExistsError)
    assert all((target / name).read_bytes() == content for name, content in payload.items())
    print("PASS existing release bytes preserved")

    sentinel = workspace / "untouched"
    sentinel.write_bytes(b"sentinel")
    second = workspace / "second"

    def linked(path, data):
        path.parent.mkdir(parents=True, exist_ok=True)
        os.link(sentinel, path)

    with patch.object(release, "write_member", linked):
        rejects(lambda: release.extract(archive, second, pin), ValueError)
    clean(workspace, second)
    assert sentinel.read_bytes() == b"sentinel" and sentinel.stat().st_nlink == 1
    print("PASS actual staged hardlinks rejected without modifying original")

    if sys.platform == "linux":
        alias = workspace / "linked-parent"
        alias.symlink_to(target, target_is_directory=True)
        rejects(lambda: release.extract(archive, alias / "nested", pin), ValueError)
        assert not (target / "nested").exists()
        print("PASS symlink parent rejected")


def test_tool_inputs(archive, workspace):
    original, raw, _ = release.verify(archive)
    assets = workspace / "tool-copy"
    assets.mkdir()
    source = Path(release.__file__).resolve().parent
    for name in ("release.py", "INSTALL.md", "environment.example"):
        shutil.copyfile(source / name, assets / name)
    template = assets / "environment.example"
    template.write_bytes(template.read_bytes() + b"\n# synthetic template revision\n")
    changed_archive = workspace / "changed-template.zip"
    receipt = workspace / "changed-template.receipt.json"
    subprocess.run([sys.executable, str(assets / "release.py"), "build", original["source_commit"],
                    str(changed_archive), "--receipt", str(receipt)], check=True, capture_output=True)
    changed, changed_raw, _ = release.verify(changed_archive)
    assert changed["source_commit"] == original["source_commit"]
    assert changed["provenance"]["builder_sha256"] == original["provenance"]["builder_sha256"]
    assert changed["provenance"]["templates"] != original["provenance"]["templates"]
    assert changed["build_id"] != original["build_id"]
    assert json.loads(receipt.read_bytes()) == release.receipt(changed_archive.read_bytes(), changed, changed_raw)
    rejects(lambda: release.extract(changed_archive, workspace / "untrusted", release.digest(raw)), ValueError)
    clean(workspace, workspace / "untrusted")
    print("PASS template edit changes build identity and external old pin rejects it")

    builder = assets / "release.py"
    builder.write_bytes(builder.read_bytes() + b"\n# synthetic builder revision\n")
    tool_archive = workspace / "changed-tool.zip"
    subprocess.run([sys.executable, str(builder), "build", original["source_commit"], str(tool_archive)],
                   check=True, capture_output=True)
    modified, _, _ = release.verify(tool_archive)
    assert modified["source_commit"] == changed["source_commit"]
    assert modified["provenance"]["templates"] == changed["provenance"]["templates"]
    assert modified["provenance"]["builder_sha256"] != changed["provenance"]["builder_sha256"]
    assert modified["build_id"] != changed["build_id"]
    print("PASS builder edit changes build identity with same runtime and templates")


def test_umask(archive, workspace, volume, mask):
    previous = os.umask(mask)
    try:
        test_extraction(archive, workspace, volume)
        target = workspace / "target"
        manifest, raw, _ = release.verify(archive)
        expected = {name: row["sha256"] for name, row in manifest["files"].items()}
        expected[release.MANIFEST] = release.digest(raw)
        assert stat.S_IMODE(target.stat().st_mode) == 0o700
        checked = set()
        for path in target.rglob("*"):
            mode = path.lstat().st_mode
            if stat.S_ISDIR(mode):
                assert stat.S_IMODE(mode) == 0o700
            else:
                assert stat.S_ISREG(mode) and stat.S_IMODE(mode) == 0o600
                name = path.relative_to(target).as_posix()
                assert release.digest(path.read_bytes()) == expected[name]
                checked.add(name)
        assert checked == set(expected)
        print("PASS exact 0600 files and 0700 directories with pinned hashes")
        rejected = workspace / "permission-failure"
        pin = release.digest(raw)
        for operation in ("chmod", "fchmod"):
            with patch.object(release.os, operation, side_effect=PermissionError(errno.EPERM, "synthetic chmod error")):
                rejects(lambda: release.extract(archive, rejected, pin), PermissionError)
            clean(workspace, rejected)
        print("PASS chmod and fchmod failure never publish a partial target")

        original = release.write_member

        def insecure(path, data):
            original(path, data)
            path.chmod(0o666)

        with patch.object(release, "write_member", insecure):
            failure = rejects(lambda: release.extract(archive, rejected, pin), ValueError)
            assert str(failure) == "Unsafe staging file permissions"
        clean(workspace, rejected)
        print("PASS permission readback rejects a modified staged file")
    finally:
        os.umask(previous)


if __name__ == "__main__":
    archive = Path(sys.argv[1]).resolve()
    workspace = Path(sys.argv[2]).resolve()
    workspace.mkdir(mode=0o700)
    volume = Path(sys.argv[3]) if len(sys.argv) > 3 else None
    if len(sys.argv) == 5:
        assert sys.platform == "linux"
        test_umask(archive, workspace, volume, int(sys.argv[4], 8))
    else:
        test_extraction(archive, workspace, volume)
    if volume is None:
        test_tool_inputs(archive, workspace)
