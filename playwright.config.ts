import { defineConfig } from "@playwright/test";

const artifactRoot =
  process.env.E2E_ARTIFACT_DIR ??
  process.env.PLAYWRIGHT_OUTPUT_DIR ??
  "/tmp/e-school-client-uat/playwright";
const executablePath =
  process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE ??
  process.env.PLAYWRIGHT_CHROMIUM_PATH;

export default defineConfig({
  testDir: "./tests/e2e",
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  outputDir: `${artifactRoot}/results`,
  reporter: [
    ["list"],
    ["html", { open: "never", outputFolder: `${artifactRoot}/report` }],
  ],
  use: {
    baseURL:
      process.env.E2E_BASE_URL ??
      process.env.PLAYWRIGHT_BASE_URL ??
      "http://127.0.0.1:8011",
    locale: "ar-EG",
    timezoneId: "Africa/Cairo",
    screenshot: "only-on-failure",
    trace: "retain-on-failure",
    video: "off",
    launchOptions: executablePath ? { executablePath } : undefined,
  },
  projects: [
    {
      name: "desktop-rtl",
      use: { viewport: { width: 1440, height: 1000 } },
    },
    {
      name: "tablet-rtl",
      use: { viewport: { width: 1024, height: 768 } },
    },
    {
      name: "mobile-rtl",
      use: { viewport: { width: 390, height: 844 } },
    },
  ],
});
