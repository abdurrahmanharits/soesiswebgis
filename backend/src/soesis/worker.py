"""Composition root of the Celery worker (queue "spatial").

R0: the worker starts and connects to the broker; tasks arrive with R1
(ingestion). Run: celery -A soesis.worker worker -Q spatial
"""

from celery import Celery

from soesis.shared.config import get_settings

settings = get_settings()

celery_app = Celery("soesis", broker=settings.redis_url)
celery_app.conf.update(
    task_default_queue="spatial",
    # Spatial jobs are long: acknowledge after completion so a crashed worker's job is retried.
    task_acks_late=True,
    worker_prefetch_multiplier=1,
    broker_connection_retry_on_startup=True,
)

# `celery -A soesis.worker` looks for `app` (or `celery`) in this module.
app = celery_app
