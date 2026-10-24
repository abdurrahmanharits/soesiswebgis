import type { Metadata } from "next";

import { UsersPage } from "@/features/admin/ui/users-page";
import { RoleGate } from "@/shared/session/role-gate";

export const metadata: Metadata = { title: "Users" };

export default function AdminUsersPage() {
  return (
    <main className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Users</h1>
        <p className="mt-1 text-sm text-slate-600">
          Accounts for the internal area. Uploaders upload and complete metadata of their own datasets; curators and
          admins approve, reject and reopen; admins manage users.
        </p>
      </div>
      <RoleGate roles={["admin"]}>
        <UsersPage />
      </RoleGate>
    </main>
  );
}
