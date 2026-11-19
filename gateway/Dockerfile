FROM python:3.12-slim-bookworm
COPY requirements.lock /tmp/requirements.lock
RUN pip install --no-cache-dir -r /tmp/requirements.lock && rm /tmp/requirements.lock && useradd --uid 10001 --no-create-home --shell /usr/sbin/nologin gateway
COPY gateway /usr/local/lib/python3.12/site-packages/gateway
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1
USER 10001:10001
WORKDIR /tmp
EXPOSE 8000
ENTRYPOINT ["python", "-I", "-m", "gateway"]
