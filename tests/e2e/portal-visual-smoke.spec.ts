import { expect, test, type Page, type TestInfo } from '@playwright/test';

const studentLogin =
    process.env.E2E_STUDENT_LOGIN ?? 'portal.live.student@demo.local';
const teacherLogin =
    process.env.E2E_TEACHER_LOGIN ?? 'portal.live.teacher@demo.local';
const portalPassword = process.env.E2E_PORTAL_PASSWORD ?? 'password';
const adminLogin = process.env.E2E_ADMIN_LOGIN ?? 'admin@eschool.test';
const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'password';

async function login(page: Page, identifier: string, password: string) {
    await page.goto('/login');
    await page.locator('input[name="login"]').fill(identifier);
    await page.locator('input[name="password"]').fill(password);
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
}

async function expectNoPageOverflow(page: Page) {
    const overflows = await page.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth + 1,
    );
    expect(overflows).toBe(false);
}

async function capture(page: Page, testInfo: TestInfo, name: string) {
    await page.screenshot({
        animations: 'disabled',
        fullPage: true,
        path: testInfo.outputPath(`${name}.png`),
    });
}

test('student dashboard, schedule, assignment, notifications, and LTR', async ({
    page,
}, testInfo) => {
    await login(page, studentLogin, portalPassword);
    await expect(page).toHaveURL(/\/student(?:\/)?$/);
    await page.locator('#app-locale').selectOption('ar');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expectNoPageOverflow(page);
    await capture(page, testInfo, 'student-dashboard');

    for (const route of [
        '/student/schedule',
        '/student/assignments',
        '/student/notifications',
    ]) {
        await page.goto(route);
        await expect(page.locator('main')).toBeVisible();
        await expectNoPageOverflow(page);
    }

    if (testInfo.project.name === 'desktop-rtl') {
        await page.goto('/student');
        await page.locator('#app-locale').selectOption('en');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await capture(page, testInfo, 'student-dashboard-ltr');
        await page.locator('#app-locale').selectOption('ar');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    }
});

test('teacher dashboard, schedule, and session workflow shell', async ({
    page,
}, testInfo) => {
    await login(page, teacherLogin, portalPassword);
    await expect(page).toHaveURL(/\/teacher(?:\/)?$/);
    await expect(page.locator('main')).toBeVisible();
    await expectNoPageOverflow(page);
    await capture(page, testInfo, 'teacher-dashboard');

    await page.goto('/teacher/schedule');
    await expect(page.locator('main')).toBeVisible();
    await expectNoPageOverflow(page);

    const sessionLink = page
        .locator('a[href^="/teacher/sessions/"]:visible')
        .first();
    if (await sessionLink.count()) {
        const sessionHref = await sessionLink.getAttribute('href');
        expect(sessionHref).toBeTruthy();
        await page.goto(sessionHref!);
        await expect(page).toHaveURL(/\/teacher\/sessions\//);
        await expect(page.locator('main')).toBeVisible();
        await expectNoPageOverflow(page);
        await capture(page, testInfo, 'teacher-session');
    }
});

test('public registration form is responsive', async ({ page }, testInfo) => {
    await page.goto('/register/student');
    await expect(page.locator('main')).toBeVisible();
    await expectNoPageOverflow(page);
    await capture(page, testInfo, 'public-registration');
});

test('admin dashboard, resource table, form, and operational reports', async ({
    page,
}, testInfo) => {
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(adminLogin);
    await page.locator('input[type="password"]').fill(adminPassword);
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/\/admin(?:\/)?$/);
    await expectNoPageOverflow(page);
    await capture(page, testInfo, 'admin-dashboard');

    await page.goto('/admin/users');
    await expect(page.locator('main')).toBeVisible();
    await expectNoPageOverflow(page);

    await page.goto('/admin/users/create');
    await expect(page.locator('#form')).toBeVisible();
    await expectNoPageOverflow(page);

    await page.goto('/admin/operational-reports');
    await expect(page.locator('main')).toBeVisible();
    await expectNoPageOverflow(page);
    await capture(page, testInfo, 'admin-operational-reports');
});
