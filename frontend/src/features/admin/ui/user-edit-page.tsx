"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useState } from "react";

import { useFormErrors, useOrganizations, useUsers } from "../hooks/use-admin-data";
import { passwordMismatch, resetPassword, updateUser } from "../services/admin";
import { FieldError, inputClass, readUserForm, UserFields } from "./user-fields";

/** /admin/users/[id]: edit profile, role, organization, active flag; set a new password. */
export function UserEditPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const users = useUsers();
  const organizations = useOrganizations();
  const profile = useFormErrors();
  const password = useFormErrors();
  const [notice, setNotice] = useState<string | null>(null);

  const user = users.items.find((u) => String(u.id) === id);

  if (users.loading || organizations.loading) return <p className="text-sm text-slate-500">Loading…</p>;
  if (users.error) return <p className="text-sm text-rose-700" role="alert">{users.error.message}</p>;
  if (!user) return <p className="text-sm text-slate-600">User not found. <Link href="/admin/users" className="text-emerald-700 hover:underline">Back to users</Link></p>;

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const result = await updateUser(user!.id, readUserForm(new FormData(event.currentTarget)));
    profile.setError(result.error);
    if (result.data) router.push("/admin/users");
  }

  async function setPassword(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const element = event.currentTarget;
    const data = new FormData(element);
    setNotice(null);

    if (data.get("password") !== data.get("password_confirmation")) {
      password.setError(passwordMismatch());
      return;
    }

    const result = await resetPassword(user!.id, String(data.get("password") ?? ""));
    password.setError(result.error);
    if (!result.error) {
      element.reset();
      setNotice("Password updated. The user is signed out everywhere.");
    }
  }

  return (
    <div className="space-y-6">
      <form onSubmit={save} className="space-y-4 rounded-lg border border-slate-200 bg-white p-5" data-testid="edit-user-form">
        <h2 className="text-sm font-semibold">Profile and access</h2>
        {profile.error && Object.keys(profile.error.errors).length === 0 && <p className="text-sm text-rose-700" role="alert">{profile.error.message}</p>}
        <UserFields user={user} organizations={organizations.items} error={profile.field} />
        <div className="flex items-center gap-3">
          <button type="submit" className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Save changes</button>
          <Link href="/admin/users" className="text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</Link>
        </div>
      </form>

      <form onSubmit={setPassword} className="space-y-4 rounded-lg border border-slate-200 bg-white p-5" data-testid="reset-password-form">
        <h2 className="text-sm font-semibold">Set a new password</h2>
        {notice && <p className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{notice}</p>}
        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="new-password" className="text-sm font-medium text-slate-700">New password</label>
            <input id="new-password" name="password" type="password" required autoComplete="new-password" className={inputClass} />
            <FieldError message={password.field("password")} />
          </div>
          <div>
            <label htmlFor="new-password-confirmation" className="text-sm font-medium text-slate-700">Repeat password</label>
            <input id="new-password-confirmation" name="password_confirmation" type="password" required autoComplete="new-password" className={inputClass} />
          </div>
        </div>
        <button type="submit" className="rounded-md px-4 py-2 text-sm font-medium text-slate-800 ring-1 ring-slate-300 ring-inset hover:bg-slate-50">Set password</button>
      </form>
    </div>
  );
}
