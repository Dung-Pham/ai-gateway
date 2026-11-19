# Cổng AI — Danh sách lệnh thăm dò CLI (Giai đoạn 0)

Trạng thái: **chỉ là danh sách lệnh để bạn xem và duyệt. Chưa lệnh nào được chạy.** Ngày soạn: 19/11/2026.
Mục đích: biến các giả định G1–G7 trong [THIET_KE.md](THIET_KE.md) mục 2 thành "đúng / sai + bằng chứng".

Grok được bổ sung ở phần "Bổ sung cho Grok" (G0–G11) ngay trước mục 12; các cờ và biến môi trường của Grok lấy từ `grok --help` và tài liệu kèm theo CLI (`~/.grok/README.md`), cũng là **tài liệu, chưa phải hành vi đã thử**.

Cờ dòng lệnh trong tài liệu này đã được đối chiếu với `--help` của bản đang cài trên máy này (claude 2.1.308, codex-cli 0.163.0, gemini 0.64.0, grok 1.0.70). **Hành vi thực tế** (có `usage` không, có % không, thông báo khi chạm giới hạn…) thì chưa biết; đó chính là thứ cần thăm dò. Lệnh nào tôi chưa chắc định dạng đầu vào/đầu ra đều ghi "cần xác nhận".

## 0. Nguyên tắc an toàn khi chạy

1. **Chỉ dùng tài khoản thử** (một tài khoản mỗi hãng, ưu tiên tài khoản không quan trọng). Không dùng tài khoản đang code của bạn.
2. Mỗi tài khoản thử đăng nhập vào **thư mục riêng** (`~/.probe/...`), không đụng `~/.claude`, `~/.codex`, `~/.gemini` thật. **Đăng nhập do bạn tự làm** (tôi không xử lý đăng nhập).
3. Mọi prompt đều rất ngắn; tổng chi phí cả bộ thăm dò ước tính khoảng 30–40 lượt gọi nhỏ. Mục nào tốn hạn mức đáng kể có ghi rõ và **chỉ làm khi bạn đồng ý**.
4. Chạy trong **thư mục rỗng** (`$W`), không chạy trong thư mục dự án.
5. **Không** dùng cờ bỏ qua quyền (`--dangerously-skip-permissions`, `--yolo`, `--dangerously-bypass-approvals-and-sandbox`) trong bất kỳ lệnh nào.
6. Kết quả lưu ở `probe-results/` (đã nằm trong `.gitignore`). Có thể chứa email/tên tài khoản: **che trước khi gửi cho tôi** (xem mục 12).
7. Chạy từng mục một, xem kết quả rồi mới sang mục sau. Mục nào lỗi thì dừng và báo lại, không cố sửa bằng cách thêm cờ nguy hiểm.

## 1. Chuẩn bị (không tốn hạn mức)

```bash
cd ~/addp-autoseo/ai-gateway
export PROBE_HOME="$HOME/.probe"
export R="$PWD/probe-results/$(date +%F)"
mkdir -p "$PROBE_HOME"/{claude,codex,gemini-home,grok} "$R"
chmod 700 "$PROBE_HOME"
export W="$(mktemp -d)"
echo "Thư mục làm việc rỗng: $W"
```

Đăng nhập tài khoản thử (bạn tự làm, mỗi hãng một lần):

```bash
CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude auth login
```

```bash
CODEX_HOME="$PROBE_HOME/codex" codex login
```

```bash
HOME="$PROBE_HOME/gemini-home" gemini
```
```bash
GROK_HOME="$PROBE_HOME/grok" grok login --device-auth
```
(Grok: `--device-auth` là đăng nhập bằng mã thiết bị, dành cho máy không có trình duyệt, đúng trường hợp server. Biến `GROK_HOME` đổi thư mục cấu hình, mặc định `~/.grok`.)

(Gemini: chạy giao diện, đăng nhập theo hướng dẫn rồi thoát. Cần xác nhận Gemini CLI có đọc biến cấu hình riêng nào khác ngoài `HOME` không; dùng `HOME` riêng là cách chắc chắn tách được.)

## P0 — Môi trường và phiên bản (không tốn hạn mức)

```bash
{ claude --version; codex --version; gemini --version; docker --version; python3 --version; command -v jq /usr/bin/time; free -m | head -2; nproc; } 2>&1 | tee "$R/p0-env.txt"
```

```bash
CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude auth status 2>&1 | tee "$R/p0-claude-auth.txt"
```

```bash
CODEX_HOME="$PROBE_HOME/codex" codex login status 2>&1 | tee "$R/p0-codex-auth.txt"
```

Ghi lại: định dạng khi **đã đăng nhập**. Mục P10 sẽ ghi định dạng khi **chưa đăng nhập** (để nhận dạng `auth_expired`).

## P1 — Có thông tin token (`usage`) trong kết quả không? → G1 (tốn: 1 lượt nhỏ mỗi hãng)

Prompt đưa qua stdin (đúng cách gateway sẽ làm, không ghép vào đối số).

**Claude:**
```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: xin chào" | CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" /usr/bin/time -v -o "$R/p1-claude.time" claude -p --output-format json --tools "" --setting-sources "" --no-session-persistence --model haiku > "$R/p1-claude.json" 2> "$R/p1-claude.err"; echo "exit=$?"
```
```bash
jq 'keys' "$R/p1-claude.json"; jq '{usage, total_cost_usd, modelUsage, is_error, duration_ms}' "$R/p1-claude.json"
```

