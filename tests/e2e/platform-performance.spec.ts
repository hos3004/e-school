import { expect, test } from "@playwright/test";

test.describe("isolated platform performance review", () => {
  test.skip(process.env.PERFORMANCE_BROWSER_REVIEW !== "1", "Requires isolated synthetic accounts.");

  for (const kind of ["student", "teacher", "platform_admin"]) {
    test(`${kind} login, translated navigation, and session-authenticated notifications`, async ({ page }) => {
      const errors: string[] = [];
      page.on("pageerror", error => errors.push(error.message));
      await page.goto("/login");
      await page.locator('input[name="login"]').fill("performance." + kind);
      await page.locator('input[name="password"]').fill("Performance-QA!2026");
      await page.locator('button[type="submit"]').click();
      await expect(page).toHaveURL(kind === "platform_admin" ? /\/manage$/ : new RegExp("/learn/" + kind + "$"));
      const status = await page.evaluate(async () => {
        const response = await fetch("/api/notifications/unread-count", {
          credentials: "same-origin", headers: { Accept: "application/json" },
        });
        return { status: response.status, data: await response.json() };
      });
      expect(status).toEqual({ status: 200, data: { data: { unread_count: 0 } } });
      const prefix = kind === "platform_admin" ? "/manage" : "/learn/" + kind;
      for (const suffix of kind === "platform_admin" ? ["/notifications"] : ["/notifications", "/profile", "/schedule"]) {
        await page.goto(prefix + suffix);
        await expect(page.locator("body")).not.toContainText(/(?:learning|console_people|console_profiles|notifications)\.[a-z_]+/);
      }
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      expect(errors).toEqual([]);
      await page.context().clearCookies();
      const expired = await page.evaluate(async () => (await fetch("/api/notifications/unread-count", { credentials: "omit", headers: { Accept: "application/json" } })).status);
      expect(expired).toBe(401);
    });
  }

  test("login renders RTL without missing translations", async ({ page }) => {
    await page.goto("/login");
    await expect(page.locator("html")).toHaveAttribute("dir", "rtl");
    await expect(page.locator("body")).not.toContainText("learning.entry.");
  });
});
