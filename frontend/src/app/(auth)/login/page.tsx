import type { Metadata } from "next";
import Link from "next/link";

import { LoginForm } from "@/features/auth/ui/login-form";

export const metadata: Metadata = { title: "Sign in" };

export default function LoginPage() {
  return (
    <main className="mx-auto max-w-sm px-4 py-16">
      <h1 className="text-xl font-semibold tracking-tight">Sign in</h1>
      <p className="mt-1 text-sm text-slate-600">Internal area for uploading and curating datasets.</p>
      <LoginForm />
      <p className="mt-6 text-center text-sm text-slate-600">
        Looking for published data?{" "}
        <Link href="/" className="font-medium text-emerald-700 hover:underline">Open the catalogue</Link>
      </p>
    </main>
  );
}