**Codex:** (nếu báo sai tên model thì bỏ `-m ...`; ghi chú trước đây cho thấy bridge dùng `gpt-6-luna`)
```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: xin chào" | CODEX_HOME="$PROBE_HOME/codex" /usr/bin/time -v -o "$R/p1-codex.time" codex exec --json -s read-only --ephemeral --ignore-user-config --ignore-rules --skip-git-repo-check -m gpt-6-luna - > "$R/p1-codex.jsonl" 2> "$R/p1-codex.err"; echo "exit=$?"
```
```bash
jq -c '.' "$R/p1-codex.jsonl" | head -20
```
Tìm sự kiện có số token (ví dụ có chứa `token`/`usage`).

**Gemini:**
```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: xin chào" | HOME="$PROBE_HOME/gemini-home" /usr/bin/time -v -o "$R/p1-gemini.time" gemini -p "Hãy làm theo nội dung được cung cấp qua stdin" -o json --approval-mode plan --skip-trust > "$R/p1-gemini.json" 2> "$R/p1-gemini.err"; echo "exit=$?"
```
```bash
jq 'keys' "$R/p1-gemini.json"; jq '.stats' "$R/p1-gemini.json"
```

Ghi lại cho mỗi hãng: có/không có số token vào/ra; có/không có chi phí quy đổi; tên trường chính xác; có tách theo model không.
**Kết luận G1:** đúng nếu cả ba có token vào/ra đọc được; nếu thiếu hãng nào thì hãng đó dùng ước lượng (gắn nhãn `estimated`).

## P2 — Đọc % hạn mức không tương tác được không? → G2

Hai hướng, **không tốn hoặc tốn rất ít**:

**(a) Tìm trong chính luồng sự kiện của mỗi lượt gọi (cần xác nhận định dạng):**
```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: ok" | CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude -p --output-format stream-json --verbose --tools "" --setting-sources "" --no-session-persistence --model haiku > "$R/p2-claude-stream.jsonl" 2>&1
grep -i -E 'rate|limit|quota|reset|utiliz|percent|remaining' "$R/p2-claude-stream.jsonl" | head -20
```

```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: ok" | CODEX_HOME="$PROBE_HOME/codex" codex exec --json -s read-only --ignore-user-config --ignore-rules --skip-git-repo-check - > "$R/p2-codex-run.jsonl" 2>&1
grep -i -E 'rate|limit|quota|reset|percent|remaining' "$R/p2-codex-run.jsonl" | head -20
```
Lần này **không** dùng `--ephemeral` để Codex ghi tệp phiên; xem thêm trong đó:
```bash
grep -r -i -E 'rate_limit|used_percent|resets' "$PROBE_HOME/codex/sessions" 2>/dev/null | head -10
```

```bash
cd "$W" && printf '%s' "ok" | HOME="$PROBE_HOME/gemini-home" gemini -p "Trả lời đúng một từ" -o stream-json --approval-mode plan --skip-trust > "$R/p2-gemini-stream.jsonl" 2>&1
grep -i -E 'rate|limit|quota|reset|percent|remaining' "$R/p2-gemini-stream.jsonl" | head -20
```

**(b) Lệnh/trang hiển thị mức dùng của CLI (xem trong giao diện, ghi lại có cách đọc bằng máy không):** mở từng CLI ở chế độ tương tác với tài khoản thử và dùng lệnh xem mức dùng/giới hạn của chính nó (ví dụ `/usage`, `/status`, `/stats`; tên lệnh chính xác tùy phiên bản, **cần xác nhận**). Ghi lại: hiển thị gì, có cờ nào xuất ra JSON không.

Ghi lại: có thấy `%`/giờ reset ở đâu không; thấy ở đâu (mỗi lượt gọi, hay chỉ lệnh riêng); giá trị là số nguyên hay thập phân; tốn hạn mức khi đọc không.
**Kết luận G2:** đúng nếu đọc được ổn định bằng máy; nếu chỉ có trong giao diện tương tác thì G2 coi là **sai** (chuyển sang đếm nội bộ + bắt 429).

## P3 — Chạm giới hạn thì báo thế nào? → G3

**Thụ động (không tốn thêm):** trong lúc dùng tài khoản thử, nếu gặp thông báo hết hạn mức thì chạy ngay lệnh gốc với `2>&1 | tee` và lưu toàn bộ đầu ra (kèm `echo $?`). Tôi cần: mã thoát, nội dung thông báo, định dạng JSON (nếu có), và giờ reset.

**Tham khảo có sẵn:** bộ nhận dạng đang dùng ở cầu nối cũ là `rate limit`, `usage limit`, `session limit`, `hit your`, `overloaded`, `429`, `quota`, `too many requests`, `limit reached`, `resets ` (xem `scripts/claude_bridge.py` trong repo addp-autoseo). Hãy đối chiếu với thông báo thật.

**Chủ động (TỐN HẠN MỨC, chỉ làm khi bạn đồng ý, chỉ trên tài khoản thử):** chạy vòng lặp prompt nhỏ cho tới khi gặp lỗi giới hạn. Tôi sẽ soạn lệnh riêng nếu bạn chọn làm; không đưa vào đây để khỏi chạy nhầm.

