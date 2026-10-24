import type { Metadata } from "next";

import { OrganizationsPage } from "@/features/admin/ui/organizations-page";
import { RoleGate } from "@/shared/session/role-gate";

export const metadata: Metadata = { title: "Organizations" };

export default function AdminOrganizationsPage() {
  return (
    <main className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
      <h1 className="text-2xl font-semibold tracking-tight">Organizations</h1>
      <RoleGate roles={["admin"]}>
        <OrganizationsPage />
      </RoleGate>
    </main>
  );
}
