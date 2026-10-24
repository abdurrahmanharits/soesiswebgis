import { expect, test } from "@playwright/test";

import { PASSWORD, requireAccounts, signIn } from "./helpers";

test.describe("R0.5 authentication & roles", () => {
  test.beforeEach(() => requireAccounts());

  test("guests are sent to /login and come back after signing in", async ({ page }) => {
    await page.goto("/admin/users");
    await expect(page).toHaveURL(/\/login\?next=%2Fadmin%2Fusers$/);

    await page.getByLabel("E-mail or username").fill("qa.admin@example.invalid");
    await page.getByLabel("Password").fill(PASSWORD);
    await page.getByRole("button", { name: "Sign in" }).click();

    await expect(page).toHaveURL(/\/admin\/users$/);
    await expect(page.getByTestId("users-table")).toContainText("qa.uploader");
  });

  test("invalid credentials show the generic message", async ({ page }) => {
    await page.goto("/login");
    await page.getByLabel("E-mail or username").fill("qa.curator");
    await page.getByLabel("Password").fill("definitely-wrong");
    await page.getByRole("button", { name: "Sign in" }).click();

    await expect(page.getByTestId("login-error")).toHaveText("The provided credentials are invalid.");
    await expect(page).toHaveURL(/\/login$/);
  });

  test("header shows account and role; non-admins get no admin pages", async ({ page }) => {
    for (const [role, label] of [["uploader", "Uploader"], ["curator", "Curator"]] as const) {
      await page.context().clearCookies();
      await signIn(page, role);

      await expect(page.getByTestId("user-menu")).toContainText(`QA ${label}`);
      await expect(page.getByTestId("user-menu")).toContainText(label);
      await expect(page.getByRole("link", { name: "Users" })).toHaveCount(0);

      await page.goto("/admin/users");
      await expect(page.getByTestId("forbidden")).toBeVisible();
      // The backend enforces it too, regardless of the UI.
      const api = await page.request.get("/api/admin/users");
      expect(api.status()).toBe(403);
    }
  });

  test("admin manages users and organizations", async ({ page }) => {
    await signIn(page, "admin", "/admin/organizations");
    const organization = `QA Org ${Date.now()}`;
    await page.getByPlaceholder("New organization").fill(organization);
    await page.getByRole("button", { name: "Add" }).click();
    await expect(page.getByTestId("organizations").locator(`input[value="${organization}"]`)).toBeVisible();

    await page.getByRole("link", { name: "Users" }).click();
    const username = `qa.e2e.${Date.now()}`;
    const form = page.getByTestId("create-user-form");
    await form.getByLabel("Name", { exact: true }).fill("QA E2E");
    await form.getByLabel("Username").fill(username);
    await form.getByLabel("E-mail").fill(`${username}@example.invalid`);
    await form.getByLabel("Organization").selectOption({ label: organization });
    await form.getByLabel("Password", { exact: true }).fill("long-enough-1");
    await form.getByLabel("Repeat password").fill("long-enough-1");
    await form.getByRole("button", { name: "Create user" }).click();
    await expect(page.getByRole("status")).toHaveText(`User ${username} created.`);

    // Deactivate the new account.
    await page.getByRole("row", { name: new RegExp(username) }).getByRole("link", { name: "Edit" }).click();
    await page.getByTestId("edit-user-form").getByLabel("Active (can sign in)").uncheck();
    await page.getByRole("button", { name: "Save changes" }).click();
    await expect(page.getByRole("row", { name: new RegExp(username) })).toContainText("Inactive");

    // An admin cannot demote themselves.
    await page.getByRole("row", { name: /QA Admin/ }).getByRole("link", { name: "Edit" }).click();
    await page.getByTestId("edit-user-form").getByLabel("Role").selectOption("curator");
    await page.getByRole("button", { name: "Save changes" }).click();
    await expect(page.getByText("You cannot remove your own admin access.")).toBeVisible();
  });

  test("an expired access token is refreshed silently", async ({ page, context }) => {
    await signIn(page, "curator");

    await context.clearCookies({ name: "soesis_access" });
    await page.reload();

    await expect(page.getByTestId("user-menu")).toContainText("QA Curator");
    const cookies = await context.cookies();
    expect(cookies.some((c) => c.name === "soesis_access")).toBe(true);
  });

  test("an ended session shows the banner and keeps the form input", async ({ page, context }) => {
    await signIn(page, "admin", "/admin/users");
    const form = page.getByTestId("create-user-form");
    await form.getByLabel("Name", { exact: true }).fill("Unsaved draft");

    // Both tokens gone (e.g. logged out elsewhere); the "signed in" marker is still there.
    await context.clearCookies({ name: "soesis_access" });
    await context.clearCookies({ name: "soesis_refresh" });
    await form.getByLabel("Username").fill("draft.user");
    await form.getByLabel("E-mail").fill("draft@example.invalid");
    await form.getByLabel("Password", { exact: true }).fill("long-enough-1");
    await form.getByLabel("Repeat password").fill("long-enough-1");
    await form.getByRole("button", { name: "Create user" }).click();

    await expect(page.getByTestId("session-expired")).toContainText("Session expired. Please sign in again.");
    await expect(form.getByLabel("Name", { exact: true })).toHaveValue("Unsaved draft");
  });

  test("logout ends access", async ({ page }) => {
    await signIn(page, "uploader");

    await page.getByRole("button", { name: "Log out" }).click();
    await expect(page).toHaveURL(/\/login$/);

    await page.goto("/status");
    await expect(page).toHaveURL(/\/login\?next=%2Fstatus$/);
    expect((await page.request.get("/api/auth/me")).status()).toBe(401);
  });

  test("public area stays open and links to sign-in", async ({ page }) => {
    await page.goto("/");
    await expect(page.getByRole("link", { name: "Masuk" })).toHaveAttribute("href", "/login");

    await signIn(page, "uploader");
    await page.goto("/");
    await expect(page.getByRole("link", { name: "Area internal" })).toBeVisible();
  });
});