**Kết luận G3:** đúng nếu thông báo có dạng ổn định nhận dạng được và có giờ reset (hoặc ít nhất mã lỗi riêng).

## P4 — Ảnh làm đầu vào mà không cần công cụ đọc tệp? → G4 (tốn: 1 lượt nhỏ mỗi hãng)

Tạo ảnh thử 64×64 màu đỏ bằng Python chuẩn (không cần thư viện):
```bash
python3 - "$W/red.png" <<'EOF'
import struct, sys, zlib
w = h = 64
raw = b"".join(b"\x00" + b"\xff\x00\x00" * w for _ in range(h))
def chunk(t, d): return struct.pack(">I", len(d)) + t + d + struct.pack(">I", zlib.crc32(t + d) & 0xffffffff)
png = b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", w, h, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(raw)) + chunk(b"IEND", b"")
open(sys.argv[1], "wb").write(png)
EOF
ls -l "$W/red.png"
```

**Claude** — đầu vào dạng luồng (ảnh nhúng base64, **không** gửi đường dẫn tệp; định dạng thông điệp cần xác nhận, nếu lỗi hãy gửi lại thông báo lỗi cho tôi):
```bash
cd "$W" && python3 - <<'EOF' > "$W/in.jsonl"
import base64, json
b = base64.b64encode(open("red.png", "rb").read()).decode()
msg = {"type": "user", "message": {"role": "user", "content": [
    {"type": "image", "source": {"type": "base64", "media_type": "image/png", "data": b}},
    {"type": "text", "text": "Màu chính của ảnh là gì? Trả lời đúng một từ."}]}}
print(json.dumps(msg))
EOF
CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude -p --input-format stream-json --output-format stream-json --verbose --tools "" --setting-sources "" --no-session-persistence --model haiku < "$W/in.jsonl" > "$R/p4-claude.jsonl" 2> "$R/p4-claude.err"; echo "exit=$?"
grep -o '"result":"[^"]*"' "$R/p4-claude.jsonl" | tail -1
```

**Codex** (`-i` đính kèm ảnh; chạy sandbox chỉ đọc):
```bash
cd "$W" && printf '%s' "Màu chính của ảnh là gì? Trả lời đúng một từ." | CODEX_HOME="$PROBE_HOME/codex" codex exec --json -s read-only --ephemeral --ignore-user-config --ignore-rules --skip-git-repo-check -i "$W/red.png" - > "$R/p4-codex.jsonl" 2> "$R/p4-codex.err"; echo "exit=$?"
```

**Gemini** (tham chiếu tệp bằng `@`; thường cần quyền đọc tệp, đây là điều cần kiểm tra ở chế độ chỉ đọc `plan`):
```bash
cd "$W" && HOME="$PROBE_HOME/gemini-home" gemini -p "@red.png Màu chính của ảnh là gì? Trả lời đúng một từ." -o json --approval-mode plan --skip-trust > "$R/p4-gemini.json" 2> "$R/p4-gemini.err"; echo "exit=$?"
```

Ghi lại: trả lời đúng ("đỏ") hay không; hãng nào cần đọc tệp theo đường dẫn (nếu có thì ảnh phải đặt vào thư mục tạm của container và công cụ đọc phải bật có kiểm soát: ghi rõ để thiết kế lại); token ảnh được tính thế nào.
**Kết luận G4:** đúng cho hãng nào nhận ảnh nhúng hoặc đính kèm mà không cần công cụ đọc tệp bật rộng.

## P5 — Sinh ảnh qua CLI có không? → G5 (tốn: 1 lượt nhỏ mỗi hãng)

```bash
cd "$W" && printf '%s' "Hãy tạo một ảnh hình vuông màu xanh dương đơn giản và trả về ảnh." | CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude -p --output-format json --tools "" --setting-sources "" --no-session-persistence --model haiku | tee "$R/p5-claude.json" | jq -r '.result' | head -c 600
```

```bash
cd "$W" && printf '%s' "Hãy tạo một ảnh hình vuông màu xanh dương đơn giản và trả về ảnh." | CODEX_HOME="$PROBE_HOME/codex" codex exec --json -s read-only --ephemeral --ignore-user-config --ignore-rules --skip-git-repo-check - > "$R/p5-codex.jsonl" 2>&1; head -c 1500 "$R/p5-codex.jsonl"; ls -la "$W"
```

```bash
cd "$W" && HOME="$PROBE_HOME/gemini-home" gemini -p "Hãy tạo một ảnh hình vuông màu xanh dương đơn giản và trả về ảnh." -o json --approval-mode plan --skip-trust > "$R/p5-gemini.json" 2>&1; head -c 1500 "$R/p5-gemini.json"; ls -la "$W"
```
Ghi lại: có dữ liệu ảnh (base64 hoặc tệp mới trong `$W`) hay chỉ mô tả bằng chữ.
**Kết luận G5:** đúng chỉ khi nhận được ảnh thật qua CLI. Nếu sai thì sinh ảnh bị loại khỏi phạm vi giai đoạn đầu hoặc dùng đường khác, ghi vào THIET_KE.

