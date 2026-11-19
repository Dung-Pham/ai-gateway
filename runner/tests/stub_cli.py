#!/usr/bin/env python3
"""CLI GIẢ cho test: bắt chước đầu ra THẬT của claude (stream-json), grok (json) và agy (json) đã thu thập ở đợt thăm dò.
Hành vi chọn bằng dấu hiệu trong nội dung prompt: @@AUTH @@LIMIT @@TOOLS @@SLOW @@GARBAGE @@DENIED @@REFRESH.
Nếu có STUB_LOG: ghi lại đối số, tên biến môi trường, HOME, cwd (KHÔNG ghi nội dung prompt)."""
import json
import os
import sys
import time

argv = sys.argv[1:]
prov = "claude" if "-p" in argv else "grok" if "--prompt-file" in argv else "agy"
if prov == "claude":
    prompt = sys.stdin.read()
elif prov == "grok":
    prompt = open(argv[argv.index("--prompt-file") + 1], encoding="utf-8").read()
else:
    prompt = argv[argv.index("--print") + 1]

if os.environ.get("STUB_LOG"):
    with open(os.environ["STUB_LOG"], "a") as f:
        f.write(json.dumps({"provider": prov, "argv": [a for a in argv if a != prompt and not a.startswith("[HƯỚNG")],
                            "env_keys": sorted(os.environ), "home": os.environ.get("HOME"), "cwd": os.getcwd(),
                            "env": {k: os.environ[k] for k in os.environ if k.startswith(("GROK_", "CLAUDE_"))}}) + "\n")

def has(tag): return tag in prompt
if has("@@SLOW"): time.sleep(30)
if has("@@GARBAGE"): print("this is not json at all"); sys.exit(0)

def refresh(path):
    if has("@@REFRESH") and os.path.isfile(path):
        open(path, "w").write('{"token":"NEW-TOKEN-AFTER-REFRESH"}')

if prov == "claude":
    cfg = os.environ.get("CLAUDE_CONFIG_DIR", "")
    refresh(os.path.join(cfg, ".credentials.json"))
    tools = ["mcp__claude_ai_Claude_Docs__guide"] if has("@@TOOLS") else []
    print(json.dumps({"type": "system", "subtype": "init", "tools": tools, "mcp_servers": [{"name": "claude.ai Claude Docs"}] if tools else [], "model": "claude-haiku-5-5"}))
    print(json.dumps({"type": "rate_limit_event", "rate_limit_info": {"status": "allowed", "unifiedWindows": {"five_hour": {"utilization": 0.39, "resetsAt": 1795088400}, "seven_day": {"utilization": 0.44, "resetsAt": 1795276800}}}}))
    if has("@@AUTH"):
        print(json.dumps({"type": "result", "subtype": "success", "is_error": True, "result": "Not logged in · Please run /login", "usage": {}, "modelUsage": {}}))
    elif has("@@LIMIT"):
        print(json.dumps({"type": "result", "subtype": "success", "is_error": True, "result": "You've hit your usage limit · resets 5pm", "usage": {}, "modelUsage": {}}))
    else:
        print(json.dumps({"type": "result", "subtype": "success", "is_error": False, "result": "xin chào", "total_cost_usd": 0.00064729,
                          "usage": {"input_tokens": 2, "output_tokens": 6, "cache_read_input_tokens": 649, "cache_creation_input_tokens": 3188},
                          "modelUsage": {"claude-haiku-5-5": {}}}))
elif prov == "grok":
    home = os.environ["GROK_HOME"]
    refresh(os.path.join(home, "auth.json"))
    sd = os.path.join(home, "sessions", "enc", "sess1"); os.makedirs(sd, exist_ok=True)
    json.dump(["read_file"] if has("@@TOOLS") else [], open(os.path.join(sd, "tool_definitions.json"), "w"))
    open(os.path.join(sd, "chat_history.jsonl"), "w").write("NOI-DUNG-HOI-THOAI")  # CLI thật lưu nội dung ra đĩa
    if has("@@AUTH"):
        print(json.dumps({"type": "error", "message": "Not signed in. To authenticate without a browser, run:\n  grok login --device-code"}))
    elif has("@@LIMIT"):
        print(json.dumps({"type": "error", "message": "Too many requests, usage limit reached"}))
    else:
        print(json.dumps({"text": "xin chào", "stopReason": "end_turn", "sessionId": "s1", "requestId": "r1",
                          "usage": {"input_tokens": 5050, "cache_read_input_tokens": 8832, "cache_creation_input_tokens": 0, "output_tokens": 296, "reasoning_tokens": 294, "total_tokens": 14178},
                          "total_cost_usd": 0.00553928, "modelUsage": {"grok-4.7-build": {}}}))
else:
    home = os.environ["HOME"]
    refresh(os.path.join(home, ".gemini", "antigravity-cli", "antigravity-oauth-token"))
    if has("@@AUTH"):
        print(json.dumps({"conversation_id": "", "status": "ERROR", "response": "", "error": "authentication failed or timed out", "finished_reason": "error"})); sys.exit(1)
    if has("@@DENIED"):
        print('jetski: no output produced — a tool required the "read_file" permission')
        print(json.dumps({"conversation_id": "c1", "status": "SUCCESS", "response": "", "usage": {"input_tokens": 9551, "output_tokens": 2591, "thinking_tokens": 2521, "cache_read_tokens": 0, "total_tokens": 12142}, "denied_actions": [{"action": "read_file", "display_name": "ReadFile"}]}))
    else:
        print(json.dumps({"conversation_id": "c1", "status": "SUCCESS", "response": "xin chào", "duration_seconds": 25.8, "num_turns": 1,
                          "usage": {"input_tokens": 9600, "output_tokens": 300, "thinking_tokens": 200, "cache_read_tokens": 8000, "total_tokens": 9900}}))
