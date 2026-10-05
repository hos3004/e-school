import { expect, test, type Page } from "@playwright/test";
import { readFileSync } from "node:fs";
import ts from "typescript";

const polling = ts.transpileModule(
  readFileSync("resources/js/lib/notificationPolling.ts", "utf8").replace(/\bexport /g, ""),
  { compilerOptions: { target: ts.ScriptTarget.ES2022 } },
).outputText;

async function setup(page: Page) {
  await page.route("http://performance.test/", route => route.fulfill({ body: "<html></html>", contentType: "text/html" }));
  await page.goto("http://performance.test/");
  await page.clock.install();
  await page.addScriptTag({ content: polling + `
    window.counts = []; window.expired = 0;
    window.stopPoll = startNotificationPolling(
      value => window.counts.push(value),
      () => window.expired++
    );` });
  await page.clock.runFor(1);
}

async function visible(page: Page, value: boolean) {
  await page.evaluate(value => {
    Object.defineProperty(document, "visibilityState", { configurable: true, value: value ? "visible" : "hidden" });
    document.dispatchEvent(new Event("visibilitychange"));
  }, value);
}

for (const status of [401, 403, 419]) {
  test(`stops polling after session rejection ${status}`, async ({ page }) => {
    let requests = 0;
    await page.route("**/api/notifications/unread-count", route => {
      requests++;
      return route.fulfill({ status, json: {} });
    });
    await setup(page);
    await expect.poll(() => page.evaluate("window.expired")).toBe(1);
    await page.clock.runFor(600_000);
    await visible(page, false);
    await visible(page, true);
    await page.clock.runFor(60_000);
    expect(requests).toBe(1);
  });
}

test("pauses hidden tabs, resumes visible tabs, and cleans up on unmount", async ({ page }) => {
  let requests = 0;
  await page.route("**/api/notifications/unread-count", route => {
    requests++;
    return route.fulfill({ json: { data: { unread_count: 7 } } });
  });
  await setup(page);
  await expect.poll(() => page.evaluate("window.counts.length")).toBe(1);
  await visible(page, false);
  await page.clock.runFor(120_000);
  expect(requests).toBe(1);
  await visible(page, true);
  await page.clock.runFor(1);
  await expect.poll(() => page.evaluate("window.counts.length")).toBe(2);
  await page.evaluate("window.stopPoll()");
  await page.clock.runFor(120_000);
  expect(requests).toBe(2);
});

test("uses exponential backoff, honors Retry-After, and resets after recovery", async ({ page }) => {
  let requests = 0;
  await page.route("**/api/notifications/unread-count", route => {
    requests++;
    if (requests === 1) return route.fulfill({ status: 500, json: {} });
    if (requests === 2) return route.fulfill({ status: 429, headers: { "Retry-After": "180" }, json: {} });
    return route.fulfill({ json: { data: { unread_count: 2 } } });
  });
  await setup(page);
  await expect.poll(() => requests).toBe(1);
  await page.waitForTimeout(10);
  await page.clock.runFor(59_000);
  expect(requests).toBe(1);
  await page.clock.runFor(1_100);
  await expect.poll(() => requests).toBe(2);
  await page.waitForTimeout(10);
  await page.clock.runFor(179_000);
  expect(requests).toBe(2);
  await page.clock.runFor(1_100);
  await expect.poll(() => page.evaluate("window.counts.length")).toBe(1);
  await page.clock.runFor(30_100);
  await expect.poll(() => requests).toBe(4);
});

test("aborts a hidden in-flight request and does not publish stale data", async ({ page }) => {
  let requests = 0;
  await page.route("**/api/notifications/unread-count", async route => {
    requests++;
    if (requests === 1) {
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    await route.fulfill({ json: { data: { unread_count: 8 } } }).catch(() => {});
  });
  await setup(page);
  await expect.poll(() => requests).toBe(1);
  await visible(page, false);
  await page.clock.runFor(60_000);
  expect(await page.evaluate("window.counts.length")).toBe(0);
  expect(requests).toBe(1);
  await visible(page, true);
  await page.clock.runFor(1);
  await expect.poll(() => page.evaluate("window.counts.length")).toBe(1);
  expect(requests).toBe(2);
});
