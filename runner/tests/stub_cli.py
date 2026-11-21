#!/usr/bin/env python3
"""CLI GIẢ cho test: bắt chước đầu ra THẬT của claude (stream-json), grok (json) và agy (json) đã thu thập ở đợt thăm dò.
Hành vi chọn bằng dấu hiệu trong nội dung prompt: @@AUTH @@LIMIT @@TOOLS @@SLOW @@GARBAGE @@DENIED @@REFRESH.
Nếu có STUB_LOG: ghi lại đối số, tên biến môi trường, HOME, cwd (KHÔNG ghi nội dung prompt)."""
import json
import os
import sys
import time

argv = sys.argv[1:]
# ---- CHẾ ĐỘ ĐĂNG NHẬP (định dạng lấy từ CLI thật 21/11/2026) ---------------------------------------------------
if argv[:2] == ["auth", "login"]:                       # Claude: chờ mã dán ở stdin; mã sai thì báo và vẫn chờ
    cfgd = os.environ["CLAUDE_CONFIG_DIR"]
    if os.environ.get("STUB_LOG"):
        open(os.environ["STUB_LOG"], "a").write(json.dumps({"provider": "claude-login", "argv": argv, "env_keys": sorted(os.environ), "cwd": os.getcwd()}) + "\n")
    print("Opening browser to sign in…")
    print(os.environ.get("STUB_LOGIN_URL", "If the browser didn't open, visit: https://claude.com/cai/oauth/authorize?code=true&client_id=stub&response_type=code"))
    sys.stdout.write("Paste code here if prompted > "); sys.stdout.flush()
    for line in sys.stdin:
        code = line.strip()
        if code == "GOODCODE-123456":
            json.dump({"claudeAiOauth": {"accessToken": "NEW-ACCESS", "refreshToken": "NEW-REFRESH", "expiresAt": 4102444800000, "refreshTokenExpiresAt": 4105000000000}}, open(os.path.join(cfgd, ".credentials.json"), "w"))
            print("Login successful."); sys.exit(0)
        sys.stdout.write("Invalid code. Please make sure the full code was copied.\n"); sys.stdout.write("Paste code here if prompted > "); sys.stdout.flush()
    sys.exit(1)
if argv[:2] in (["login", "--device-auth"], ["login", "--device-code"]):     # Codex / Grok: in mã thiết bị rồi chờ người dùng xác nhận trên trang hãng
    codex = argv[1] == "--device-auth" or argv[1] == "--device-auth"
    home = os.environ.get("CODEX_HOME") or os.environ.get("GROK_HOME")
    if codex:
        print("Follow these steps to sign in with ChatGPT using device code authorization:\n\n1. Open this link in your browser and sign in to your account\n   https://auth.openai.com/codex/device\n\n2. Enter this one-time code (expires in 15 minutes)\n   ABCD-EFG12\n")
    else:
        print("To sign in, open this URL in your browser:\n\n  https://accounts.x.ai/oauth2/device?user_code=WXYZ-1234\n\nConfirm this code in your browser:\n\n  WXYZ-1234\n\nWaiting for authorization...")
    sys.stdout.flush()
    for _ in range(int(float(os.environ.get("STUB_DEVICE_WAIT", "6")) * 20)):
        if os.path.exists(os.environ.get("STUB_APPROVE", "/nonexistent")):
            json.dump({"tokens": {"access_token": "NEW-ACCESS", "refresh_token": "NEW-REFRESH"}}, open(os.path.join(home, "auth.json"), "w"))
            print("Signed in."); sys.exit(0)
        time.sleep(0.05)
    print("Error: device code expired"); sys.exit(1)

prov = "codex" if argv[:1] == ["exec"] else "claude" if "-p" in argv else "grok" if "--prompt-file" in argv else "agy"
if prov in ("claude", "codex"):
    prompt = sys.stdin.read()
elif prov == "grok":
    prompt = open(argv[argv.index("--prompt-file") + 1], encoding="utf-8").read()
else:
    prompt = argv[argv.index("--print") + 1]

