from __future__ import annotations

import json
import sys
from typing import Any

from .tools import PanelContext, ToolError, run_tool, tool_defs


PROTOCOL = "2024-11-05"
_NDJSON = False


def _read_message() -> dict[str, Any] | None:
    global _NDJSON
    line = sys.stdin.buffer.readline()
    if not line:
        return None
    if line.lstrip().startswith(b"{"):
        _NDJSON = True
        return json.loads(line.decode("utf-8"))
    headers: dict[str, str] = {}
    while True:
        if line in (b"\r\n", b"\n"):
            break
        key, _, val = line.decode("utf-8").partition(":")
        headers[key.strip().lower()] = val.strip()
        line = sys.stdin.buffer.readline()
        if not line:
            return None
    n = int(headers.get("content-length") or "0")
    if n <= 0:
        return None
    body = sys.stdin.buffer.read(n)
    return json.loads(body.decode("utf-8"))


def _write_message(payload: dict[str, Any]) -> None:
    raw = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    if _NDJSON:
        sys.stdout.buffer.write(raw + b"\n")
    else:
        sys.stdout.buffer.write(f"Content-Length: {len(raw)}\r\n\r\n".encode("ascii") + raw)
    sys.stdout.buffer.flush()


def _result(msg_id: Any, result: Any) -> dict[str, Any]:
    return {"jsonrpc": "2.0", "id": msg_id, "result": result}


def _error(msg_id: Any, code: int, message: str) -> dict[str, Any]:
    return {"jsonrpc": "2.0", "id": msg_id, "error": {"code": code, "message": message}}


def serve_mcp(ctx: PanelContext) -> None:
    tools = [
        {
            "name": t["name"],
            "description": t["description"],
            "inputSchema": t.get("parameters") or {"type": "object", "properties": {}},
        }
        for t in tool_defs()
    ]
    while True:
        msg = _read_message()
        if msg is None:
            return
        method = msg.get("method")
        msg_id = msg.get("id")
        params = msg.get("params") or {}
        if method == "initialize":
            _write_message(
                _result(
                    msg_id,
                    {
                        "protocolVersion": PROTOCOL,
                        "capabilities": {"tools": {"listChanged": False}},
                        "serverInfo": {"name": "ghazal-panel-agent", "version": "2.0.0"},
                    },
                )
            )
            continue
        if method == "notifications/initialized" or method == "notifications/cancelled":
            continue
        if method == "ping":
            _write_message(_result(msg_id, {}))
            continue
        if method == "tools/list":
            _write_message(_result(msg_id, {"tools": tools}))
            continue
        if method == "tools/call":
            name = params.get("name") or ""
            args = params.get("arguments") or {}
            try:
                text = run_tool(ctx, name, args)
                _write_message(
                    _result(
                        msg_id,
                        {"content": [{"type": "text", "text": text}], "isError": False},
                    )
                )
            except (ToolError, ValueError, OSError) as e:
                _write_message(
                    _result(
                        msg_id,
                        {
                            "content": [{"type": "text", "text": str(e)}],
                            "isError": True,
                        },
                    )
                )
            continue
        if msg_id is not None:
            _write_message(_error(msg_id, -32601, f"Unknown method {method}"))
