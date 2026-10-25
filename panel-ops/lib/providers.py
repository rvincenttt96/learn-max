from __future__ import annotations

import json
import os
import urllib.error
import urllib.request
from typing import Any, Callable, Iterator


ToolHandler = Callable[[str, dict[str, Any]], str]


def detect_provider() -> dict[str, str]:
    if os.environ.get("GEMINI_API_KEY") or os.environ.get("GOOGLE_API_KEY"):
        model = os.environ.get("GEMINI_MODEL", "gemini-2.5-flash")
        return {"kind": "gemini", "label": f"gemini:{model}", "model": model}
    if os.environ.get("ANTHROPIC_API_KEY"):
        model = os.environ.get("ANTHROPIC_MODEL", "claude-sonnet-4-5")
        return {"kind": "anthropic", "label": f"anthropic:{model}", "model": model}
    if os.environ.get("OPENAI_API_KEY") or os.environ.get("OPENAI_BASE_URL"):
        model = os.environ.get("OPENAI_MODEL", "gpt-4.1")
        return {"kind": "openai", "label": f"openai:{model}", "model": model}
    return {"kind": "none", "label": "none", "model": ""}


def missing_llm_help() -> str:
    return (
        "هیچ کلید مدلی تنظیم نشده. این ایجنت برای فکر کردن به یکی از این‌ها نیاز دارد:\n"
        "1) MCP را به Cursor یا Gemini CLI وصل کن تا خود مدل میزبان ابزارها را صدا بزند "
        "(پیشنهاد اصلی برای جمینای ۴).\n"
        "2) یا GEMINI_API_KEY را در panel-ops/.env بگذار و همین playground / `agent.py run` را اجرا کن.\n"
        "OPENAI_API_KEY و ANTHROPIC_API_KEY هم پشتیبانی می‌شوند.\n"
        "رمز DirectAdmin را با کلید مدل قاطی نکن."
    )


def gemini_schema(tool_defs: list[dict[str, Any]]) -> list[dict[str, Any]]:
    out = []
    for t in tool_defs:
        out.append(
            {
                "name": t["name"],
                "description": t["description"],
                "parameters": t.get("parameters") or {"type": "object", "properties": {}},
            }
        )
    return out


def openai_schema(tool_defs: list[dict[str, Any]]) -> list[dict[str, Any]]:
    return [
        {
            "type": "function",
            "function": {
                "name": t["name"],
                "description": t["description"],
                "parameters": t.get("parameters") or {"type": "object", "properties": {}},
            },
        }
        for t in tool_defs
    ]


def _http_json(url: str, payload: dict[str, Any], headers: dict[str, str], timeout: int = 120) -> dict[str, Any]:
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json", **headers},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", "replace")
        raise RuntimeError(f"LLM HTTP {e.code}: {body[:1500]}") from e


def run_gemini(
    system: str,
    user: str,
    tools: list[dict[str, Any]],
    call_tool: ToolHandler,
    on_event: Callable[[dict[str, Any]], None] | None = None,
) -> str:
    key = os.environ.get("GEMINI_API_KEY") or os.environ.get("GOOGLE_API_KEY")
    model = os.environ.get("GEMINI_MODEL", "gemini-2.5-flash")
    url = (
        f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"
        f"?key={key}"
    )
    contents: list[dict[str, Any]] = [{"role": "user", "parts": [{"text": user}]}]
    final = ""
    for _ in range(24):
        payload = {
            "systemInstruction": {"parts": [{"text": system}]},
            "contents": contents,
            "tools": [{"functionDeclarations": gemini_schema(tools)}],
            "toolConfig": {"functionCallingConfig": {"mode": "AUTO"}},
            "generationConfig": {"temperature": 0.2},
        }
        data = _http_json(url, payload, {})
        cand = (data.get("candidates") or [{}])[0]
        parts = ((cand.get("content") or {}).get("parts")) or []
        fn_calls = [p["functionCall"] for p in parts if "functionCall" in p]
        texts = [p.get("text") or "" for p in parts if p.get("text")]
        if fn_calls:
            contents.append({"role": "model", "parts": parts})
            responses = []
            for fc in fn_calls:
                name = fc.get("name") or ""
                args = fc.get("args") or {}
                if on_event:
                    on_event({"type": "tool_start", "name": name, "args": args})
                try:
                    result = call_tool(name, args)
                    ok = True
                except Exception as e:  # noqa: BLE001 — surface tool errors to the model
                    result = f"ERROR: {e}"
                    ok = False
                if on_event:
                    on_event({"type": "tool_end", "name": name, "ok": ok, "result": result[:4000]})
                responses.append({"functionResponse": {"name": name, "response": {"result": result}}})
            contents.append({"role": "user", "parts": responses})
            continue
        final = "\n".join(t for t in texts if t).strip()
        break
    return final or "مدل جواب متنی نداد."


