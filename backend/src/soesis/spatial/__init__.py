"""Pure spatial algorithms (numpy/shapely-style computations).

No FastAPI, database, Celery or GDAL I/O here: functions take plain values and
return plain values, so they can be unit-tested in isolation and reused by any
module's application layer. Populated from R1 onward.
"""
