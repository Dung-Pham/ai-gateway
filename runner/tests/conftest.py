import json
import os
import stat
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))
from runner.config import Config  # noqa: E402
from runner.server import RunnerService  # noqa: E402

STUB = os.path.join(os.path.dirname(__file__), "stub_cli.py")
SECRET = "s" * 48
TOKEN_FILES = {"claude": ".credentials.json", "grok": "auth.json", "agy": os.path.join(".gemini", "antigravity-cli", "antigravity-oauth-token")}


@pytest.fixture
def make_service(tmp_path, monkeypatch):
    def factory(provider="claude", **over):
        auth = tmp_path / "auth"
        tok = auth / TOKEN_FILES[provider]
        tok.parent.mkdir(parents=True, exist_ok=True)
        tok.write_text('{"token":"ORIGINAL-TOKEN"}')
        os.chmod(tok, 0o600)
        deny = tmp_path / "deny.txt"
        deny.write_text("run_terminal_command,read_file,search_replace")
        log = tmp_path / "stub.log"
        monkeypatch.setenv("STUB_LOG", str(log))
        kw = dict(provider=provider, cli_bin=STUB, auth_dir=str(auth), secret=SECRET, work_root=str(tmp_path / "work"),
                  allow_unpinned=True, timeout_s=5, max_concurrency=2, passthrough_env=("STUB_LOG",), grok_deny_file=str(deny),
                  max_prompt_bytes=2000, port=0, host="127.0.0.1")
        kw.update(over)
        svc = RunnerService(Config(**kw))
        svc.tmp_path, svc.log, svc.token = tmp_path, log, tok
        return svc
    return factory


def calls(svc):
    return [json.loads(l) for l in svc.log.read_text().splitlines()] if svc.log.exists() else []