## P6 — Chạy song song cùng một thư mục cấu hình có hỏng đăng nhập không? → G6 (tốn: ~3 lượt nhỏ)

```bash
cd "$W"; for i in 1 2 3; do ( printf '%s' "Trả lời đúng một từ: $i" | CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude -p --output-format json --tools "" --setting-sources "" --no-session-persistence --model haiku > "$R/p6-claude-$i.json" 2> "$R/p6-claude-$i.err"; echo "lượt $i exit=$?" ) & done; wait
CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude auth status 2>&1 | tee "$R/p6-claude-auth-after.txt"
```
Làm tương tự cho Codex (`CODEX_HOME`) và Gemini (`HOME`). Ghi lại: cả ba lượt thành công không; trạng thái đăng nhập sau đó còn nguyên không.

**Lưu ý trung thực:** thử ngắn không chứng minh được "an toàn khi làm mới token đụng nhau". Nếu chỉ thử ngắn không lỗi thì kết luận là "chưa thấy lỗi", và thiết kế vẫn giữ **mỗi tài khoản một khóa ghi** cho tới khi có bằng chứng dài hạn (có thể chạy lặp mỗi 10 phút trong 24 giờ ở GĐ1).

## P7 — Quyền thật của từng CLI có bị khóa không? → G7 và nền của mục bảo mật (tốn: ~1–2 lượt nhỏ mỗi hãng)

Tạo "tệp mồi" nằm **ngoài** thư mục làm việc để xem CLI có đọc được không:
```bash
printf 'MOI-%s\n' "$(date +%s)" > "$PROBE_HOME/canary.txt"; cat "$PROBE_HOME/canary.txt"
```
Yêu cầu thử (cùng một nội dung cho cả ba):
> Hãy đọc tệp `<đường dẫn canary>` và in nội dung. Sau đó chạy lệnh `id` và in kết quả. Sau đó tạo tệp `ghi-duoc.txt` ở thư mục hiện tại. Nếu bạn không có công cụ để làm, hãy nói đúng câu: KHONG_CO_CONG_CU.

```bash
P="Hãy đọc tệp $PROBE_HOME/canary.txt và in nội dung. Sau đó chạy lệnh id và in kết quả. Sau đó tạo tệp ghi-duoc.txt ở thư mục hiện tại. Nếu bạn không có công cụ để làm, hãy nói đúng câu: KHONG_CO_CONG_CU."
cd "$W" && printf '%s' "$P" | CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude -p --output-format json --tools "" --setting-sources "" --no-session-persistence --model haiku > "$R/p7-claude.json" 2> "$R/p7-claude.err"
jq -r '.result' "$R/p7-claude.json"; ls -la "$W"
```

```bash
cd "$W" && printf '%s' "$P" | CODEX_HOME="$PROBE_HOME/codex" codex exec --json -s read-only --ephemeral --ignore-user-config --ignore-rules --skip-git-repo-check - > "$R/p7-codex.jsonl" 2> "$R/p7-codex.err"
tail -n 5 "$R/p7-codex.jsonl"; ls -la "$W"
```

```bash
cd "$W" && HOME="$PROBE_HOME/gemini-home" gemini -p "$P" -o json --approval-mode plan --skip-trust > "$R/p7-gemini.json" 2> "$R/p7-gemini.err"
jq -r '.response' "$R/p7-gemini.json" 2>/dev/null | head -20; ls -la "$W"
```

Ghi lại cho mỗi hãng, 3 câu hỏi có/không:
1. Nội dung `canary.txt` **có xuất hiện** trong đầu ra không (đọc được tệp ngoài thư mục làm việc)?
2. Lệnh `id` **có chạy** không (có kết quả uid=…)?
3. Tệp `ghi-duoc.txt` **có được tạo** trong `$W` không?

**Cách đọc kết quả:** câu nào trả lời "có" nghĩa là chế độ đó **chưa đủ** để dựa vào; hãng đó phải được cách ly hoàn toàn ở tầng container (hệ tệp rỗng, chỉ đọc, không mạng ngoài allowlist) và không được tin vào cờ CLI. Với bất kỳ hãng nào làm được cả ba điều, loại khỏi môi trường chạy cho tới khi sửa cấu hình.

## P8 — Tài nguyên mỗi lượt gọi (không tốn thêm: đọc từ các tệp `.time` của P1)

```bash
for f in "$R"/p1-*.time; do echo "== $f"; grep -E "Elapsed|Maximum resident" "$f"; done
```
Ghi lại: thời gian chạy và RAM đỉnh (KB) mỗi hãng. Từ số này tính số lượt song song tối đa trên server. Lặp lại 3 lần và lấy trung vị nếu muốn số đáng tin hơn.

## P9 — CLI cần ra những tên miền nào? → allowlist cho proxy

Cửa sổ 1 (bộ ghi tên miền, chỉ nghe 127.0.0.1, chỉ ghi `host:port`, không ghi nội dung):
```bash
python3 probes/connect_logger.py --port 8899 --log "$R/p9-hosts.txt"
```

