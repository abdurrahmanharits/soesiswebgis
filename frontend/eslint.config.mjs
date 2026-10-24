import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";
import boundaries from "eslint-plugin-boundaries";

// Architecture (see README): app → features/<f>/ui → hooks → services → shared/api.
// A feature never imports another feature; only shared/api talks to the backend.
const sameFeature = { feature: "{{ from.element.captured.feature }}" };

const architecture = {
  files: ["src/**/*.{ts,tsx}"],
  plugins: { boundaries },
  settings: {
    "boundaries/elements": [
      { type: "app", pattern: "src/app" },
      { type: "feature-ui", pattern: "src/features/*/ui", capture: ["feature"] },
      { type: "feature-hooks", pattern: "src/features/*/hooks", capture: ["feature"] },
      { type: "feature-services", pattern: "src/features/*/services", capture: ["feature"] },
      { type: "shared-api", pattern: "src/shared/api" },
      { type: "shared-ui", pattern: "src/shared/ui" },
      { type: "shared-map", pattern: "src/shared/map" },
    ],
    "import/resolver": { typescript: { alwaysTryTypes: true } },
  },
  rules: {
    "boundaries/dependencies": [2, {
      default: "disallow",
      policies: [
        // Routing/layout only: pages compose feature UI and shared UI.
        { from: { element: { type: "app" } }, allow: { to: { element: { types: { anyOf: ["feature-ui", "shared-ui"] } } } } },
        // UI may use its own feature's hooks/services (server components call services directly).
        { from: { element: { type: "feature-ui" } }, allow: { to: { element: { types: { anyOf: ["feature-ui", "feature-hooks", "feature-services"] }, captured: sameFeature } } } },
        { from: { element: { type: "feature-ui" } }, allow: { to: { element: { types: { anyOf: ["shared-ui", "shared-map"] } } } } },
        { from: { element: { type: "feature-hooks" } }, allow: { to: { element: { types: { anyOf: ["feature-hooks", "feature-services"] }, captured: sameFeature } } } },
        { from: { element: { type: "feature-services" } }, allow: { to: { element: { type: "feature-services", captured: sameFeature } } } },
        { from: { element: { type: "feature-services" } }, allow: { to: { element: { type: "shared-api" } } } },
        { from: { element: { type: "shared-api" } }, allow: { to: { element: { type: "shared-api" } } } },
        { from: { element: { type: "shared-ui" } }, allow: { to: { element: { type: "shared-ui" } } } },
        { from: { element: { type: "shared-map" } }, allow: { to: { element: { type: "shared-map" } } } },
        // Third-party packages are fine everywhere (database drivers are banned below).
        { allow: { to: { module: { origin: "external" } } } },
        { allow: { to: { module: { origin: "core" } } } },
      ],
    }],
    // The frontend never talks to the database, Redis or the file store directly.
    "no-restricted-imports": ["error", {
      patterns: [{
        group: ["pg", "pg-*", "postgres", "@prisma/*", "prisma", "drizzle-orm*", "kysely", "redis", "ioredis", "@neondatabase/*", "mysql*", "sqlite*", "better-sqlite3"],
        message: "The frontend reaches data only through the backend API (src/shared/api).",
      }],
    }],
  },
};

// Only the API client may call fetch; everything else goes through it.
const fetchOnlyInApiClient = {
  files: ["src/**/*.{ts,tsx}"],
  ignores: ["src/shared/api/**"],
  rules: {
    "no-restricted-globals": ["error", { name: "fetch", message: "Use the typed client in src/shared/api." }],
  },
};

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  architecture,
  fetchOnlyInApiClient,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
    // Generated from contracts/openapi.json.
    "src/shared/api/schema.ts",
  ]),
]);

export default eslintConfig;
