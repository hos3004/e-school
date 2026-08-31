import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [
        ['line'],
        ['html', { outputFolder: process.env.PLAYWRIGHT_HTML_REPORT ?? '/tmp/eschool-playwright-report', open: 'never' }],
    ],
    outputDir: process.env.PLAYWRIGHT_OUTPUT_DIR ?? '/tmp/eschool-playwright-results',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8090',
        locale: 'ar-EG',
        timezoneId: 'Africa/Cairo',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
        ...devices['Desktop Chrome'],
        launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH
            ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH }
            : undefined,
    },
});
