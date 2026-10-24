"""Exports the OpenAPI contract shared with the frontend (contracts/openapi.json).

  python -m soesis.contract /contracts/openapi.json

The frontend generates its TypeScript API types from that file; a backend test
fails when the committed file no longer matches the API (contract drift).
"""

import json
import sys
from pathlib import Path

from soesis.main import create_app
from soesis.shared.config import Settings

# Building the app never connects to anything, so a placeholder URL is enough.
_OFFLINE = Settings(database_url="postgresql+psycopg://contract@localhost/contract", redis_url="redis://localhost:1/0")


def render() -> str:
    schema = create_app(_OFFLINE).openapi()
    return json.dumps(schema, indent=2, ensure_ascii=False, sort_keys=True) + "\n"


def main() -> None:
    target = Path(sys.argv[1] if len(sys.argv) > 1 else "/contracts/openapi.json")
    target.write_text(render(), encoding="utf-8")
    print(f"OpenAPI contract written to {target}")


if __name__ == "__main__":
    main()
