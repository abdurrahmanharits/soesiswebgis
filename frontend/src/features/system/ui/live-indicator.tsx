"use client";

import { StatusBadge } from "@/shared/ui/status-badge";

import { useLiveness } from "../hooks/use-liveness";

const LABELS = { checking: "Checking…", up: "API reachable", down: "API unreachable" } as const;

/** Client component: proves the browser → /api proxy → FastAPI path. */
export function LiveIndicator() {
  const { state } = useLiveness();

  return (
    <span data-testid="live-indicator" data-state={state}>
      <StatusBadge ok={state === "up"} pending={state === "checking"} label={LABELS[state]} />
    </span>
  );
}
