import type { NextConfig } from "next";

// Server-side only: where the FastAPI backend listens (never exposed to the browser).
const backendUrl = process.env.BACKEND_URL ?? "http://127.0.0.1:8001";

const nextConfig: NextConfig = {
  cacheComponents: true,
  partialPrefetching: true,
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  // One origin for the browser: /api/* is proxied to FastAPI (decision 12B),
  // so there is no CORS and auth cookies stay first-party.
  async rewrites() {
    return [{ source: "/api/:path*", destination: `${backendUrl}/api/:path*` }];
  },
};

export default nextConfig;
