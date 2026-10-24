import { expect, test } from "@playwright/test";

import { requireAccounts, signIn } from "./helpers";

test.describe("R0 foundation", () => {
  test("public landing renders in Indonesian", async ({ page }) => {
    await page.goto("/");

    await expect(page.locator("html")).toHaveAttribute("lang", "id");
    await expect(page.getByRole("heading", { level: 1, name: "SoESIS WebGIS" })).toBeVisible();
    await expect(page.getByText("Katalog Data Spasial")).toBeVisible();
  });

  test("status page shows backend readiness rendered server-side", async ({ page }) => {
    requireAccounts();
    await signIn(page, "curator");

    await expect(page.locator("html")).toHaveAttribute("lang", "en");
    await expect(page.getByTestId("readiness")).toHaveAttribute("data-status", "ok");
    await expect(page.getByTestId("component-database")).toContainText("postgis: 3.4");
    await expect(page.getByTestId("component-redis")).toContainText("OK");
  });

  test("browser reaches FastAPI through the same-origin /api proxy", async ({ page }) => {
    requireAccounts();
    const errors: string[] = [];
    page.on("pageerror", (error) => errors.push(error.message));

    await signIn(page, "curator");

    await expect(page.getByTestId("live-indicator")).toHaveAttribute("data-state", "up");
    expect(errors).toEqual([]);
  });

  test("/api proxy returns the backend contract", async ({ request }) => {
    const response = await request.get("/api/health");

    expect(response.status()).toBe(200);
    expect(await response.json()).toEqual({ status: "ok" });
  });
});
