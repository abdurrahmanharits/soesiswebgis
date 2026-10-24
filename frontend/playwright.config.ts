import { defineConfig } from "@playwright/test";

/**
 * Browser smoke tests against a production build (`npm run build` first).
 * Uses the locally installed Chrome, so no browser download is needed.
 * The FastAPI backend must be running (docker compose up -d backend).
 */
// E2E_PORT: use another port when 3000 is taken by a different local app.
const port = Number(process.env.E2E_PORT ?? 3000);
const baseURL = `http://127.0.0.1:${port}`;

export default defineConfig({
  testDir: "./tests/e2e",
  fullyParallel: true,
  retries: 0,
  // Sign-in hashes with bcrypt cost 12 (same as Laravel): ~1–2 s per login in Docker on Windows.
  expect: { timeout: 15_000 },
  reporter: [["list"]],
  use: {
    baseURL,
    channel: "chrome",
    headless: true,
  },
  webServer: {
    command: `npx next start -H 127.0.0.1 -p ${port}`,
    url: baseURL,
    // Never test whatever else happens to listen on the port.
    reuseExistingServer: false,
    timeout: 60_000,
  },
});
