# SoESIS backend (FastAPI API + Celery worker share this image).
# Official OSGeo image: GDAL 3.10.3 CLI + matching osgeo.gdal bindings on Python 3.12 (Ubuntu 24.04).
FROM ghcr.io/osgeo/gdal:ubuntu-small-3.10.3

COPY --from=ghcr.io/astral-sh/uv:0.11.7 /uv /uvx /usr/local/bin/

ENV UV_PYTHON=/usr/bin/python3 \
    UV_PYTHON_DOWNLOADS=never \
    UV_PROJECT_ENVIRONMENT=/opt/venv \
    UV_LINK_MODE=copy \
    UV_COMPILE_BYTECODE=1 \
    PATH=/opt/venv/bin:$PATH \
    PYTHONPATH=/app/src \
    PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1

WORKDIR /app

# Dependencies first (cached layer). The venv sees system site-packages so the
# image's osgeo.gdal bindings stay importable next to the locked dependencies.
COPY pyproject.toml uv.lock ./
RUN uv venv --system-site-packages /opt/venv \
    && uv sync --frozen --no-install-project

COPY . .

# Non-root runtime user (UID 1000 is taken by the image's "ubuntu" user).
RUN useradd --create-home --uid 10001 soesis
USER soesis

EXPOSE 8001

CMD ["uvicorn", "soesis.main:app", "--host", "0.0.0.0", "--port", "8001"]
