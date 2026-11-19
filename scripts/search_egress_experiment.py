#!/usr/bin/env python3
"""Thí nghiệm: công cụ tìm web của Claude cần ra internet từ máy runner không?

Chạy Claude thật qua một proxy allowlist CHỈ có api.anthropic.com, thử ba cấu hình công cụ, rồi báo tên miền nào được/bị chặn.
Dùng tài khoản trong ~/.probe/claude (cần đăng nhập). Tốn vài lượt gọi nhỏ.
"""
import json
import os
import subprocess
import sys
import tempfile
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CFG = os.path.expanduser("~/.probe/claude")
PORT = "3129"
ASK = ('Dùng công cụ tìm web để tìm tài liệu THẬT về "huyết áp thấp là gì". Chỉ trả về một mảng JSON tối đa 2 phần tử, mỗi phần tử có '
       '"title", "url", "content" (200 ký tự chép từ kết quả). Không có thì trả [].')


def run(tools, tag, allow_extra=()):
    tmp = tempfile.mkdtemp()
    allow = os.path.join(tmp, "allow.txt")
    open(allow, "w").write("api.anthropic.com:443\n" + "".join(h + ":443\n" for h in allow_extra))
    log = open(os.path.join(tmp, "proxy.log"), "w")
    proxy = subprocess.Popen([sys.executable, os.path.join(ROOT, "egress-proxy", "proxy.py")], stdout=log, stderr=log,
                             env={"EGRESS_ALLOWLIST_FILE": allow, "EGRESS_PORT": PORT, "PATH": os.environ["PATH"]})
    time.sleep(1)
    env = {"PATH": os.environ["PATH"], "HOME": tmp, "CLAUDE_CONFIG_DIR": tmp + "/cfg", "HTTPS_PROXY": "http://127.0.0.1:" + PORT, "HTTP_PROXY": "http://127.0.0.1:" + PORT}
    os.makedirs(env["CLAUDE_CONFIG_DIR"])
    import shutil
    shutil.copy(os.path.join(CFG, ".credentials.json"), env["CLAUDE_CONFIG_DIR"])
    args = ["claude", "-p", "--model", "haiku", "--output-format", "stream-json", "--verbose", "--tools", tools, "--allowedTools", tools,
            "--strict-mcp-config", "--mcp-config", '{"mcpServers":{}}', "--setting-sources", "", "--no-session-persistence", "--disable-slash-commands"]
    t0 = time.time()
    p = subprocess.run(args, input=ASK.encode(), capture_output=True, env=env, timeout=240, cwd=tmp)
    proxy.terminate(); log.close()
    events = [json.loads(l) for l in p.stdout.decode().splitlines() if l.startswith("{")]
    used = [c.get("name") for e in events if e.get("type") == "assistant" for c in e.get("message", {}).get("content", []) if c.get("type") == "tool_use"]
    res = next((e.get("result", "") for e in reversed(events) if e.get("type") == "result"), "")
    n = 0
    try:
        import re
        n = len(json.loads(re.search(r"\[.*\]", res, re.S).group(0)))
    except Exception:
        n = -1
    hosts = {}
    for line in open(os.path.join(tmp, "proxy.log")):
        if "CHO_PHEP" in line or "TU_CHOI" in line:
            k = " ".join(line.split()[-3:])
            hosts[k] = hosts.get(k, 0) + 1
    print(f"\n=== {tag}  (công cụ: {tools})  {time.time()-t0:.0f}s")
    print("   công cụ mô hình đã gọi:", {u: used.count(u) for u in set(used)})
    print("   số kết quả JSON trong câu trả lời:", n, "| mẫu:", res[:160].replace("\n", " "))
    print("   tên miền qua proxy:", dict(sorted(hosts.items())))


if __name__ == "__main__":
    run("WebSearch", "A. CHỈ WebSearch, allowlist chỉ có api.anthropic.com")
    run("WebSearch,WebFetch", "B. WebSearch + WebFetch, allowlist chỉ có api.anthropic.com")
