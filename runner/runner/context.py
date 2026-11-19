import os
import shutil
import tempfile
from dataclasses import dataclass


@dataclass
class Ctx:
    cfg: object
    root: str      # thư mục gốc của riêng yêu cầu này
    home: str
    work: str
    tmp: str
    request_id: str

    @staticmethod
    def create(cfg, request_id: str) -> "Ctx":
        os.makedirs(cfg.work_root, mode=0o700, exist_ok=True)
        root = tempfile.mkdtemp(prefix="req-", dir=cfg.work_root)
        os.chmod(root, 0o700)
        home, work, tmp = (os.path.join(root, n) for n in ("home", "work", "tmp"))
        for d in (home, work, tmp):
            os.mkdir(d, 0o700)
        return Ctx(cfg, root, home, work, tmp, request_id)

    def destroy(self) -> None:
        shutil.rmtree(self.root, ignore_errors=True)


def copy_secret(src: str, dst: str) -> bool:
    """Chép tệp bí mật (token) vào thư mục tạm với quyền 0600. Trả False nếu nguồn không có."""
    if not os.path.isfile(src):
        return False
    os.makedirs(os.path.dirname(dst), mode=0o700, exist_ok=True)
    fd = os.open(dst, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, "wb") as f, open(src, "rb") as s:
        f.write(s.read())
    return True


def sync_back(tmp_copy: str, original: str) -> bool:
    """Nếu CLI đã làm mới token (nội dung đổi) thì ghi ngược vào volume token, nguyên tử, quyền 0600."""
    if not os.path.isfile(tmp_copy):
        return False
    new = open(tmp_copy, "rb").read()
    old = open(original, "rb").read() if os.path.isfile(original) else None
    if new == old or not new:
        return False
    tmp = original + ".new"
    fd = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, "wb") as f:
        f.write(new)
    os.replace(tmp, original)
    return True
