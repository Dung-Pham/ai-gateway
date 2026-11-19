#!/usr/bin/env python3
"""Sinh contracts/control_schema.sql từ CSDL SQLite đã migrate của control/ và so với tệp hiện có (phát hiện lệch).

  python3 scripts/dump_contract.py           # ghi đè tệp hợp đồng
  python3 scripts/dump_contract.py --check   # chỉ kiểm tra, thoát mã 1 nếu lệch
"""
import sqlite3, sys, os

TABLES = ["providers", "accounts", "pools", "pool_members", "capabilities", "apps", "app_keys", "app_policies",
          "app_limits", "usage_events", "model_weights", "settings"]
HEADER = ["-- HỢP ĐỒNG DỮ LIỆU giữa giao diện điều khiển (Laravel, sở hữu migration) và gateway (Python).",
          "-- Sinh bằng scripts/dump_contract.py từ CSDL đã migrate của control/. KHÔNG sửa tay: sửa migration rồi sinh lại.",
          "-- Gateway chỉ ĐỌC các bảng chính sách; chỉ GHI usage_events (INSERT) và app_keys.last_used_at.", ""]


def build(db_path: str) -> str:
    db = sqlite3.connect(db_path)
    out = list(HEADER)
    for t in TABLES:
        r = db.execute("select sql from sqlite_master where type='table' and name=?", (t,)).fetchone()
        if not r:
            raise SystemExit(f"Thiếu bảng {t} trong {db_path}: đã chạy migrate chưa?")
        out.append(r[0] + ";")
        for (sql,) in db.execute("select sql from sqlite_master where type='index' and tbl_name=? and sql is not null order by name", (t,)):
            out.append(sql + ";")
        out.append("")
    return "\n".join(out)


if __name__ == "__main__":
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    new = build(os.path.join(root, "control/database/database.sqlite"))
    path = os.path.join(root, "contracts/control_schema.sql")
    if "--check" in sys.argv:
        cur = open(path, encoding="utf-8").read() if os.path.exists(path) else ""
        if cur != new:
            print("LỆCH: contracts/control_schema.sql khác lược đồ hiện tại của control/. Chạy lại không có --check để sinh lại.")
            sys.exit(1)
        print("hợp đồng khớp với control/")
    else:
        open(path, "w", encoding="utf-8").write(new)
        print("đã ghi", path)
