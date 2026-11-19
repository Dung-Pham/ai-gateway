#!/usr/bin/env python3
"""Chép phông từ @fontsource vào public/fonts và sinh public/css/gateway-fonts.css (tự lưu, không dùng CDN)."""
import os
import shutil

SRC = "node_modules/@fontsource"
VI = "U+0102-0103,U+0110-0111,U+0128-0129,U+0168-0169,U+01A0-01A1,U+01AF-01B0,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+0329,U+1EA0-1EF9,U+20AB"
LA = "U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD"

os.makedirs("public/fonts", exist_ok=True)
os.makedirs("public/css", exist_ok=True)
faces = []
for pkg, fam, weights in [("be-vietnam-pro", "Be Vietnam Pro", (400, 500, 600, 700)), ("ibm-plex-mono", "IBM Plex Mono", (400, 500))]:
    for w in weights:
        for sub, rng in (("vietnamese", VI), ("latin", LA)):
            f = f"{pkg}-{sub}-{w}-normal.woff2"
            shutil.copy(f"{SRC}/{pkg}/files/{f}", f"public/fonts/{f}")
            faces.append(
                f"@font-face{{font-family:'{fam}';font-style:normal;font-display:swap;font-weight:{w};"
                f"src:url('../fonts/{f}') format('woff2');unicode-range:{rng}}}"
            )
with open("public/css/gateway-fonts.css", "w", encoding="utf-8") as fh:
    fh.write("/* Phông tự lưu (SIL OFL) từ @fontsource: giao diện nội bộ không gọi CDN ngoài. Sinh lại bằng `npm run fonts`. */\n" + "\n".join(faces) + "\n")
print(len(faces), "font-face")
