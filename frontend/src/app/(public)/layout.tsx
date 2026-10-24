import type { Metadata } from "next";

import { Brand } from "@/shared/ui/brand";

import { instrumentSans } from "../fonts";
import "../globals.css";

export const metadata: Metadata = {
  title: { default: "SoESIS WebGIS", template: "%s · SoESIS WebGIS" },
  description: "Katalog dataset spasial SoESIS WebGIS yang telah dikurasi.",
};

/** Root layout of the public area (Indonesian). Internal pages use (internal)/layout.tsx. */
export default function PublicLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="id" className={`${instrumentSans.variable} antialiased`}>
      <body className="min-h-screen bg-slate-100 font-sans text-slate-900">
        <header className="border-b border-slate-200 bg-white">
          <div className="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
            <Brand href="/" subtitle="Katalog Data Spasial" />
          </div>
        </header>
        {children}
      </body>
    </html>
  );
}
