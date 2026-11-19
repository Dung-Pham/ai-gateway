import uvicorn

from .app import create_app
from .config import Settings


def main():
    app = create_app(Settings.from_env())
    # Một tiến trình (trạng thái điều phối nằm trong bộ nhớ). Nghe trên mọi địa chỉ TRONG container; công khai ra ngoài do Compose quyết.
    uvicorn.run(app, host="0.0.0.0", port=8000, log_level="warning", access_log=False, workers=1)


if __name__ == "__main__":
    main()
