"use client";

import type { AdminUser, Organization, UserWrite } from "../services/admin";

export const inputClass = "mt-1 w-full rounded-md border-0 px-3 py-2 text-sm ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-emerald-600";
const labelClass = "text-sm font-medium text-slate-700";

const ROLES: { value: UserWrite["role"]; label: string }[] = [
  { value: "uploader", label: "Uploader" },
  { value: "curator", label: "Curator" },
  { value: "admin", label: "Admin" },
];

export function FieldError({ message }: { message?: string }) {
  return message ? <p className="mt-1 text-xs text-rose-700">{message}</p> : null;
}

/** Shared create/edit fields; `user` is undefined when creating. */
export function UserFields({ user, organizations, error }: {
  user?: AdminUser;
  organizations: Organization[];
  error: (field: string) => string | undefined;
}) {
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <div>
        <label htmlFor="name" className={labelClass}>Name</label>
        <input id="name" name="name" maxLength={100} required defaultValue={user?.name} className={inputClass} />
        <FieldError message={error("name")} />
      </div>
      <div>
        <label htmlFor="username" className={labelClass}>Username</label>
        <input id="username" name="username" maxLength={30} required autoComplete="off" defaultValue={user?.username} placeholder="e.g. rina.pratiwi" className={inputClass} />
        <p className="mt-1 text-xs text-slate-500">3–30 lowercase letters, digits, dots or underscores.</p>
        <FieldError message={error("username")} />
      </div>
      <div>
        <label htmlFor="email" className={labelClass}>E-mail</label>
        <input id="email" name="email" type="email" maxLength={255} required autoComplete="off" defaultValue={user?.email} className={inputClass} />
        <FieldError message={error("email")} />
      </div>
      <div>
        <label htmlFor="role" className={labelClass}>Role</label>
        <select id="role" name="role" defaultValue={user?.role ?? "uploader"} className={`${inputClass} pr-8`}>
          {ROLES.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}
        </select>
        <FieldError message={error("role")} />
      </div>
      <div>
        <label htmlFor="organization_id" className={labelClass}>Organization</label>
        <select id="organization_id" name="organization_id" defaultValue={user?.organization?.id ?? ""} className={`${inputClass} pr-8`}>
          <option value="">—</option>
          {organizations.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}
        </select>
        <FieldError message={error("organization_id")} />
      </div>
      <div className="flex items-end">
        <label className="inline-flex items-center gap-2 pb-2 text-sm text-slate-700">
          <input type="checkbox" name="is_active" defaultChecked={user?.is_active ?? true} className="size-4 accent-emerald-700" />
          Active (can sign in)
        </label>
      </div>
    </div>
  );
}

export function readUserForm(form: FormData): UserWrite {
  const organization = String(form.get("organization_id") ?? "");
  return {
    name: String(form.get("name") ?? ""),
    username: String(form.get("username") ?? ""),
    email: String(form.get("email") ?? ""),
    role: String(form.get("role")) as UserWrite["role"],
    organization_id: organization ? Number(organization) : null,
    is_active: form.get("is_active") === "on",
  };
}
