# Runner: chỉ thư viện chuẩn Python, không cài gói bên thứ ba. CLI AI KHÔNG nằm trong image: gắn chỉ-đọc vào /opt/cli và ghim bằng SHA-256.
FROM python:3.12-slim-bookworm
RUN useradd --uid 10001 --no-create-home --shell /usr/sbin/nologin runner
COPY runner /usr/local/lib/python3.12/site-packages/runner
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1
USER 10001:10001
WORKDIR /tmp
EXPOSE 8800
ENTRYPOINT ["python", "-I", "-m", "runner"]
