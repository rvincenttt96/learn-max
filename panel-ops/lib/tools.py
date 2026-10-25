from __future__ import annotations

import json
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from .paths import find_ops, is_blocked, posix, resolve_rel

SKIP_DIRS = {
    ".git",
    "node_modules",
    "__pycache__",
    ".venv",
    "dist",
    "playground",
    "vendor",
}


@dataclass
class PanelContext:
    ops: Path
    root: Path
    targets: dict[str, Any]

    @classmethod
    def load(cls, root: Path | None = None, ops: Path | None = None) -> "PanelContext":
        ops = ops or find_ops()
        from .paths import default_root

        root = (root or default_root(ops)).resolve()
        targets = json.loads((ops / "targets.json").read_text(encoding="utf-8"))
        return cls(ops=ops, root=root, targets=targets)

    def blocked_list(self) -> list[str]:
        return list(self.targets.get("blocked") or [])

    def local(self, rel: str) -> Path:
        return self.root / posix(rel)


def _truncate(text: str, limit: int = 24000) -> str:
    if len(text) <= limit:
        return text
    return text[:limit] + f"\n… [truncated {len(text) - limit} chars]"


def tool_defs() -> list[dict[str, Any]]:
    return [
        {
            "name": "search_code",
            "description": "Search the private Ghazal panel repo with a regex. Use this before editing.",
            "parameters": {
                "type": "object",
                "properties": {
                    "pattern": {"type": "string", "description": "Regex to search for"},
                    "glob": {"type": "string", "description": "Optional glob, e.g. *.php"},
                    "path": {"type": "string", "description": "Optional subdirectory relative to panel root"},
                    "max_results": {"type": "integer", "description": "Default 40"},
                },
                "required": ["pattern"],
            },
        },
        {
            "name": "read_file",
            "description": "Read a text file from the panel repo. Paths are relative to PANEL_ROOT.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "offset": {"type": "integer", "description": "1-based start line"},
                    "limit": {"type": "integer", "description": "Max lines to return"},
                },
                "required": ["path"],
            },
        },
        {
            "name": "list_files",
            "description": "List files matching a glob under the panel repo.",
            "parameters": {
                "type": "object",
                "properties": {
                    "glob": {"type": "string", "description": "e.g. dashboard/teacher/*term-plan*"},
                    "max_results": {"type": "integer"},
                },
                "required": ["glob"],
            },
        },
        {
            "name": "edit_file",
            "description": "Replace exact text in a file. old_string must match uniquely unless replace_all is true.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "old_string": {"type": "string"},
                    "new_string": {"type": "string"},
                    "replace_all": {"type": "boolean"},
                },
                "required": ["path", "old_string", "new_string"],
            },
        },
        {
            "name": "write_file",
            "description": "Create or overwrite a text file in the panel repo. Do not use for secrets.",
            "parameters": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "contents": {"type": "string"},
                },
                "required": ["path", "contents"],
            },
        },
        {
            "name": "deploy_preview",
            "description": "Map files to DirectAdmin paths without uploading. Always call this before deploy_live.",
            "parameters": {
                "type": "object",
                "properties": {
                    "files": {
                        "type": "array",
                        "items": {"type": "string"},
                        "description": "Paths relative to panel root",
                    }
                },
                "required": ["files"],
            },
        },
        {
            "name": "deploy_live",
            "description": "Upload files to live DirectAdmin (overwrite) and verify by download. Never send secret files.",
            "parameters": {
                "type": "object",
                "properties": {
                    "files": {
                        "type": "array",
                        "items": {"type": "string"},
                    }
                },
                "required": ["files"],
            },
        },
        {
            "name": "panel_status",
            "description": "Show panel root, DirectAdmin targets, whether DA password is present (boolean only), and LLM provider.",
            "parameters": {"type": "object", "properties": {}},
        },
    ]


class ToolError(Exception):
    pass


def run_tool(ctx: PanelContext, name: str, args: dict[str, Any] | None = None) -> str:
    args = args or {}
    fn = {
        "search_code": _search,
        "read_file": _read,
        "list_files": _list,
        "edit_file": _edit,
        "write_file": _write,
        "deploy_preview": lambda c, a: _deploy(c, a, dry_run=True),
        "deploy_live": lambda c, a: _deploy(c, a, dry_run=False),
        "panel_status": _status,
    }.get(name)
    if not fn:
        raise ToolError(f"ابزار ناشناس: {name}")
    return fn(ctx, args)