if os.environ.get("STUB_LOG"):
    with open(os.environ["STUB_LOG"], "a") as f:
        f.write(json.dumps({"provider": prov, "argv": [a for a in argv if a != prompt and not a.startswith("[HƯỚNG")],
                            "env_keys": sorted(os.environ), "home": os.environ.get("HOME"), "cwd": os.getcwd(),
                            "env": {k: os.environ[k] for k in os.environ if k.startswith(("GROK_", "CLAUDE_", "CODEX_"))}}) + "\n")

def has(tag): return tag in prompt
if has("@@SLOW"): time.sleep(30)
if has("@@GARBAGE"): print("this is not json at all"); sys.exit(0)

def refresh(path):
    if has("@@REFRESH") and os.path.isfile(path):
        open(path, "w").write('{"token":"NEW-TOKEN-AFTER-REFRESH"}')

def search_rows():
    good = [{"title": "Huyết áp thấp là gì", "url": "https://www.vinmec.com/vi/bai-viet/a", "content": "Huyết áp thấp là dưới 90/60 mmHg. " * 6},
            {"title": "Triệu chứng", "url": "https://suckhoedoisong.vn/b", "content": "Chóng mặt, mệt mỏi."}]
    if has("@@EMPTY"): return []
    if has("@@MANY"): return [{"title": f"T{i}", "url": f"https://site{i}.example.org/p", "content": "c"} for i in range(12)]
    if has("@@DUP"): return [{"title": "A", "url": "https://Example.org/x/", "content": "1"}, {"title": "B", "url": "https://example.org/x", "content": "2"}] + good
    if has("@@DOM"): return [{"title": "n1", "url": "https://www.nhs.uk/a", "content": "1"}, {"title": "n2", "url": "https://vinmec.com/b", "content": "2"}, {"title": "n3", "url": "https://sub.cdc.gov/c", "content": "3"}]
    if has("@@CTRL"): return [{"title": "Tiêu\u202eđề\x00 lạ\n\n  nhiều   dòng", "url": "https://ok.example.org/p", "content": "nội\x07dung\u200b  ẩn"}]
    if has("@@BADURLS"):
        return [{"title": "x", "url": u, "content": "bad"} for u in ("javascript:alert(1)", "file:///etc/passwd", "http://169.254.169.254/latest/meta-data/", "http://user:pw@evil.example.org/x",
                "http://localhost/admin", "http://10.0.0.1/", "http://[::1]/", "https://good.example.org:8443/x", "ftp://x.example.org/a", "//evil.example.org/x", "https://intranet/x",
                "https://host.internal/x", "https://has space.example.org/x", "", "https://" + "a" * 2100 + ".example.org/x")] + [good[0]]
    return good