Cửa sổ 2 (chạy từng hãng qua proxy này rồi xem tên miền):
```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: ok" | HTTPS_PROXY=http://127.0.0.1:8899 HTTP_PROXY=http://127.0.0.1:8899 CLAUDE_CONFIG_DIR="$PROBE_HOME/claude" claude -p --output-format json --tools "" --setting-sources "" --no-session-persistence --model haiku > /dev/null; echo "exit=$?"
```
```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: ok" | HTTPS_PROXY=http://127.0.0.1:8899 HTTP_PROXY=http://127.0.0.1:8899 CODEX_HOME="$PROBE_HOME/codex" codex exec --json -s read-only --ephemeral --ignore-user-config --ignore-rules --skip-git-repo-check - > /dev/null; echo "exit=$?"
```
```bash
cd "$W" && HTTPS_PROXY=http://127.0.0.1:8899 HTTP_PROXY=http://127.0.0.1:8899 HOME="$PROBE_HOME/gemini-home" gemini -p "Trả lời đúng một từ: ok" -o json --approval-mode plan --skip-trust > /dev/null; echo "exit=$?"
```
```bash
sort -u -k2 "$R/p9-hosts.txt" | awk '{print $2, $3}' | sort -u
```
Ghi lại: danh sách `host:port` theo từng hãng, **phân loại**: (1) bắt buộc để trả lời, (2) đăng nhập/làm mới token, (3) đo đạc/telemetry (nên chặn nếu CLI vẫn chạy được), (4) khác.
**Nếu `p9-hosts.txt` rỗng** thì CLI đó bỏ qua biến proxy; báo lại để tôi đổi cách đo (ví dụ mạng Docker có nhật ký).
Kết quả này thành danh sách cho phép chính thức của proxy; không điền tên miền theo trí nhớ.

## P10 — Chưa đăng nhập / hết phiên trông thế nào? (không tốn hạn mức) → để nhận dạng `auth_expired`

```bash
mkdir -p "$PROBE_HOME/empty-claude" "$PROBE_HOME/empty-codex" "$PROBE_HOME/empty-gemini-home"
cd "$W" && printf '%s' "ok" | CLAUDE_CONFIG_DIR="$PROBE_HOME/empty-claude" claude -p --output-format json --tools "" --setting-sources "" --no-session-persistence --model haiku > "$R/p10-claude.out" 2> "$R/p10-claude.err"; echo "claude exit=$?"
```
```bash
cd "$W" && printf '%s' "ok" | CODEX_HOME="$PROBE_HOME/empty-codex" codex exec --json -s read-only --ephemeral --ignore-user-config --ignore-rules --skip-git-repo-check - > "$R/p10-codex.out" 2> "$R/p10-codex.err"; echo "codex exit=$?"
```
```bash
cd "$W" && HOME="$PROBE_HOME/empty-gemini-home" gemini -p "ok" -o json --approval-mode plan --skip-trust > "$R/p10-gemini.out" 2> "$R/p10-gemini.err"; echo "gemini exit=$?"
```
Ghi lại: mã thoát, nội dung thông báo, ra ở stdout hay stderr, có JSON không. Đây là chữ ký để gateway phân biệt "hết phiên" với "chạm giới hạn" và "lỗi mạng". Nếu CLI hỏi tương tác (treo chờ nhập) thì ghi rõ: gateway **phải** chạy với đầu vào đóng và timeout cứng.

## P11 — Thử LiteLLM (làm sau khi xong P1–P10, không bắt buộc ngay)

Chỉ đọc tài liệu và chạy thử một bản tách riêng trên máy thử để trả lời: (1) khóa ảo theo ứng dụng, (2) giới hạn rpm/tpm/ngân sách theo khóa, (3) nhiều "deployment" cho cùng một model (cân bằng tải, cooldown khi lỗi), (4) nhật ký chi phí/token tùy biến cho model tự đặt giá, (5) giao diện quản trị dùng được không, (6) có thể dùng cầu nối cũ làm upstream không. Tôi sẽ soạn lệnh cụ thể khi tới bước này; chưa soạn để tránh đoán phiên bản.

## Bổ sung cho Grok (G0–G11)

Grok CLI ("Grok Build") chạy không tương tác bằng `--prompt-file`/`-p` kèm `--output-format json`. Theo tài liệu của chính nó, kết quả JSON có dạng `{"text","stopReason","sessionId","requestId"}`; **chưa biết** có số token trong đó không. Có lệnh `grok usage <SESSION_ID>` in "token và chi phí đã lưu" của một phiên, có thể là nguồn số liệu thay thế cho G1.

Tất cả lệnh dưới đây đặt `GROK_HOME` riêng và tắt các tính năng không cần: `GROK_MEMORY=0` (trí nhớ giữa các phiên, tránh rò dữ liệu giữa ứng dụng), `GROK_SUBAGENTS=0`, `GROK_WEB_FETCH=0`, `GROK_TELEMETRY_ENABLED=0`. Đặt gọn bằng biến:

```bash
export GK_ENV="GROK_HOME=$PROBE_HOME/grok GROK_MEMORY=0 GROK_SUBAGENTS=0 GROK_WEB_FETCH=0 GROK_TELEMETRY_ENABLED=0"
```

**G0 — Môi trường (không tốn hạn mức):**
```bash
{ grok --version; env $GK_ENV grok models; env $GK_ENV grok du; } 2>&1 | tee "$R/g0-grok.txt"
```
`grok models` cho biết đã đăng nhập chưa (không có lệnh "auth status" riêng; **cần xác nhận**) và danh sách model dùng được. Ghi lại tên model sẽ dùng cho gateway.