def _search(ctx: PanelContext, args: dict[str, Any]) -> str:
    pattern = str(args.get("pattern") or "")
    if not pattern:
        raise ToolError("pattern لازم است")
    glob = str(args.get("glob") or "")
    rel_path = str(args.get("path") or "")
    max_results = int(args.get("max_results") or 40)
    target = ctx.root
    if rel_path:
        rel = resolve_rel(ctx.root, rel_path)
        target = ctx.local(rel)
    rg = Path("/exec-daemon/rg")
    cmd = ["rg" if not rg.is_file() else str(rg), "-n", "--hidden", "--no-heading", "-S", pattern]
    if glob:
        cmd.extend(["-g", glob])
    for d in SKIP_DIRS:
        cmd.extend(["-g", f"!{d}/**"])
    cmd.append(str(target))
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    except FileNotFoundError:
        return _search_python(ctx, pattern, glob, target, max_results)
    lines = [ln for ln in proc.stdout.splitlines() if ln.strip()]
    if proc.returncode not in (0, 1) and not lines:
        err = (proc.stderr or "").strip()
        if "No such file" in err:
            return err
        return _search_python(ctx, pattern, glob, target, max_results)
    clipped = lines[:max_results]
    body = "\n".join(_relativize(ctx.root, ln) for ln in clipped)
    extra = ""
    if len(lines) > max_results:
        extra = f"\n… {len(lines) - max_results} more matches"
    return body + extra if body else "نتیجه‌ای پیدا نشد."


def _relativize(root: Path, line: str) -> str:
    prefix = str(root) + "/"
    if line.startswith(prefix):
        return line[len(prefix) :]
    return line


def _search_python(ctx: PanelContext, pattern: str, glob: str, target: Path, max_results: int) -> str:
    import fnmatch
    import re as _re

    cre = _re.compile(pattern)
    hits: list[str] = []
    files: list[Path] = []
    if target.is_file():
        files = [target]
    else:
        for p in target.rglob("*"):
            if not p.is_file():
                continue
            if any(part in SKIP_DIRS for part in p.parts):
                continue
            if glob and not fnmatch.fnmatch(p.name, glob) and not fnmatch.fnmatch(str(p.relative_to(ctx.root)), glob):
                continue
            files.append(p)
    for p in files:
        try:
            text = p.read_text(encoding="utf-8")
        except (OSError, UnicodeDecodeError):
            continue
        rel = posix(str(p.relative_to(ctx.root)))
        for i, line in enumerate(text.splitlines(), 1):
            if cre.search(line):
                hits.append(f"{rel}:{i}:{line}")
                if len(hits) >= max_results:
                    return "\n".join(hits)
    return "\n".join(hits) if hits else "نتیجه‌ای پیدا نشد."


def _read(ctx: PanelContext, args: dict[str, Any]) -> str:
    rel = resolve_rel(ctx.root, str(args.get("path") or ""))
    if is_blocked(rel, ctx.blocked_list()):
        raise ToolError(f"فایل رمز است؛ خوانده نمی‌شود: {rel}")
    path = ctx.local(rel)
    if not path.is_file():
        raise ToolError(f"فایل نیست: {rel}")
    text = path.read_text(encoding="utf-8")
    lines = text.splitlines()
    offset = int(args["offset"]) if args.get("offset") else 1
    limit = int(args["limit"]) if args.get("limit") else 400
    if offset < 1:
        offset = 1
    chunk = lines[offset - 1 : offset - 1 + limit]
    numbered = [f"{i + offset}|{line}" for i, line in enumerate(chunk)]
    header = f"{rel} lines {offset}-{offset + len(chunk) - 1} of {len(lines)}\n"
    return header + _truncate("\n".join(numbered))


def _list(ctx: PanelContext, args: dict[str, Any]) -> str:
    import fnmatch

    glob = str(args.get("glob") or "*")
    max_results = int(args.get("max_results") or 80)
    matches: list[str] = []
    for p in ctx.root.rglob("*"):
        if not p.is_file():
            continue
        if any(part in SKIP_DIRS for part in p.parts):
            continue
        rel = posix(str(p.relative_to(ctx.root)))
        if fnmatch.fnmatch(rel, glob) or fnmatch.fnmatch(p.name, glob):
            matches.append(rel)
            if len(matches) >= max_results:
                break
    return "\n".join(matches) if matches else "فایلی مطابق glob پیدا نشد."


