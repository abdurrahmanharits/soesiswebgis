import type { Metadata } from "next";
import { Suspense } from "react";

import { LiveIndicator } from "@/features/system/ui/live-indicator";
import { Brand } from "@/shared/ui/brand";
import { InternalNav } from "@/shared/session/internal-nav";
import { SessionExpiredBanner } from "@/shared/session/session-expired-banner";
import { SessionProvider } from "@/shared/session/session-provider";
import { UserMenu } from "@/shared/session/user-menu";

import { instrumentSans } from "../fonts";
import "../globals.css";

export const metadata: Metadata = {
  title: { default: "SoESIS WebGIS", template: "%s · SoESIS WebGIS" },
};

/**
 * Root layout of the internal area (English, sign-in required).
 * proxy.ts sends visitors without a session cookie to /login; FastAPI decides every request.
 */
export default function InternalLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className={`${instrumentSans.variable} antialiased`}>
      <body className="min-h-screen bg-slate-100 font-sans text-slate-900">
        {/* The session reads the current path (also of dynamic routes), so it streams in after the static shell. */}
        <Suspense fallback={<Header />}>
          <SessionProvider>
            <SessionExpiredBanner />
            <Header>
              <InternalNav />
              <div className="ml-auto flex items-center gap-4">
                <LiveIndicator />
                <UserMenu />
              </div>
            </Header>
            {children}
          </SessionProvider>
        </Suspense>
      </body>
    </html>
  );
}

function Header({ children }: { children?: React.ReactNode }) {
  return (
    <header className="border-b border-slate-200 bg-white">
      <div className="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
        <Brand href="/status" subtitle="Spatial Data Platform" />
        {children}
      </div>
    </header>
  );
}
