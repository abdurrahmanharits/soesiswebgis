import type { Metadata } from "next";
import Link from "next/link";

import { UserEditPage } from "@/features/admin/ui/user-edit-page";
import { RoleGate } from "@/shared/session/role-gate";

export const metadata: Metadata = { title: "Edit user" };

export default function AdminUserEditPage() {
  return (
    <main className="mx-auto max-w-3xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
      <Link href="/admin/users" className="text-sm font-medium text-slate-600 hover:text-slate-900">← Users</Link>
      <h1 className="text-2xl font-semibold tracking-tight">Edit user</h1>
      <RoleGate roles={["admin"]}>
        <UserEditPage />
      </RoleGate>
    </main>
  );
}
