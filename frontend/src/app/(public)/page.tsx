import type { Metadata } from "next";

export const metadata: Metadata = { title: "Beranda" };

/** Public landing page (same copy as the Laravel version). Catalogue links arrive with R5. */
export default function LandingPage() {
  return (
    <main className="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
      <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">SoESIS WebGIS</h1>
      <p className="mt-4 text-base leading-relaxed text-slate-600">
        Platform data spasial untuk menelusuri dan melihat dataset yang telah ditinjau dan disetujui.
        Setiap dataset dilengkapi metadata, sumber, dan lisensi, serta dapat dibuka langsung di peta.
      </p>
    </main>
  );
}
