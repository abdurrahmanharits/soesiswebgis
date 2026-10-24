import Link from "next/link";

/** "S" mark + product name, as in the Laravel layouts. */
export function Brand({ href, subtitle }: { href: string; subtitle: string }) {
  return (
    <Link href={href} className="flex items-center gap-3">
      <span className="grid size-9 place-items-center rounded-md bg-emerald-700 text-sm font-semibold text-white" aria-hidden="true">
        S
      </span>
      <span className="leading-tight">
        <span className="block text-sm font-semibold tracking-tight">SoESIS WebGIS</span>
        <span className="block text-xs text-slate-500">{subtitle}</span>
      </span>
    </Link>
  );
}