**G1 — Có token/chi phí không? → G1 của thiết kế (tốn: 1 lượt nhỏ):**
```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: xin chào" > "$W/p.txt" && env $GK_ENV /usr/bin/time -v -o "$R/g1-grok.time" grok --prompt-file "$W/p.txt" --output-format json --tools "" --no-subagents --disable-web-search --max-turns 1 --cwd "$W" > "$R/g1-grok.json" 2> "$R/g1-grok.err"; echo "exit=$?"
```
(Prompt nằm trong tệp tạm vì chưa biết CLI có đọc stdin không; gateway thật sẽ tạo tệp tạm riêng cho mỗi yêu cầu rồi xóa. Nếu `--tools ""` báo lỗi, ghi lại thông báo và thử bỏ cờ này, rồi dựa vào G7 để biết quyền thật.)
```bash
jq '.' "$R/g1-grok.json"
SID=$(jq -r '.sessionId' "$R/g1-grok.json"); echo "phiên: $SID"
env $GK_ENV grok usage "$SID" 2>&1 | tee "$R/g1-grok-usage.txt"
```
Ghi lại: JSON có token không; `grok usage` có token vào/ra và chi phí không; chi phí tính theo đơn vị gì.

**G2 — Đọc % hạn mức? → G2 (không tốn hoặc rất ít):**
```bash
cd "$W" && env $GK_ENV grok --prompt-file "$W/p.txt" --output-format streaming-json --tools "" --no-subagents --disable-web-search --max-turns 1 --cwd "$W" > "$R/g2-grok-stream.jsonl" 2>&1
grep -i -E 'rate|limit|quota|reset|percent|remaining|usage' "$R/g2-grok-stream.jsonl" | head -20
```
Ngoài ra mở `grok` ở chế độ tương tác với tài khoản thử, xem có lệnh nào hiện hạn mức/mức dùng không (tên lệnh **cần xác nhận**), ghi lại có cách đọc bằng máy không.

**G3 — Chạm giới hạn thì báo thế nào?** Thu thập thụ động như mục P3: lưu nguyên đầu ra và mã thoát khi gặp thông báo hết hạn mức.

**G4 — Ảnh làm đầu vào? → G4 (tốn: 1 lượt nhỏ):** dùng lại ảnh `red.png` đã tạo ở P4. `--prompt-json` nhận "khối nội dung dạng JSON"; **định dạng khối chưa được tài liệu mô tả**, dưới đây là dạng thường gặp (cần xác nhận, nếu lỗi hãy gửi lại thông báo lỗi cho tôi):
```bash
cd "$W" && python3 - <<'EOF' > "$W/g4.json"
import base64, json
b = base64.b64encode(open("red.png", "rb").read()).decode()
print(json.dumps([{"type": "image", "data": b, "mimeType": "image/png"}, {"type": "text", "text": "Màu chính của ảnh là gì? Trả lời đúng một từ."}]))
EOF
env $GK_ENV grok --prompt-json "$(cat "$W/g4.json")" --output-format json --tools "" --no-subagents --disable-web-search --max-turns 1 --cwd "$W" > "$R/g4-grok.json" 2> "$R/g4-grok.err"; echo "exit=$?"; jq -r '.text' "$R/g4-grok.json"
```
Ảnh nhúng nằm trong đối số dòng lệnh nên **chỉ dùng cho thăm dò**; nếu đây là cách duy nhất, thiết kế phải đổi (ví dụ đưa qua tệp) và ghi lại giới hạn kích thước đối số.

**G5 — Sinh ảnh qua CLI?** (tốn: 1 lượt nhỏ)
```bash
cd "$W" && printf '%s' "Hãy tạo một ảnh hình vuông màu xanh dương đơn giản và trả về ảnh." > "$W/p5.txt" && env $GK_ENV grok --prompt-file "$W/p5.txt" --output-format json --tools "" --no-subagents --disable-web-search --max-turns 1 --cwd "$W" > "$R/g5-grok.json" 2>&1; head -c 1200 "$R/g5-grok.json"; ls -la "$W"
```

**G6 — Song song cùng `GROK_HOME`? (tốn ~3 lượt nhỏ):** theo tài liệu, tiến trình Grok có thể dùng một "leader" chung qua ổ cắm `~/.grok/leader.sock` (mặc định, nằm trong `GROK_HOME` nếu biến này đổi chỗ; **cần xác nhận**), nên cần xem chạy song song có gom về một tiến trình không.
```bash
cd "$W"; for i in 1 2 3; do ( printf '%s' "Trả lời đúng một từ: $i" > "$W/pp$i.txt"; env $GK_ENV grok --prompt-file "$W/pp$i.txt" --output-format json --tools "" --no-subagents --disable-web-search --max-turns 1 --cwd "$W" > "$R/g6-grok-$i.json" 2> "$R/g6-grok-$i.err"; echo "lượt $i exit=$?" ) & done; wait
env $GK_ENV grok leader --help 2>&1 | head -12; ls -la "$PROBE_HOME/grok" | head -20
```
Ghi lại: cả ba lượt thành công không; có tiến trình `grok` nào còn sống sau khi xong không (`pgrep -a grok`); có tệp `leader.sock` không.

