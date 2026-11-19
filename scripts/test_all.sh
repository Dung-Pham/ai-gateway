#!/bin/sh
# Chạy toàn bộ kiểm thử trong container dev (không cần cài gì lên máy) + kiểm tra hợp đồng dữ liệu. Chạy từ gốc repo.
set -e
cd "$(dirname "$0")/.."
docker build -q -t ai-gateway-dev -f deploy/Dockerfile.dev . >/dev/null
python3 scripts/dump_contract.py --check
for d in runner gateway egress-proxy; do
  echo "== $d"
  docker run --rm -u "$(id -u):$(id -g)" -e PYTHONDONTWRITEBYTECODE=1 -v "$PWD":/w:rw -w "/w/$d" ai-gateway-dev python -m pytest -q -p no:cacheprovider tests 2>&1 | tail -3
done
echo "== lỗ hổng đã biết trong phụ thuộc"
docker run --rm -v "$PWD/gateway":/g:ro ai-gateway-dev pip-audit -r /g/requirements.lock --progress-spinner off 2>&1 | tail -2
