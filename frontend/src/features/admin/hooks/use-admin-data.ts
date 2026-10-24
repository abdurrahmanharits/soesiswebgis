"use client";

import { useCallback, useEffect, useState } from "react";

import { listOrganizations, listUsers, type AdminUser, type ApiError, type Organization } from "../services/admin";

type Loaded<T> = { items: T[]; error: ApiError | null; loading: boolean; reload: () => Promise<void> };

function useList<T>(load: () => Promise<{ data: T[] | null; error: ApiError | null }>): Loaded<T> {
  const [items, setItems] = useState<T[]>([]);
  const [error, setError] = useState<ApiError | null>(null);
  const [loading, setLoading] = useState(true);

  const apply = useCallback((result: { data: T[] | null; error: ApiError | null }) => {
    setItems(result.data ?? []);
    setError(result.error);
    setLoading(false);
  }, []);

  const reload = useCallback(() => load().then(apply), [load, apply]);

  useEffect(() => {
    let cancelled = false;
    load().then((result) => {
      if (!cancelled) apply(result);
    });
    return () => {
      cancelled = true;
    };
  }, [load, apply]);

  return { items, error, loading, reload };
}

export const useUsers = () => useList<AdminUser>(listUsers);

export const useOrganizations = () => useList<Organization>(listOrganizations);

/** Field errors + message of the last failed save, cleared on the next attempt. */
export function useFormErrors() {
  const [error, setError] = useState<ApiError | null>(null);
  return { error, setError, field: (name: string) => error?.field(name) };
}
