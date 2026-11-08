"""Run pinned, local Docker package and source regressions on disposable databases."""
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
EVIDENCE = ROOT / "docs/evidence/f6-h"
IMAGES = {
    "83": "sha256:d4c4bd00590b5983b8a2b42ad2cdc60693e1e091517abb6ba1d3fca0c30c2bad",
    "84": "sha256:ecef3f23a81f103e25add00f44a77fe073d4835506af101dadc3928c5802a0ba",
}
DB_IMAGE = "sha256:de61fed4a40d3842f3ee09944ba52792156cfd9adf489b2cc670fc6ded28df8d"
DSN = "mysql:host=127.0.0.1;port=3306;dbname=hostider_f0_test;charset=utf8mb4"
SUITES = "hosting-preflight identity identity-throttle-q1 identity-security identity-http session session-concurrency integration hardening-unit"
REGRESSION = """set -eu
mkdir /workspace/project
tar -C /input --exclude=.git --exclude=storage --exclude='h-*.py' -cf - . | tar -C /workspace/project -xf -
cd /workspace/project
mkdir -p storage
verify() { php -r '$m=json_decode(file_get_contents("docs/evidence/f6-h/source-manifest.json"),true,512,JSON_THROW_ON_ERROR);foreach($m as $p=>$h){if(hash_file("sha256",$p)!==$h){exit(91);}}'; }
verify
php -r 'echo "PHP ".PHP_VERSION.PHP_EOL;'
failed=0
for s in @SUITES@; do
 echo "BEGIN $s"
 rc=0; php "tests/$s.php" || rc=$?
 echo "END $rc"
 if [ "$rc" -ne 0 ]; then failed=1; fi
done
git init -q
git add -- README.md docs
for q in check audit ci/doc-check phpstan; do
 echo "QUALITY $q"
 rc=0
 if [ "$q" = phpstan ]; then
  php /analysis/phpstan.phar analyse --no-progress --memory-limit=512M || rc=$?
 else
  php "tools/$q.php" || rc=$?
 fi
 echo "QEND $rc"
 if [ "$rc" -ne 0 ]; then failed=1; fi
done
verify
echo MANIFEST_MATCH
exit "$failed"
""".replace("@SUITES@", SUITES)
INSTALL = """set -eu
mkdir /workspace/install
cp -R /package/. /workspace/install/
cd /workspace/install
php -r 'echo "PHP ".PHP_VERSION.PHP_EOL;'
php -n -r 'try { require "bootstrap/app.php"; exit(1); } catch (RuntimeException $e) { if ($e->getMessage()!=="Required PHP extension missing") {exit(1);} echo "PASS actual missing extension guard\n"; }'
php /rehearse.php
"""


def sha(data):
    return hashlib.sha256(data).hexdigest()


def source_manifest():
    previous = json.loads((ROOT / "docs/evidence/f6-g-q2/source-manifest.json").read_bytes())
    files = {name: sha((ROOT / name).read_bytes()) for name in previous}
    if files != previous:
        raise ValueError("Protected source mismatch")
    for path in sorted((ROOT / "tools/release").iterdir()):
        if path.is_file():
            files[path.relative_to(ROOT).as_posix()] = sha(path.read_bytes())
    files["tests/release-package.py"] = sha((ROOT / "tests/release-package.py").read_bytes())
    return json.dumps(files, sort_keys=True, separators=(",", ":")).encode()


def collect(output, record):
    row = None
    for line in output.splitlines():
        if re.fullmatch(r"PHP \d+\.\d+\.\d+", line):
            record["php"] = line[4:]
        elif line.startswith("BEGIN "):
            row = {"suite": line[6:], "pass": 0, "fail": []}
            record["suites"].append(row)
        elif line.startswith("PASS "):
            if row is None:
                record["checks"].append(line[5:])
            else:
                row["pass"] += 1
        elif line.startswith("FAIL "):
            name = line[5:].split(": ", 1)[0]
            (record["fail"] if row is None else row["fail"]).append(name)
        elif row is not None and re.fullmatch(r"END \d+", line):
            row["exit"] = int(line[4:])
            row = None
        elif line.startswith("QUALITY "):
            quality = line[8:]
        elif re.fullmatch(r"QEND \d+", line):
            record["quality"][quality] = int(line[5:])
        elif line == "MANIFEST_MATCH":
            record["manifest_match"] = True