if prov == "claude":
    cfg = os.environ.get("CLAUDE_CONFIG_DIR", "")
    if os.environ.get("STUB_LOG"):   # token mà CLI thấy lúc bắt đầu (để test khóa làm mới)
        try: seen = open(os.path.join(cfg, ".credentials.json")).read()[:80]
        except OSError: seen = ""
        open(os.environ["STUB_LOG"] + ".seen", "a").write(json.dumps(seen) + "\n")
    if has("@@PAUSE"): time.sleep(0.8)
    refresh(os.path.join(cfg, ".credentials.json"))
    tools_arg = argv[argv.index("--tools") + 1]
    if tools_arg == "WebSearch":
        tl = ["WebSearch"] + (["WebFetch"] if has("@@FETCH") else [])
        print(json.dumps({"type": "system", "subtype": "init", "tools": tl, "mcp_servers": [], "model": "claude-haiku-5-5"}))
        print(json.dumps({"type": "rate_limit_event", "rate_limit_info": {"status": "allowed", "unifiedWindows": {"five_hour": {"utilization": 0.1, "resetsAt": 1795088400}}}}))
        if has("@@LIMIT"):
            print(json.dumps({"type": "result", "subtype": "success", "is_error": True, "result": "You've hit your usage limit · resets 5pm", "usage": {}, "modelUsage": {}}))
        else:
            body = "Kết quả:\n```json\n" + json.dumps(search_rows(), ensure_ascii=False) + "\n```" if not has("@@NOJSON") else "Xin lỗi, không tìm được."
            print(json.dumps({"type": "result", "subtype": "success", "is_error": False, "result": body, "total_cost_usd": 0.002,
                              "usage": {"input_tokens": 10, "output_tokens": 300, "cache_read_input_tokens": 100, "cache_creation_input_tokens": 50},
                              "modelUsage": {"claude-haiku-5-5": {}}}))
        sys.exit(0)
    tools = ["mcp__claude_ai_Claude_Docs__guide"] if has("@@TOOLS") else []
    print(json.dumps({"type": "system", "subtype": "init", "tools": tools, "mcp_servers": [{"name": "claude.ai Claude Docs"}] if tools else [], "model": "claude-haiku-5-5"}))
    print(json.dumps({"type": "rate_limit_event", "rate_limit_info": {"status": "allowed", "unifiedWindows": {"five_hour": {"utilization": 0.39, "resetsAt": 1795088400}, "seven_day": {"utilization": 0.44, "resetsAt": 1795276800}}}}))
    if has("@@AUTH"):
        print(json.dumps({"type": "result", "subtype": "success", "is_error": True, "result": "Not logged in · Please run /login", "usage": {}, "modelUsage": {}}))
    elif has("@@EXPIRED"):
        print(json.dumps({"type": "result", "subtype": "success", "is_error": True, "result": "OAuth token expired and refresh failed (re-login required)", "usage": {}, "modelUsage": {}}))
    elif has("@@LIMIT"):
        print(json.dumps({"type": "result", "subtype": "success", "is_error": True, "result": "You've hit your usage limit · resets 5pm", "usage": {}, "modelUsage": {}}))
    else:
        print(json.dumps({"type": "result", "subtype": "success", "is_error": False, "result": "xin chào", "total_cost_usd": 0.00064729,
                          "usage": {"input_tokens": 2, "output_tokens": 6, "cache_read_input_tokens": 649, "cache_creation_input_tokens": 3188},
                          "modelUsage": {"claude-haiku-5-5": {}}}))
elif prov == "codex":
    refresh(os.path.join(os.environ["CODEX_HOME"], "auth.json"))
    # Định dạng lấy từ lần chạy codex-cli 0.163.0 thật (20/11/2026). Câu chữ lỗi hết hạn mức CHƯA quan sát thật (đoán).
    print(json.dumps({"type": "thread.started", "thread_id": "t1"}))
    print(json.dumps({"type": "item.completed", "item": {"id": "item_0", "type": "error", "message": "Code Mode is unavailable because code-mode host is disabled."}}))   # cảnh báo THẬT, không phải dùng công cụ
    print(json.dumps({"type": "turn.started"}))
    if has("@@AUTH"):
        for i in (2, 3):
            print(json.dumps({"type": "error", "message": f"Reconnecting... {i}/5 (unexpected status 401 Unauthorized: Missing bearer or basic authentication in header, url: wss://api.openai.com/v1/responses)"}))
        sys.exit(1)
    if has("@@LIMIT"):
        print(json.dumps({"type": "error", "message": "You've hit your usage limit. Try again later."})); sys.exit(1)
    if has("@@TOOLS"):
        print(json.dumps({"type": "item.completed", "item": {"id": "item_0", "type": "command_execution", "command": "/bin/bash -lc id"}}))
    if has("@@NOTURN"):
        print(json.dumps({"type": "item.completed", "item": {"id": "item_1", "type": "agent_message", "text": "dang do"}})); sys.exit(1)
    print(json.dumps({"type": "item.completed", "item": {"id": "item_1", "type": "agent_message", "text": "xin chào"}}))
    print(json.dumps({"type": "turn.completed", "usage": {"input_tokens": 12773, "cached_input_tokens": 10880, "cache_write_input_tokens": 0, "output_tokens": 5, "reasoning_output_tokens": 0}}))
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
