from __future__ import annotations

import os
import re
from pathlib import Path


BLOCK_NAME_RE = re.compile(
    r"(directadmin-config|rollcall-config|liara-ai-config|seo-secret|"
    r"identity-config|bookstore-config)\.php$|panel-ops/config\.json$|/(config)\.json$",
    re.I,
)


def find_ops() -> Path:
    here = Path(__file__).resolve().parent.parent
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
    if ops.name == "panel-ops" and (
        (parent / "dashboard").is_dir() or (parent / "includes").is_dir()
    ):
        return parent
    cwd = Path.cwd().resolve()
    if (cwd / "dashboard").is_dir() or (cwd / "includes").is_dir():
        return cwd
    return cwd


def posix(rel: str) -> str:
    s = str(rel).replace("\\", "/")
    while s.startswith("./"):
        s = s[2:]
    return s


def load_env_file(ops: Path) -> None:
    for candidate in (ops / ".env", ops / "config.env"):
        if not candidate.is_file():
            continue
        for raw in candidate.read_text(encoding="utf-8").splitlines():
            line = raw.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, val = line.partition("=")
            key = key.strip()
            val = val.strip().strip("'").strip('"')
            if key and key not in os.environ:
                os.environ[key] = val


def resolve_rel(root: Path, raw: str) -> str:
    path = Path(raw)
    if path.is_absolute():
        try:
            rel = posix(path.resolve().relative_to(root))
        except ValueError as exc:
            raise ValueError(f"مسیر بیرون از ریپوی پنل است: {raw}") from exc
        return rel
    rel = posix(raw)
    resolved = (root / rel).resolve()
    try:
        resolved.relative_to(root)
    except ValueError as exc:
        raise ValueError(f"مسیر بیرون از ریپوی پنل است: {raw}") from exc
    return posix(str(resolved.relative_to(root)))


def is_blocked(rel: str, extra: list[str] | None = None) -> bool:
    rel = posix(rel)
    blocked = {posix(x) for x in (extra or [])}
    if rel in blocked or rel.startswith("panel-ops/config"):
        return True
    name = Path(rel).name.lower()
    if name in {"config.json", ".env"}:
        return True
    return bool(BLOCK_NAME_RE.search(rel))
