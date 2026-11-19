import os
import signal
import subprocess

from .errors import RunnerError

SAFE_PATH = "/usr/local/bin:/usr/bin:/bin"


def build_env(cfg, home: str, tmp: str, extra: dict) -> dict:
    """Môi trường TỐI THIỂU cho CLI: không kế thừa gì từ tiến trình runner (đặc biệt là bí mật của runner)."""
    env = {"PATH": SAFE_PATH, "HOME": home, "TMPDIR": tmp, "LANG": "C.UTF-8", "LC_ALL": "C.UTF-8"}
    env.update(cfg.proxy_env)
    for name in cfg.passthrough_env:
        if name in os.environ:
            env[name] = os.environ[name]
    env.update(extra)
    return env


def run_cli(args: list[str], *, stdin_bytes: bytes | None, env: dict, cwd: str, timeout: int, max_out: int):
    """Chạy CLI không qua shell, đầu vào đóng sau khi ghi, giết cả nhóm tiến trình khi quá giờ."""
    try:
        p = subprocess.Popen(args, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                             env=env, cwd=cwd, start_new_session=True)
    except FileNotFoundError:
        raise RunnerError("upstream_error", "Không tìm thấy tệp thực thi CLI.")
    try:
        out, err = p.communicate(stdin_bytes if stdin_bytes is not None else b"", timeout=timeout)
    except subprocess.TimeoutExpired:
        try:
            os.killpg(p.pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
        p.communicate()
        raise RunnerError("timeout", f"CLI quá {timeout} giây.")
    finally:
        # đảm bảo không còn tiến trình con mồ côi
        try:
            os.killpg(p.pid, signal.SIGKILL)
        except (ProcessLookupError, PermissionError):
            pass
    return p.returncode, out[:max_out].decode("utf-8", "replace"), err[:max_out].decode("utf-8", "replace")
