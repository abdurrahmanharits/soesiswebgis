import type { Metadata } from "next";

import { Brand } from "@/shared/ui/brand";

import { instrumentSans } from "../fonts";
import "../globals.css";

export const metadata: Metadata = {
  title: { default: "Sign in", template: "%s · SoESIS WebGIS" },
};

/** Root layout of the sign-in page (English, no session loaded). */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className={`${instrumentSans.variable} antialiased`}>
      <body className="min-h-screen bg-slate-100 font-sans text-slate-900">
        <header className="border-b border-slate-200 bg-white">
          <div className="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
            <Brand href="/" subtitle="Spatial Data Platform" />
          </div>
        </header>
        {children}
      </body>
    </html>
  );
}
