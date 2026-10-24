"use client";

import { useSignIn } from "../hooks/use-sign-in";

const input = "mt-1 w-full rounded-md border-0 px-3 py-2 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600";

/** Same fields and messages as the Laravel /login page. */
export function LoginForm() {
  const { submit, error, submitting } = useSignIn();

  return (
    <form
      className="mt-6 space-y-4 rounded-lg border border-slate-200 bg-white p-5"
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        void submit(String(form.get("login") ?? ""), String(form.get("password") ?? ""));
      }}
    >
      {error && (
        <p className="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert" data-testid="login-error">
          {error}
        </p>
      )}

      <div>
        <label htmlFor="login" className="text-sm font-medium text-slate-700">E-mail or username</label>
        <input id="login" name="login" type="text" required autoFocus autoComplete="username" className={input} />
      </div>
      <div>
        <label htmlFor="password" className="text-sm font-medium text-slate-700">Password</label>
        <input id="password" name="password" type="password" required autoComplete="current-password" className={input} />
      </div>

      <button type="submit" disabled={submitting} className="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
        {submitting ? "Signing in…" : "Sign in"}
      </button>
    </form>
  );
}
