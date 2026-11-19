import hashlib
import os

import pytest

from conftest import STUB, SECRET
from runner.config import Config, verify_pin, sha256_file


def cfg(**kw):
    base = dict(provider="claude", cli_bin=STUB, auth_dir="/a", secret=SECRET)
    base.update(kw)
    return Config(**base)


def test_pin_dung_thi_chay():
    verify_pin(cfg(cli_sha256=sha256_file(STUB)))


def test_pin_sai_thi_tu_choi_khoi_dong():
    with pytest.raises(SystemExit) as e:
        verify_pin(cfg(cli_sha256="0" * 64))
    assert "không khớp" in str(e.value)


def test_khong_pin_thi_tu_choi_tru_khi_dev():
    with pytest.raises(SystemExit):
        verify_pin(cfg())
    verify_pin(cfg(allow_unpinned=True))


def test_thieu_tep_cli():
    with pytest.raises(SystemExit):
        verify_pin(cfg(cli_bin="/khong/co", allow_unpinned=True))


def test_from_env_bat_buoc_bi_mat_du_dai(tmp_path):
    s = tmp_path / "s"; s.write_text("ngan")
    env = {"RUNNER_PROVIDER": "claude", "RUNNER_CLI_BIN": STUB, "RUNNER_AUTH_DIR": "/a", "RUNNER_SECRET_FILE": str(s)}
    with pytest.raises(SystemExit):
        Config.from_env(env)
    s.write_text(SECRET)
    assert Config.from_env(env).secret == SECRET
    with pytest.raises(SystemExit):
        Config.from_env({**env, "RUNNER_PROVIDER": "khac"})
