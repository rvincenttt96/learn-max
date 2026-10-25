#!/usr/bin/env python3
"""Upload Ghazal panel files through DirectAdmin Evolution file manager.

Other models: edit files in this repo, then run this script. Do not use FTP.
Never print the DirectAdmin password.
"""
from __future__ import annotations

import argparse
import json
import re
import ssl
import sys
import uuid
import urllib.error
import urllib.parse
import urllib.request
import http.cookiejar
import os
from pathlib import Path


def find_ops() -> Path:
    here = Path(__file__).resolve().parent
    if (here / "targets.json").is_file():
        return here
    nested = here / "panel-ops"
    if (nested / "targets.json").is_file():
        return nested
    return here


def default_root(ops: Path) -> Path:
    env = os.environ.get("PANEL_ROOT", "").strip()
    if env:
        return Path(env).resolve()
    parent = ops.parent
    if ops.name == "panel-ops" and ((parent / "dashboard").is_dir() or (parent / "includes").is_dir()):
        return parent
    return Path.cwd().resolve()


OPS = find_ops()
ROOT = default_root(OPS)
TARGETS_PATH = OPS / "targets.json"

BLOCK_NAME_RE = re.compile(
    r"(directadmin-config|rollcall-config|liara-ai-config|seo-secret|"
    r"identity-config|bookstore-config)\.php$|panel-ops/config\.json$",
    re.I,
)


def load_targets() -> dict:
    return json.loads(TARGETS_PATH.read_text(encoding="utf-8"))


def load_secrets() -> dict:
    json_cfg = OPS / "config.json"
    if json_cfg.is_file():
        data = json.loads(json_cfg.read_text(encoding="utf-8"))
        if data.get("password"):
            return {
                "url": data.get("url") or "https://server16e.irwebspace.com:2223",
                "username": data.get("username") or "ghazali1",
                "password": data["password"],
            }
    php_cfg = ROOT / "includes" / "directadmin-config.php"
    if php_cfg.is_file():
        text = php_cfg.read_text(encoding="utf-8")
        def grab(key: str, default: str = "") -> str:
            m = re.search(r"'" + re.escape(key) + r"'\s*=>\s*'([^']*)'", text)
            return m.group(1) if m else default
        password = grab("password")
        if password:
            return {
                "url": grab("url", "https://server16e.irwebspace.com:2223"),
                "username": grab("username", "ghazali1"),
                "password": password,
            }
    sys.exit(
        "No DirectAdmin password found.\n"
        "Copy panel-ops/config.example.json to panel-ops/config.json and set password.\n"
        "Do not paste the password into chat."
    )


def posix(rel: str) -> str:
    return str(rel).replace("\\", "/").lstrip("./")


def is_blocked(rel: str, blocked: list[str]) -> bool:
    rel = posix(rel)
    if rel in blocked or rel.startswith("panel-ops/config"):
        return True
    return bool(BLOCK_NAME_RE.search(rel))


def map_remote(rel: str, targets: dict) -> tuple[str, str, str]:
    """Return (target_key, da_directory, filename)."""
    rel = posix(rel)
    if rel == "accounting/api.php":
        root = targets["accounting"]["da_root"]
        return "accounting", root + "/api", "api.php"
    if rel.startswith("accounting/"):
        rest = rel[len("accounting/") :]
        parent = str(Path(rest).parent).replace("\\", "/")
        filename = Path(rest).name
        da = targets["accounting"]["da_root"]
        directory = da if parent in (".", "") else da + "/" + parent
        return "accounting", directory, filename
    if rel.startswith("bookstore-shop/"):
        rest = rel[len("bookstore-shop/") :]
        parent = str(Path(rest).parent).replace("\\", "/")
        filename = Path(rest).name
        da = targets["books"]["da_root"]
        directory = da if parent in (".", "") else da + "/" + parent
        return "books", directory, filename
    if rel.startswith("wordpress/") or rel.startswith("panel-ops/"):
        raise SystemExit(f"Refusing to map {rel} as a panel file. Say which site if this is intentional.")
    parent = str(Path(rel).parent).replace("\\", "/")
    filename = Path(rel).name
    da = targets["panel"]["da_root"]
    directory = da if parent in (".", "") else da + "/" + parent
    return "panel", directory, filename


