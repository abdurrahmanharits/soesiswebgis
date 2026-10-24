"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { useSession } from "./session-provider";

const LINKS = [
  { href: "/status", label: "Status", admin: false },
  { href: "/admin/users", label: "Users", admin: true },
  { href: "/admin/organizations", label: "Organizations", admin: true },
] as const;

/** Internal menu; admin-only entries are hidden for other roles (the backend still enforces 403). */
export function InternalNav() {
  const { user } = useSession();
  const pathname = usePathname();

  return (
    <nav className="flex min-w-0 items-center gap-1 overflow-x-auto text-sm" aria-label="Main">
      {LINKS.filter((link) => !link.admin || user?.role === "admin").map((link) => {
        const active = pathname === link.href || pathname.startsWith(`${link.href}/`);
        return (
          <Link key={link.href} href={link.href} aria-current={active ? "page" : undefined}
                className={`rounded-md px-3 py-2 font-medium whitespace-nowrap ${active ? "bg-slate-100 text-slate-900" : "text-slate-600 hover:text-slate-900"}`}>
            {link.label}
          </Link>
        );
      })}
      <Link href="/" className="rounded-md px-3 py-2 font-medium whitespace-nowrap text-slate-600 hover:text-slate-900">Catalogue</Link>
    </nav>
  );
}
