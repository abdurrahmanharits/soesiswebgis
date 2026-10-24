"""Clean-architecture rules (pyproject.toml [tool.importlinter]) and the OpenAPI contract."""

import os
import shutil
import subprocess
from pathlib import Path

import pytest

from soesis import contract

BACKEND_ROOT = Path(__file__).resolve().parents[2]


def test_import_contracts_hold():
    lint_imports = shutil.which("lint-imports")
    assert lint_imports, "import-linter is not installed (dev dependency)"

    result = subprocess.run(
        [lint_imports], cwd=BACKEND_ROOT, capture_output=True, text=True,
        env={**os.environ, "PYTHONPATH": str(BACKEND_ROOT / "src")},
    )

    assert result.returncode == 0, result.stdout + result.stderr


def test_committed_openapi_contract_matches_the_api():
    path = Path(os.environ.get("SOESIS_CONTRACT_PATH", "/contracts/openapi.json"))
    if not path.exists():
        pytest.skip(f"{path} not mounted")

    assert path.read_text(encoding="utf-8") == contract.render(), (
        "contracts/openapi.json is outdated: run `python -m soesis.contract` and regenerate the frontend types."
    )
