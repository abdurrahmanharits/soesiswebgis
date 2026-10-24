"use client";

import Link from "next/link";
import { useState } from "react";

import { useSession } from "@/shared/session/session-provider";

import { useFormErrors, useOrganizations, useUsers } from "../hooks/use-admin-data";
import { createUser, passwordMismatch } from "../services/admin";
import { FieldError, inputClass, readUserForm, UserFields } from "./user-fields";

/** /admin/users: all accounts plus "Add user" (same content as the Laravel page). */
export function UsersPage() {
  const { user: me } = useSession();
  const users = useUsers();
  const organizations = useOrganizations();
  const form = useFormErrors();
  const [status, setStatus] = useState<string | null>(null);

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const element = event.currentTarget;
    const data = new FormData(element);

    setStatus(null);
    if (data.get("password") !== data.get("password_confirmation")) {
      form.setError(passwordMismatch());
      return;
    }

    const result = await createUser({ ...readUserForm(data), password: String(data.get("password") ?? "") });
    form.setError(result.error);
    if (result.data) {
      element.reset();
      setStatus(`User ${result.data.username} created.`);
      await users.reload();
      await organizations.reload();
    }
  }

  if (users.error) {
    return <p className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{users.error.message}</p>;
  }

  return (
    <div className="space-y-6">
      {status && <p className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800" role="status">{status}</p>}

      <section className="overflow-hidden rounded-lg border border-slate-200 bg-white" aria-labelledby="users-heading">
        <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
          <h2 id="users-heading" className="text-sm font-semibold">All users</h2>
          <Link href="/admin/organizations" className="text-sm font-medium text-emerald-700 hover:underline">Organizations</Link>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-sm" data-testid="users-table">
            <thead className="bg-slate-50 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
              <tr>
                <th className="px-5 py-2">Name</th>
                <th className="px-5 py-2">Username / e-mail</th>
                <th className="px-5 py-2">Role</th>
                <th className="px-5 py-2">Organization</th>
                <th className="px-5 py-2">Status</th>
                <th className="px-5 py-2"><span className="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {users.loading && <tr><td colSpan={6} className="px-5 py-6 text-center text-slate-500">Loading…</td></tr>}
              {users.items.map((u) => (
                <tr key={u.id} className={u.is_active ? "" : "text-slate-400"}>
                  <td className="px-5 py-3 font-medium">{u.name}{u.id === me?.id && <span className="text-xs font-normal text-slate-500"> (you)</span>}</td>
                  <td className="px-5 py-3">{u.username}<br /><span className="text-xs text-slate-500">{u.email}</span></td>
                  <td className="px-5 py-3">{u.role_label}</td>
                  <td className="px-5 py-3">{u.organization?.name ?? "—"}</td>
                  <td className="px-5 py-3">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${u.is_active ? "bg-emerald-50 text-emerald-800 ring-emerald-200" : "bg-slate-100 text-slate-600 ring-slate-300"}`}>
                      {u.is_active ? "Active" : "Inactive"}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-right"><Link href={`/admin/users/${u.id}`} className="font-medium text-emerald-700 hover:underline">Edit</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="create-heading">
        <h2 id="create-heading" className="text-sm font-semibold">Add user</h2>
        <form onSubmit={submit} className="mt-4 space-y-4" data-testid="create-user-form">
          {form.error && Object.keys(form.error.errors).length === 0 && (
            <p className="text-sm text-rose-700" role="alert">{form.error.message}</p>
          )}
          <UserFields organizations={organizations.items} error={form.field} />
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="password" className="text-sm font-medium text-slate-700">Password</label>
              <input id="password" name="password" type="password" required autoComplete="new-password" className={inputClass} />
              <p className="mt-1 text-xs text-slate-500">At least 8 characters.</p>
              <FieldError message={form.field("password")} />
            </div>
            <div>
              <label htmlFor="password_confirmation" className="text-sm font-medium text-slate-700">Repeat password</label>
              <input id="password_confirmation" name="password_confirmation" type="password" required autoComplete="new-password" className={inputClass} />
            </div>
          </div>
          <button type="submit" className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Create user</button>
        </form>
      </section>
    </div>
  );
}
