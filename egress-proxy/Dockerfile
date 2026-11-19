FROM python:3.12-slim-bookworm
RUN useradd --uid 10001 --no-create-home --shell /usr/sbin/nologin egress
COPY proxy.py /app/proxy.py
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1
USER 10001:10001
WORKDIR /app
EXPOSE 3128
ENTRYPOINT ["python", "-I", "/app/proxy.py"]
