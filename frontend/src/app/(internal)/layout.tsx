import type { Metadata } from "next";

import { LiveIndicator } from "@/features/system/ui/live-indicator";
import { Brand } from "@/shared/ui/brand";

import { instrumentSans } from "../fonts";
import "../globals.css";

export const metadata: Metadata = {
  title: { default: "SoESIS WebGIS", template: "%s · SoESIS WebGIS" },
};

/** Root layout of the internal area (English). Protected by login from R0.5. */
export default function InternalLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className={`${instrumentSans.variable} antialiased`}>
      <body className="min-h-screen bg-slate-100 font-sans text-slate-900">
        <header className="border-b border-slate-200 bg-white">
          <div className="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
            <Brand href="/status" subtitle="Spatial Data Platform" />
            <div className="ml-auto">
              <LiveIndicator />
            </div>
          </div>
        </header>
        {children}
      </body>
    </html>
  );
}
