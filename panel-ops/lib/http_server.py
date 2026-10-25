from __future__ import annotations

import json
import posixpath
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

from .paths import find_ops
from .providers import detect_provider, missing_llm_help, run_agent
from .tools import PanelContext, ToolError, run_tool, tool_defs


def _json_bytes(data: Any, status: int = 200) -> tuple[int, bytes, str]:
    return status, json.dumps(data, ensure_ascii=False).encode("utf-8"), "application/json; charset=utf-8"


def make_handler(ctx: PanelContext, dist: Path):
    constitution = (find_ops() / "constitution.md").read_text(encoding="utf-8")

    class Handler(BaseHTTPRequestHandler):
        def log_message(self, fmt: str, *args: Any) -> None:
            sys_stderr = __import__("sys").stderr
            sys_stderr.write("playground: " + (fmt % args) + "\n")

        def _cors(self) -> None:
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Access-Control-Allow-Headers", "Content-Type")
            self.send_header("Access-Control-Allow-Methods", "GET,POST,OPTIONS")

        def do_OPTIONS(self) -> None:  # noqa: N802
            self.send_response(204)
            self._cors()
            self.end_headers()

        def _send(self, status: int, body: bytes, content_type: str) -> None:
            self.send_response(status)
            self._cors()
            self.send_header("Content-Type", content_type)
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

        def do_GET(self) -> None:  # noqa: N802
            parsed = urlparse(self.path)
            if parsed.path == "/api/health":
                try:
                    status_text = run_tool(ctx, "panel_status", {})
                except Exception as e:  # noqa: BLE001
                    status, body, ctype = _json_bytes({"ok": False, "error": str(e)}, 500)
                    self._send(status, body, ctype)
                    return
                provider = detect_provider()
                payload = {
                    "ok": True,
                    "llm": provider["label"],
                    "needs_key": provider["kind"] == "none",
                    "status": status_text,
                    "tools": [t["name"] for t in tool_defs()],
                    "help": missing_llm_help() if provider["kind"] == "none" else "",
                }
                status, body, ctype = _json_bytes(payload)
                self._send(status, body, ctype)
                return
            self._static(parsed.path)

        def do_POST(self) -> None:  # noqa: N802
            parsed = urlparse(self.path)
            length = int(self.headers.get("Content-Length") or "0")
            raw = self.rfile.read(length) if length else b"{}"
            try:
                data = json.loads(raw.decode("utf-8") or "{}")
            except json.JSONDecodeError:
                status, body, ctype = _json_bytes({"error": "JSON نامعتبر"}, 400)
                self._send(status, body, ctype)
                return
            if parsed.path == "/api/tool":
                name = str(data.get("name") or "")
                args = data.get("args") or {}
                try:
                    text = run_tool(ctx, name, args)
                    status, body, ctype = _json_bytes({"ok": True, "result": text})
                except (ToolError, ValueError, OSError) as e:
                    status, body, ctype = _json_bytes({"ok": False, "error": str(e)}, 400)
                self._send(status, body, ctype)
                return
            if parsed.path == "/api/chat":
                message = str(data.get("message") or "").strip()
                if not message:
                    status, body, ctype = _json_bytes({"error": "پیام خالی است"}, 400)
                    self._send(status, body, ctype)
                    return
                events: list[dict[str, Any]] = []

                def on_event(ev: dict[str, Any]) -> None:
                    events.append(ev)

                try:
                    text = run_agent(
                        constitution,
                        message,
                        tool_defs(),
                        lambda n, a: run_tool(ctx, n, a),
                        on_event,
                    )
                    status, body, ctype = _json_bytes({"ok": True, "text": text, "events": events})
                except Exception as e:  # noqa: BLE001
                    status, body, ctype = _json_bytes(
                        {"ok": False, "error": str(e), "help": missing_llm_help(), "events": events},
                        400,
                    )
                self._send(status, body, ctype)
                return
            status, body, ctype = _json_bytes({"error": "not found"}, 404)
            self._send(status, body, ctype)

        def _static(self, url_path: str) -> None:
            if not dist.is_dir():
                html = _fallback_html().encode("utf-8")
                self._send(200, html, "text/html; charset=utf-8")
                return
            rel = posixpath.normpath(url_path).lstrip("/")
            if rel in ("", "."):
                rel = "index.html"
            candidate = (dist / rel).resolve()
            try:
                candidate.relative_to(dist.resolve())
            except ValueError:
                self._send(403, b"forbidden", "text/plain")
                return
            if candidate.is_dir():
                candidate = candidate / "index.html"
            if not candidate.is_file():
                candidate = dist / "index.html"
            if not candidate.is_file():
                self._send(404, b"not found", "text/plain")
                return
            data = candidate.read_bytes()
            ctype = {
                ".html": "text/html; charset=utf-8",
                ".js": "text/javascript; charset=utf-8",
                ".css": "text/css; charset=utf-8",
                ".svg": "image/svg+xml",
                ".json": "application/json",
                ".ico": "image/x-icon",
            }.get(candidate.suffix, "application/octet-stream")
            self._send(200, data, ctype)

    return Handler


def _fallback_html() -> str:
    return """<!doctype html>
<html lang="fa" dir="rtl">
<meta charset="utf-8"/>
<title>ایجنت پنل غزال</title>
<body style="font-family:Tahoma,sans-serif;background:#0f1a14;color:#e8f0ea;padding:32px">
<h1>ایجنت پنل غزال</h1>
<p>playground هنوز build نشده. از ریشهٔ ریپو:</p>
<pre>cd playground && npm install && npm run build</pre>
<p>یا برای توسعه: <code>npm run dev</code> در پوشهٔ playground</p>
</body></html>
"""


def serve_http(ctx: PanelContext, host: str, port: int) -> None:
    dist = find_ops() / "playground" / "dist"
    httpd = ThreadingHTTPServer((host, port), make_handler(ctx, dist))
    print(f"Ghazal panel agent: http://{host}:{port}", flush=True)
    httpd.serve_forever()