def run_openai(
    system: str,
    user: str,
    tools: list[dict[str, Any]],
    call_tool: ToolHandler,
    on_event: Callable[[dict[str, Any]], None] | None = None,
) -> str:
    api_key = os.environ.get("OPENAI_API_KEY") or "no-key"
    base = os.environ.get("OPENAI_BASE_URL", "https://api.openai.com/v1").rstrip("/")
    model = os.environ.get("OPENAI_MODEL", "gpt-4.1")
    messages: list[dict[str, Any]] = [
        {"role": "system", "content": system},
        {"role": "user", "content": user},
    ]
    final = ""
    for _ in range(24):
        payload = {
            "model": model,
            "messages": messages,
            "tools": openai_schema(tools),
            "temperature": 0.2,
        }
        data = _http_json(
            base + "/chat/completions",
            payload,
            {"Authorization": f"Bearer {api_key}"},
        )
        msg = ((data.get("choices") or [{}])[0].get("message")) or {}
        tool_calls = msg.get("tool_calls") or []
        if tool_calls:
            messages.append(msg)
            for tc in tool_calls:
                fn = tc.get("function") or {}
                name = fn.get("name") or ""
                raw_args = fn.get("arguments") or "{}"
                try:
                    args = json.loads(raw_args) if isinstance(raw_args, str) else (raw_args or {})
                except json.JSONDecodeError:
                    args = {}
                if on_event:
                    on_event({"type": "tool_start", "name": name, "args": args})
                try:
                    result = call_tool(name, args)
                    ok = True
                except Exception as e:  # noqa: BLE001
                    result = f"ERROR: {e}"
                    ok = False
                if on_event:
                    on_event({"type": "tool_end", "name": name, "ok": ok, "result": result[:4000]})
                messages.append(
                    {
                        "role": "tool",
                        "tool_call_id": tc.get("id") or name,
                        "content": result,
                    }
                )
            continue
        final = (msg.get("content") or "").strip()
        break
    return final or "مدل جواب متنی نداد."


def run_anthropic(
    system: str,
    user: str,
    tools: list[dict[str, Any]],
    call_tool: ToolHandler,
    on_event: Callable[[dict[str, Any]], None] | None = None,
) -> str:
    key = os.environ.get("ANTHROPIC_API_KEY")
    model = os.environ.get("ANTHROPIC_MODEL", "claude-sonnet-4-5")
    a_tools = []
    for t in tools:
        a_tools.append(
            {
                "name": t["name"],
                "description": t["description"],
                "input_schema": t.get("parameters") or {"type": "object", "properties": {}},
            }
        )
    messages: list[dict[str, Any]] = [{"role": "user", "content": user}]
    final = ""
    for _ in range(24):
        payload = {
            "model": model,
            "max_tokens": 4096,
            "system": system,
            "tools": a_tools,
            "messages": messages,
        }
        data = _http_json(
            "https://api.anthropic.com/v1/messages",
            payload,
            {
                "x-api-key": key or "",
                "anthropic-version": "2023-06-01",
            },
        )
        blocks = data.get("content") or []
        uses = [b for b in blocks if b.get("type") == "tool_use"]
        texts = [b.get("text") or "" for b in blocks if b.get("type") == "text"]
        if uses:
            messages.append({"role": "assistant", "content": blocks})
            results = []
            for b in uses:
                name = b.get("name") or ""
                args = b.get("input") or {}
                if on_event:
                    on_event({"type": "tool_start", "name": name, "args": args})
                try:
                    result = call_tool(name, args)
                    ok = True
                except Exception as e:  # noqa: BLE001
                    result = f"ERROR: {e}"
                    ok = False
                if on_event:
                    on_event({"type": "tool_end", "name": name, "ok": ok, "result": result[:4000]})
                results.append(
                    {
                        "type": "tool_result",
                        "tool_use_id": b.get("id"),
                        "content": result,
                    }
                )
            messages.append({"role": "user", "content": results})
            continue
        final = "\n".join(t for t in texts if t).strip()
        break
    return final or "مدل جواب متنی نداد."


def run_agent(
    system: str,
    user: str,
    tools: list[dict[str, Any]],
    call_tool: ToolHandler,
    on_event: Callable[[dict[str, Any]], None] | None = None,
) -> str:
    kind = detect_provider()["kind"]
    if kind == "gemini":
        return run_gemini(system, user, tools, call_tool, on_event)
    if kind == "anthropic":
        return run_anthropic(system, user, tools, call_tool, on_event)
    if kind == "openai":
        return run_openai(system, user, tools, call_tool, on_event)
    raise RuntimeError(missing_llm_help())


def iter_agent_events(
    system: str,
    user: str,
    tools: list[dict[str, Any]],
    call_tool: ToolHandler,
) -> Iterator[dict[str, Any]]:
    events: list[dict[str, Any]] = []

    def on_event(ev: dict[str, Any]) -> None:
        events.append(ev)

    try:
        text = run_agent(system, user, tools, call_tool, on_event)
    except Exception as e:  # noqa: BLE001
        yield {"type": "error", "message": str(e)}
        return
    for ev in events:
        yield ev
    yield {"type": "final", "text": text}
