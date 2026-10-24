"use client";

import { useState } from "react";

import { useFormErrors, useOrganizations } from "../hooks/use-admin-data";
import { createOrganization, renameOrganization } from "../services/admin";
import { FieldError } from "./user-fields";

const small = "w-full rounded-md border-0 px-2.5 py-1.5 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600";

/** /admin/organizations: provenance of users and their uploads (does not limit access). */
export function OrganizationsPage() {
  const organizations = useOrganizations();
  const form = useFormErrors();
  const [status, setStatus] = useState<string | null>(null);

  async function run(action: Promise<{ error: Parameters<typeof form.setError>[0] }>, message: string) {
    const result = await action;
    form.setError(result.error);
    setStatus(result.error ? null : message);
    if (!result.error) await organizations.reload();
    return !result.error;
  }

  if (organizations.error) return <p className="text-sm text-rose-700" role="alert">{organizations.error.message}</p>;

  return (
    <section className="max-w-xl rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="org-heading">
      <h2 id="org-heading" className="text-sm font-semibold">Organizations</h2>
      <p className="mt-1 text-xs text-slate-500">Recorded with each user&apos;s uploads for provenance. Does not limit access.</p>

      {status && <p className="mt-3 text-sm text-emerald-800" role="status">{status}</p>}
      <FieldError message={form.field("name") ?? (form.error && Object.keys(form.error.errors).length === 0 ? form.error.message : undefined)} />

      <ul className="mt-3 space-y-2" data-testid="organizations">
        {organizations.items.map((o) => (
          <li key={o.id}>
            <form
              className="flex items-center gap-2"
              onSubmit={(event) => {
                event.preventDefault();
                void run(renameOrganization(o.id, String(new FormData(event.currentTarget).get("name") ?? "")), "Organization renamed.");
              }}
            >
              <label className="min-w-0 flex-1">
                <span className="sr-only">Organization name</span>
                <input name="name" defaultValue={o.name} maxLength={255} className={small} />
              </label>
              <span className="w-16 shrink-0 text-right text-xs text-slate-500 tabular-nums">{o.users_count} {o.users_count === 1 ? "user" : "users"}</span>
              <button type="submit" className="shrink-0 text-xs font-medium text-emerald-700 hover:underline">Rename</button>
            </form>
          </li>
        ))}
      </ul>

      <form
        className="mt-4 flex items-center gap-2 border-t border-slate-100 pt-4"
        onSubmit={async (event) => {
          event.preventDefault();
          const element = event.currentTarget;
          if (await run(createOrganization(String(new FormData(element).get("name") ?? "")), "Organization added.")) element.reset();
        }}
      >
        <label className="min-w-0 flex-1">
          <span className="sr-only">New organization</span>
          <input name="name" maxLength={255} required placeholder="New organization" className={small} />
        </label>
        <button type="submit" className="shrink-0 rounded-md px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Add</button>
      </form>
    </section>
  );
}