**G7 — Quyền thật có bị khóa không? → G7 (tốn: 3 lượt nhỏ):** cùng tệp mồi `canary.txt` và câu lệnh `$P` như mục P7, chạy ba chế độ để so sánh:
```bash
printf '%s' "$P" > "$W/p7.txt"
for MODE in "--tools ''" "--sandbox read-only" "--sandbox strict"; do
  echo "=== chế độ: $MODE"; cd "$W" && eval env $GK_ENV grok --prompt-file "$W/p7.txt" --output-format json --no-subagents --disable-web-search --max-turns 3 --cwd "$W" $MODE 2>&1 | tee "$R/g7-grok-$(echo $MODE | tr -c 'a-z\n' '_').json" | jq -r '.text' 2>/dev/null | head -8; ls -la "$W" | grep -c ghi-duoc
done
```
Ghi lại cho từng chế độ 3 câu có/không như P7: đọc được canary, lệnh `id` chạy, tạo được `ghi-duoc.txt`. Tài liệu ghi chú các hồ sơ sandbox chỉ chặn mạng **một phần**, nên **không** dựa vào cờ này để bảo mật; chỉ dựa vào container.

**G8 — Tài nguyên:**
```bash
grep -E "Elapsed|Maximum resident" "$R/g1-grok.time"
```

**G9 — Tên miền cần ra ngoài?** Chạy bộ ghi tên miền như P9 (cửa sổ 1), rồi:
```bash
cd "$W" && env $GK_ENV HTTPS_PROXY=http://127.0.0.1:8899 HTTP_PROXY=http://127.0.0.1:8899 grok --prompt-file "$W/p.txt" --output-format json --tools "" --no-subagents --disable-web-search --max-turns 1 --cwd "$W" > /dev/null; echo "exit=$?"
```
Phân loại tên miền như P9. Có biến chuyển tiếp riêng cho tìm web (`GROK_WEB_FETCH_PROXY`), ghi lại nếu bật tìm web sau này.

**G10 — Chưa đăng nhập trông thế nào? (không tốn hạn mức)** dùng `timeout` và đóng đầu vào để không bị treo chờ nhập:
```bash
mkdir -p "$PROBE_HOME/empty-grok"; cd "$W" && timeout 30 env GROK_HOME="$PROBE_HOME/empty-grok" GROK_TELEMETRY_ENABLED=0 grok --prompt-file "$W/p.txt" --output-format json --tools "" --max-turns 1 --cwd "$W" < /dev/null > "$R/g10-grok.out" 2> "$R/g10-grok.err"; echo "grok exit=$?"
```
Ghi lại: mã thoát (124 nghĩa là bị `timeout` cắt, tức CLI treo chờ nhập: gateway **phải** có timeout cứng), thông báo, stdout hay stderr.

**G11 — Nội dung hội thoại có bị lưu ra đĩa không? → ảnh hưởng nguyên tắc "không ghi nội dung":**
```bash
grep -r -l "xin chào" "$PROBE_HOME/grok/sessions" 2>/dev/null | head -3
ls "$PROBE_HOME/grok/sessions" 2>/dev/null | head; env $GK_ENV grok du 2>&1 | head -8
grok --help 2>&1 | grep -i -E "persist|ephemeral|incognito|no-session|no-save" | head
```
Theo tài liệu, Grok **tự lưu** mỗi hội thoại vào `$GROK_HOME/sessions/<thư mục làm việc>/<phiên>/` (có `chat_history.jsonl`), và tôi chưa thấy cờ nào tắt việc này (Claude có `--no-session-persistence`, Codex có `--ephemeral`). Nếu đúng, gateway phải: chạy mỗi yêu cầu trong **thư mục làm việc riêng**, rồi **xóa đúng thư mục phiên của yêu cầu đó** ngay sau khi xong, và quét dọn định kỳ. Ghi lại: có tìm thấy nội dung câu hỏi trong tệp không; có cờ tắt lưu không.

**Điều cần chú ý riêng cho Grok khi vận hành:** tài liệu của CLI ghi token đăng nhập hết hạn sau 7 ngày (có mục "làm mới thông tin xác thực tự động"). **Chưa biết** việc làm mới có tự chạy ở chế độ không tương tác không. Nếu phải đăng nhập lại mỗi tuần thì cảnh báo hết phiên của gateway là tính năng bắt buộc; ghi nhận hành vi thật bằng cách theo dõi `auth.json` (không đọc nội dung) qua vài ngày: `stat "$PROBE_HOME/grok/auth.json"`.

## Bổ sung cho Antigravity `agy` (thay Gemini CLI) (AG0–AG6)

Lý do: Gemini CLI báo không còn hỗ trợ đăng nhập cá nhân và chỉ sang Antigravity (xem KET_QUA_THAM_DO). Cờ lệnh dưới đây lấy từ `agy --help` (1.5.12); **hành vi chưa thử**. Dùng `HOME` riêng, **không** dùng `~/.gemini` thật của bạn.

```bash
mkdir -p "$HOME/.probe/agy-home" && export AGY_ENV="HOME=$HOME/.probe/agy-home"
```

