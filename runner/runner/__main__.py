import sys

from .config import Config, verify_pin
from .server import RunnerService, serve


def main():
    cfg = Config.from_env()
    verify_pin(cfg)
    service = RunnerService(cfg)
    srv = serve(service)
    print(f"[runner] {cfg.provider} nghe tại {cfg.host}:{cfg.port} (song song tối đa {cfg.max_concurrency})", file=sys.stderr, flush=True)
    try:
        srv.serve_forever()
    except KeyboardInterrupt:
        pass


if __name__ == "__main__":
    main()