def run(version, phase, installed, phar):
    tag = "hostider-f6h-" + version + "-" + secrets.token_hex(4)
    label = "hostider.f6h=" + tag
    db, net, app = [tag + "-" + suffix for suffix in ("db", "net", "app")]
    password = secrets.token_hex(24)
    env = dict(os.environ, MARIADB_ROOT_PASSWORD=password, TEST_DB_PASSWORD=password,
               DB_PASSWORD=password, APP_KEY=base64.b64encode(secrets.token_bytes(32)).decode())
    record = {"run": tag, "phase": phase, "php_image": IMAGES[version], "mariadb_image": DB_IMAGE,
              "source_manifest_sha256": sha(source_manifest()), "suites": [], "checks": [], "fail": [], "quality": {}}

    def docker(*args):
        return subprocess.run(["docker", *args], env=env, capture_output=True, text=True, encoding="utf-8", errors="replace")

    def checked(*args):
        process = docker(*args)
        if process.returncode:
            raise RuntimeError("DOCKER_" + args[0])
        return process.stdout.strip()

    try:
        checked("network", "create", "--internal", "--label", label, net)
        checked("run", "-d", "--name", db, "--label", label, "--network", net, "--log-driver", "none",
                "--tmpfs", "/var/lib/mysql:rw,size=2g", "-e", "MARIADB_ROOT_PASSWORD",
                "-e", "MARIADB_DATABASE=hostider_f0_test", "--health-cmd", "healthcheck.sh --connect --innodb_initialized",
                "--health-interval", "2s", "--health-retries", "30", DB_IMAGE, "--plugin-load-add=disks")
        for _ in range(45):
            if checked("inspect", "--format", "{{.State.Health.Status}}", db) == "healthy":
                break
            time.sleep(1)
        else:
            raise RuntimeError("DB_STARTUP")
        common = ["run", "--rm", "--name", app, "--label", label, "--user", "10001:10001",
                  "--network", "container:" + db, "--log-driver", "none", "--tmpfs", "/workspace:rw,size=512m,mode=1777",
                  "--tmpfs", "/tmp:rw,size=256m,mode=1777", "-e", "APP_ENV=test", "-e", "APP_URL=https://rehearsal.example.invalid",
                  "-e", "DB_DSN=" + DSN, "-e", "DB_USER=root", "-e", "DB_PASSWORD", "-e", "APP_KEY",
                  "-e", "PROVISIONING_ALLOWED=false", "-e", "PAYMENT_LIVE_ALLOWED=false", "-e", "EXTERNAL_MAIL_ALLOWED=false",
                  "-e", "TEST_DB_DSN=" + DSN, "-e", "TEST_DB_USER=root", "-e", "TEST_DB_PASSWORD", "-e", "HTTP_TEST_ENGINE=interpreter"]
        if phase == "install":
            record["release_manifest_sha256"] = sha((installed / "release-manifest.json").read_bytes())
            mounts = ["--mount", f"type=bind,source={installed},target=/package,readonly",
                      "--mount", f"type=bind,source={ROOT / 'tools/release/rehearse.php'},target=/rehearse.php,readonly",
                      "-e", "F6H_MANIFEST=" + record["release_manifest_sha256"]]
            script = INSTALL
        else:
            mounts = ["--mount", f"type=bind,source={ROOT},target=/input,readonly",
                      "--mount", f"type=bind,source={phar},target=/analysis/phpstan.phar,readonly"]
            script = REGRESSION
        result = docker(*common, *mounts, "--entrypoint", "sh", IMAGES[version], "-c", script)
        collect(result.stdout, record)
        record["exit"] = result.returncode
    except RuntimeError as error:
        record.update(exit=2, error=str(error))
    finally:
        for obj in (app, db):
            info = docker("inspect", "--format", '{{index .Config.Labels "hostider.f6h"}}', obj)
            if info.returncode == 0 and info.stdout.strip() == tag:
                docker("rm", "-f", obj)
        info = docker("network", "inspect", "--format", '{{index .Labels "hostider.f6h"}}', net)
        if info.returncode == 0 and info.stdout.strip() == tag:
            docker("network", "rm", net)
        (EVIDENCE / (tag + ".json")).write_bytes((json.dumps(record, indent=2) + "\n").encode())
        print(json.dumps(record))
    return record["exit"]


if __name__ == "__main__":
    EVIDENCE.mkdir(parents=True, exist_ok=True)
    if sys.argv[1] == "prepare":
        (EVIDENCE / "source-manifest.json").write_bytes(source_manifest())
    else:
        if (EVIDENCE / "source-manifest.json").read_bytes() != source_manifest():
            raise SystemExit("Source manifest mismatch")
        sys.exit(run(sys.argv[1], sys.argv[2], Path(sys.argv[3]).resolve(), Path(sys.argv[4]).resolve()))
