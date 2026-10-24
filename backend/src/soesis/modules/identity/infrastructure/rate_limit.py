"""Login throttling in Redis: N failures per window per (login, client IP), shared by all workers."""

import hashlib

from redis import Redis


class RedisLoginRateLimiter:
    PREFIX = "soesis:login-failures:"

    def __init__(self, redis: Redis, max_attempts: int = 5, window_seconds: int = 60) -> None:
        self._redis = redis
        self._max_attempts = max_attempts
        self._window = window_seconds

    def _key(self, key: str) -> str:
        # Hashed: no e-mail addresses or IPs in Redis key names.
        return self.PREFIX + hashlib.sha256(key.encode("utf-8")).hexdigest()

    def retry_after(self, key: str) -> int | None:
        name = self._key(key)
        attempts = int(self._redis.get(name) or 0)
        if attempts < self._max_attempts:
            return None
        return max(int(self._redis.ttl(name)), 1)

    def hit(self, key: str) -> None:
        name = self._key(key)
        pipeline = self._redis.pipeline()
        pipeline.incr(name)
        pipeline.expire(name, self._window, nx=True)  # window starts at the first failure
        pipeline.execute()

    def clear(self, key: str) -> None:
        self._redis.delete(self._key(key))