class DA:
    def __init__(self, url: str, username: str, password: str):
        self.url = url.rstrip("/")
        self.username = username
        self.password = password
        self.cj = http.cookiejar.CookieJar()
        ctx = ssl.create_default_context()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPSHandler(context=ctx),
            urllib.request.HTTPCookieProcessor(self.cj),
        )

    def call(self, method: str, path: str, data=None, headers=None, timeout=90):
        h = {"Accept": "application/json"}
        if headers:
            h.update(headers)
        body = data
        if isinstance(data, (dict, list)):
            body = json.dumps(data).encode("utf-8")
            h.setdefault("Content-Type", "application/json")
        url = path if path.startswith("http") else self.url + path
        req = urllib.request.Request(url, data=body, headers=h, method=method)
        try:
            with self.opener.open(req, timeout=timeout) as resp:
                return resp.status, dict(resp.headers), resp.read()
        except urllib.error.HTTPError as e:
            return e.code, dict(e.headers), e.read()

    def login(self) -> None:
        st, _, b = self.call("POST", "/api/login", {"username": self.username, "password": self.password})
        if st != 200:
            raise SystemExit(f"DirectAdmin login failed HTTP {st}")
        payload = json.loads(b.decode("utf-8", "replace"))
        login_url = payload.get("loginURL")
        if not login_url:
            raise SystemExit("DirectAdmin login did not return loginURL")
        # Evolution serves an auto-submit <form method="post"> instead of a 302.
        st, _, body = self.call("GET", login_url, None, {"Accept": "text/html"})
        if st not in (200, 303, 302):
            raise SystemExit(f"DirectAdmin login redirect failed HTTP {st}")
        if b"forms[0].submit" in body or b'<form method="post">' in body:
            st, _, body = self.call(
                "POST",
                login_url,
                b"",
                {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "Accept": "text/html",
                    "Referer": login_url,
                },
            )
            if st not in (200, 303, 302):
                raise SystemExit(f"DirectAdmin login form POST failed HTTP {st}")
        st, _, sess = self.call("GET", "/api/session")
        if st == 200:
            return
        st_lic, _, lic = self.call("GET", "/CMD_API_LICENSE")
        if st_lic == 402 or b"license is expired" in lic.lower() or b"License check failure" in lic:
            raise SystemExit(
                "DirectAdmin license is expired; file manager API returns ACCESS_DENIED. "
                "Renew the license on the DA box, then rerun deploy."
            )
        raise SystemExit(
            f"DirectAdmin session not usable HTTP {st}: {sess[:180].decode('utf-8', 'replace')}"
        )

    def upload(self, directory: str, filename: str, content: bytes) -> int:
        boundary = "----DA" + uuid.uuid4().hex
        parts = [
            (
                f"--{boundary}\r\n"
                f'Content-Disposition: form-data; name="file"; filename="{filename}"\r\n'
                "Content-Type: application/octet-stream\r\n\r\n"
            ).encode("utf-8")
            + content
            + b"\r\n"
            + f"--{boundary}--\r\n".encode("utf-8")
        ]
        body = b"".join(parts)
        q = "/api/filemanager-actions/upload?" + urllib.parse.urlencode(
            {"dir": directory, "overwrite": "true"}
        )
        st, _, resp = self.call(
            "POST",
            q,
            body,
            {"Content-Type": f"multipart/form-data; boundary={boundary}", "Accept": "*/*"},
        )
        self.last_body = resp
        return st

    def download(self, path: str) -> bytes:
        q = "/api/filemanager/download?" + urllib.parse.urlencode({"path": path})
        st, _, b = self.call("GET", q)
        if st != 200:
            raise SystemExit(f"Download failed HTTP {st} for {path}")
        return b


def encode_check(local: Path) -> bytes:
    data = local.read_bytes()
    if not data:
        raise SystemExit(f"Empty file: {local}")
    return data


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Deploy Ghazal panel files via DirectAdmin (not FTP, not MCP)."
    )
    parser.add_argument("files", nargs="*", help="Paths relative to the panel repo root")
    parser.add_argument("--root", default="", help="Panel repo root (or set PANEL_ROOT). Default: parent of panel-ops, else cwd")
    parser.add_argument("--dry-run", action="store_true", help="Print mapping only")
    parser.add_argument("--targets", action="store_true", help="Print site roots")
    args = parser.parse_args()
    global ROOT
    if args.root:
        ROOT = Path(args.root).resolve()
    targets = load_targets()

    if args.targets:
        for key in ("panel", "accounting", "books"):
            t = targets[key]
            print(f"{key}: {t['host']}  ←  {t['da_root']}")
        return

    if not args.files:
        parser.error("Pass at least one file, or --targets")

    blocked = list(targets.get("blocked") or [])
    jobs = []
    for raw in args.files:
        path = Path(raw)
        if path.is_absolute():
            try:
                rel = posix(path.resolve().relative_to(ROOT))
            except ValueError:
                raise SystemExit(f"File is outside the repo: {path}")
        else:
            rel = posix(raw)
        local = ROOT / rel
        if not local.is_file():
            raise SystemExit(f"Not a file: {rel}")
        if is_blocked(rel, blocked):
            raise SystemExit(f"Blocked secret file, will not upload: {rel}")
        site, directory, filename = map_remote(rel, targets)
        remote_file = directory + "/" + filename
        jobs.append((rel, local, site, directory, filename, remote_file))

    print("Mapped:")
    for rel, _, site, directory, filename, remote_file in jobs:
        host = targets[site]["host"]
        print(f"  {rel}")
        print(f"    → {host}  {remote_file}")

    if args.dry_run:
        return

    secrets = load_secrets()
    da = DA(secrets["url"], secrets["username"], secrets["password"])
    da.login()
    failed = []
    for rel, local, site, directory, filename, remote_file in jobs:
        content = encode_check(local)
        st = da.upload(directory, filename, content)
        if st not in (200, 204):
            extra = ""
            raw = getattr(da, "last_body", b"") or b""
            if raw:
                extra = "  " + raw[:180].decode("utf-8", "replace").replace("\n", " ")
            print(f"FAIL {rel} HTTP {st}{extra}")
            failed.append(rel)
            continue
        got = da.download(remote_file)
        ok = len(got) == len(content) or content[:80] in got
        print(f"OK   {rel}  HTTP {st}  remote={len(got)}b  match={ok}")
        if not ok:
            failed.append(rel)
    if failed:
        raise SystemExit("Deploy finished with failures: " + ", ".join(failed))


if __name__ == "__main__":
    main()
