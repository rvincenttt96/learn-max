#!/usr/bin/env python3
"""Ghazal panel agent — search, edit, and deploy like a Cursor coding agent.

Commands:
  python3 agent.py doctor
  python3 agent.py mcp
  python3 agent.py serve
  python3 agent.py run "ریمارک سوپروایزر برای معلم دیده شود"
  python3 agent.py call search_code --pattern term_plan
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from lib.paths import default_root, find_ops, load_env_file
from lib.providers import detect_provider, missing_llm_help, run_agent
from lib.tools import PanelContext, ToolError, run_tool, tool_defs


def build_ctx(root: str) -> PanelContext:
    ops = find_ops()
    load_env_file(ops)
    panel_root = Path(root).resolve() if root else default_root(ops)
    return PanelContext.load(root=panel_root, ops=ops)


def cmd_doctor(ctx: PanelContext) -> int:
    print(run_tool(ctx, "panel_status", {}))
    provider = detect_provider()
    if provider["kind"] == "none":
        print()
        print(missing_llm_help())
        return 0
    print(f"\nLLM ready: {provider['label']}")
    return 0


def cmd_call(ctx: PanelContext, name: str, args_json: str, extra: list[str]) -> int:
    args: dict = json.loads(args_json) if args_json else {}
    # convenience: leftover argv like --pattern x --glob '*.php'
    i = 0
    while i < len(extra):
        tok = extra[i]
        if tok.startswith("--") and i + 1 < len(extra):
            key = tok[2:].replace("-", "_")
            val: object = extra[i + 1]
            if key == "files":
                val = extra[i + 1 :]
                args[key] = val
                break
            if val in ("true", "false"):
                val = val == "true"
            elif str(val).isdigit():
                val = int(val)
            args[key] = val
            i += 2
            continue
        i += 1
    try:
        print(run_tool(ctx, name, args))
        return 0
    except (ToolError, ValueError, OSError) as e:
        print(str(e), file=sys.stderr)
        return 1


def cmd_run(ctx: PanelContext, message: str) -> int:
    constitution = (ctx.ops / "constitution.md").read_text(encoding="utf-8")

    def on_event(ev: dict) -> None:
        if ev.get("type") == "tool_start":
            print(f"→ {ev.get('name')} {json.dumps(ev.get('args') or {}, ensure_ascii=False)[:400]}", flush=True)
        elif ev.get("type") == "tool_end":
            flag = "ok" if ev.get("ok") else "fail"
            snippet = (ev.get("result") or "").replace("\n", " ")[:220]
            print(f"  [{flag}] {snippet}", flush=True)

    try:
        text = run_agent(
            constitution,
            message,
            tool_defs(),
            lambda n, a: run_tool(ctx, n, a),
            on_event,
        )
    except Exception as e:  # noqa: BLE001
        print(str(e), file=sys.stderr)
        return 1
    print()
    print(text)
    return 0


def main() -> None:
    parser = argparse.ArgumentParser(description="Ghazal panel agent (not an MCP marketplace plugin — this IS the agent).")
    parser.add_argument("--root", default="", help="Private panel repo root (or PANEL_ROOT)")
    sub = parser.add_subparsers(dest="cmd", required=True)

    sub.add_parser("doctor", help="Check panel root, DA config, and LLM keys")
    sub.add_parser("mcp", help="Stdio MCP server for Cursor / Gemini CLI / Claude Desktop")
    serve = sub.add_parser("serve", help="Playground HTTP UI")
    serve.add_argument("--host", default="0.0.0.0")
    serve.add_argument("--port", type=int, default=4547)
    run_p = sub.add_parser("run", help="One-shot agentic turn (needs GEMINI_API_KEY or similar)")
    run_p.add_argument("message")
    call_p = sub.add_parser("call", help="Call one tool directly")
    call_p.add_argument("name")
    call_p.add_argument("--args", default="", help="JSON object")
    call_p.add_argument("extra", nargs=argparse.REMAINDER)

    args = parser.parse_args()
    ctx = build_ctx(args.root)

    if args.cmd == "doctor":
        sys.exit(cmd_doctor(ctx))
    if args.cmd == "mcp":
        from lib.mcp_stdio import serve_mcp

        serve_mcp(ctx)
        return
    if args.cmd == "serve":
        from lib.http_server import serve_http

        serve_http(ctx, args.host, args.port)
        return
    if args.cmd == "run":
        sys.exit(cmd_run(ctx, args.message))
    if args.cmd == "call":
        sys.exit(cmd_call(ctx, args.name, args.args, args.extra))
    parser.error("unknown command")


if __name__ == "__main__":
    main()
