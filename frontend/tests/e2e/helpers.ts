import { expect, test, type Page } from "@playwright/test";

/**
 * Temporary QA accounts (qa.uploader, qa.curator, qa.admin) are created before a run and
 * deleted afterwards; their password comes from E2E_PASSWORD and is never committed.
 */
export const PASSWORD = process.env.E2E_PASSWORD ?? "";

export function requireAccounts() {
  test.skip(!PASSWORD, "E2E_PASSWORD is not set (QA accounts are created for the run)");
}

export async function signIn(page: Page, role: "uploader" | "curator" | "admin", next = "/status") {
  await page.goto(`/login?next=${encodeURIComponent(next)}`);
  await page.getByLabel("E-mail or username").fill(`QA.${role.toUpperCase()}`); // case-insensitive
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(new RegExp(`${next.replace(/[/]/g, "\\/")}$`));
  await expect(page.getByTestId("user-menu")).toBeVisible();
}
