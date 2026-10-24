"use client";

import { useCallback, useEffect, useState } from "react";

import { getLiveness } from "../services/health";

export type LivenessState = "checking" | "up" | "down";

/** Polls the backend through the same-origin proxy (browser → Next.js → FastAPI). */
export function useLiveness(intervalMs = 10_000): { state: LivenessState; refresh: () => void } {
  const [state, setState] = useState<LivenessState>("checking");

  const refresh = useCallback(() => {
    getLiveness()
      .then((alive) => setState(alive ? "up" : "down"))
      .catch(() => setState("down"));
  }, []);

  useEffect(() => {
    refresh();
    const timer = setInterval(refresh, intervalMs);
    return () => clearInterval(timer);
  }, [refresh, intervalMs]);

  return { state, refresh };
}