def _edit(ctx: PanelContext, args: dict[str, Any]) -> str:
    rel = resolve_rel(ctx.root, str(args.get("path") or ""))
    if is_blocked(rel, ctx.blocked_list()):
        raise ToolError(f"فایل رمز است؛ ادیت نمی‌شود: {rel}")
    path = ctx.local(rel)
    if not path.is_file():
        raise ToolError(f"فایل نیست: {rel}")
    old = str(args.get("old_string") or "")
    new = str(args.get("new_string") or "")
    if not old:
        raise ToolError("old_string خالی است")
    text = path.read_text(encoding="utf-8")
    count = text.count(old)
    if count == 0:
        raise ToolError(f"old_string در {rel} پیدا نشد")
    replace_all = bool(args.get("replace_all"))
    if count > 1 and not replace_all:
        raise ToolError(f"old_string در {rel} {count} بار آمده؛ replace_all بگذار یا متن یکتا بده")
    path.write_text(text.replace(old, new) if replace_all else text.replace(old, new, 1), encoding="utf-8")
    return f"edited {rel} ({count if replace_all else 1} replacement)"


def _write(ctx: PanelContext, args: dict[str, Any]) -> str:
    rel = resolve_rel(ctx.root, str(args.get("path") or ""))
    if is_blocked(rel, ctx.blocked_list()):
        raise ToolError(f"فایل رمز است؛ نوشته نمی‌شود: {rel}")
    if rel.startswith("wordpress/"):
        raise ToolError("wordpress/ را با این ایجنت ننویس مگر کاربر صریح سایت اصلی را بخواهد")
    path = ctx.local(rel)
    path.parent.mkdir(parents=True, exist_ok=True)
    contents = str(args.get("contents") or "")
    path.write_text(contents, encoding="utf-8")
    return f"wrote {rel} ({len(contents.encode('utf-8'))} bytes)"


def _deploy(ctx: PanelContext, args: dict[str, Any], dry_run: bool) -> str:
    files = args.get("files") or []
    if isinstance(files, str):
        files = [files]
    if not files:
        raise ToolError("files خالی است")
    rels = [resolve_rel(ctx.root, str(f)) for f in files]
    for rel in rels:
        if is_blocked(rel, ctx.blocked_list()):
            raise ToolError(f"Blocked secret file: {rel}")
        if not ctx.local(rel).is_file():
            raise ToolError(f"Not a file: {rel}")
    cmd = [sys.executable, str(ctx.ops / "deploy.py"), "--root", str(ctx.root)]
    if dry_run:
        cmd.append("--dry-run")
    cmd.extend(rels)
    proc = subprocess.run(cmd, capture_output=True, text=True, timeout=180)
    out = (proc.stdout or "") + (("\n" + proc.stderr) if proc.stderr else "")
    if proc.returncode != 0:
        raise ToolError(_truncate(out.strip() or f"deploy exit {proc.returncode}"))
    return _truncate(out.strip() or "ok")


def _status(ctx: PanelContext, _args: dict[str, Any]) -> str:
    from .providers import detect_provider

    cfg = ctx.ops / "config.json"
    php = ctx.root / "includes" / "directadmin-config.php"
    da_password = False
    if cfg.is_file():
        try:
            data = json.loads(cfg.read_text(encoding="utf-8"))
            da_password = bool(data.get("password"))
        except json.JSONDecodeError:
            da_password = False
    if not da_password and php.is_file():
        da_password = "password" in php.read_text(encoding="utf-8", errors="ignore")
    sites = []
    for key in ("panel", "accounting", "books"):
        t = ctx.targets[key]
        sites.append(f"{key}: {t['host']} ← {t['da_root']}")
    provider = detect_provider()
    return "\n".join(
        [
            f"ops={ctx.ops}",
            f"panel_root={ctx.root}",
            f"panel_root_exists={ctx.root.is_dir()}",
            f"has_dashboard={(ctx.root / 'dashboard').is_dir()}",
            f"has_includes={(ctx.root / 'includes').is_dir()}",
            f"da_password_present={da_password}",
            f"llm={provider['label']}",
            *sites,
        ]
    )