**AG0 — Đăng nhập (bạn tự làm, trong Terminal hệ thống):**
```bash
env HOME="$HOME/.probe/agy-home" agy
```
Cách đăng nhập (trình duyệt hay mã) và nơi lưu token **chưa biết**; ghi lại các bước và tệp mới sinh ra (chỉ tên/kích thước), như đã làm với Gemini.

**AG1 — Token/chi phí có trong JSON? (tốn 1 lượt nhỏ):**
```bash
cd "$W" && printf '%s' "Trả lời đúng một từ: xin chào" | env $AGY_ENV /usr/bin/time -v -o "$R/ag1-agy.time" agy --print --output-format json --mode plan --sandbox --print-timeout 60s > "$R/ag1-agy.json" 2> "$R/ag1-agy.err"; echo "exit=$?"; jq 'keys' "$R/ag1-agy.json" 2>&1 | head
```
(Chưa biết `agy -p` có đọc prompt từ stdin không; nếu không, đưa prompt làm đối số cho thăm dò và ghi lại giới hạn.)

**AG2 — Chưa đăng nhập trông thế nào (không tốn hạn mức):** dùng thư mục rỗng và `timeout`:
```bash
mkdir -p "$PROBE_HOME/empty-agy" && cd "$W" && timeout 40 env HOME="$PROBE_HOME/empty-agy" agy --print --output-format json --print-timeout 20s "ok" < /dev/null > "$R/ag2-agy.out" 2> "$R/ag2-agy.err"; echo "exit=$?"
```

**AG3 — Quyền thật có bị khóa không (tệp mồi `canary.txt`, 3 câu có/không như P7):** chạy với `--sandbox --mode plan` và với `--mode plan` không `--sandbox`; **cấm** `--dangerously-skip-permissions`.

**AG4 — Ảnh vào/ra, tên miền, song song cùng `HOME`, tài nguyên (RAM, 280 MB tệp thực thi), lưu hội thoại ra đĩa (`~/.gemini/antigravity-cli/{conversations,history.jsonl,brain}` thấy trên máy bạn):** lặp P4, P5, P6, P8, P9, G11 cho `agy`. Nơi ghi `cli.log` và `history.jsonl` **có thể chứa nội dung prompt**; kiểm bằng chuỗi dấu hiệu độc nhất.

**AG5 — Tự cập nhật:** ghi lại `agy update`/cơ chế tự cập nhật, tên miền dịch vụ cập nhật, và có tắt được bằng cờ/biến môi trường/`settings.json` không.

**AG6 — Nhiều tài khoản trên một máy:** thử hai `HOME` khác nhau có giữ hai đăng nhập tách biệt không (Gemini CLI ở chế độ keychain thì không).

## 12. Gửi kết quả cho tôi

Che thông tin cá nhân trước:
```bash
cd "$R" && for f in *.json *.jsonl *.txt *.err *.out *.time; do [ -f "$f" ] && sed -E -i 's/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/<EMAIL>/g; s/(sk|key|token|Bearer)[-_ :=]+[A-Za-z0-9._-]{12,}/\1=<ĐÃ_CHE>/gi' "$f"; done; ls -la
```
Sau đó dán nội dung (hoặc cho tôi đọc thư mục `probe-results/<ngày>/`) và điền bảng dưới đây:

| Giả định | Mục | Kết quả (Đúng / Sai / Chưa rõ) | Bằng chứng (tên tệp) | Ghi chú |
|---|---|---|---|---|
| G1 có `usage` | P1, G1 | | | Claude: / Codex: / Gemini: / Grok: (JSON và `grok usage`) |
| G2 đọc % hạn mức bằng máy | P2, G2 | | | |
| G3 thông báo chạm giới hạn nhận dạng được | P3 | | | |
| G4 ảnh vào không cần công cụ đọc tệp | P4, G4 | | | |
| G5 sinh ảnh qua CLI | P5, G5 | | | |
| G6 song song cùng thư mục cấu hình | P6, G6 | | | Grok: tiến trình leader? |
| G7 cờ chỉ-đọc có tác dụng | P7, G7 | | | Đọc tệp ngoài / chạy lệnh / ghi tệp (Grok: 3 chế độ) |
| RAM, thời gian mỗi lượt | P8, G8 | | | |
| Allowlist tên miền | P9, G9 | | | |
| Chữ ký `auth_expired` | P10, G10 | | | |
| Grok lưu nội dung hội thoại ra đĩa | G11 | | | Cần xóa sau mỗi yêu cầu? |
| Grok: token hết hạn 7 ngày, tự làm mới không | G11 (theo dõi vài ngày) | | | |

Theo AGENTS.md (trung thực về bằng chứng): mục nào chưa chạy ghi **"Chưa chạy"**, mục nào bị chặn (thiếu tài khoản, lỗi môi trường) ghi **"Bị chặn tích hợp"**; không điền giá trị giả. Sau khi có bảng này tôi sẽ cập nhật THIET_KE thành v1.1 và chốt phạm vi từng khả năng.

## 13. Dọn dẹp sau khi thăm dò

```bash
rm -rf "$W"
```
Giữ lại `~/.probe` nếu bạn muốn dùng tiếp làm tài khoản thử; nếu không thì đăng xuất từng CLI (`claude auth logout`, `codex logout`, `grok logout`) với đúng biến môi trường thư mục tương ứng rồi xóa thư mục. **Không xóa gì ngoài `~/.probe` và `$W`.**
