"use client";

import { useState } from "react";

import { safeNextPath, signIn } from "../services/auth";

export function useSignIn() {
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function submit(login: string, password: string) {
    setSubmitting(true);
    setError(null);

    const failure = await signIn(login, password);

    if (failure) {
      setError(failure.message);
      setSubmitting(false);
      return;
    }

    // Full navigation so the internal area starts with the new session.
    window.location.assign(safeNextPath(new URLSearchParams(window.location.search).get("next")));
  }

  return { submit, error, submitting };
}
