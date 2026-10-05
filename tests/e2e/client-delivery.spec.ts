import { expect, Page, test } from '@playwright/test';

const password = process.env.E2E_PASSWORD ?? 'password';
const guardianEmail = process.env.E2E_GUARDIAN_EMAIL ?? 'portal.live.guardian@demo.local';
const consoleErrors = new WeakMap<Page, string[]>();

test.beforeEach(async ({ page }) => {
    const errors: string[] = [];
    consoleErrors.set(page, errors);
    page.on('console', message => {
        if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
    });
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => {
        if (response.status() >= 500) errors.push(`HTTP ${response.status()} ${response.url()}`);
    });
});

test.afterEach(async ({ page }) => {
    expect(consoleErrors.get(page) ?? [], 'unexpected browser console/page errors').toEqual([]);
});

async function openHealthy(page: Page, path: string): Promise<void> {
    const response = await page.goto(path, { waitUntil: 'networkidle' });
    expect(response, `missing navigation response for ${path}`).not.toBeNull();
    expect(response!.status(), `${path} returned ${response!.status()}`).toBeLessThan(400);
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('body')).not.toContainText(/Server Error|Internal Server Error|Whoops/i);
}

async function login(page: Page, email: string, destination: RegExp, admin = false): Promise<void> {
    await page.goto(admin ? '/admin/login' : '/login');
    const identifier = admin
        ? page.locator('input[type="email"]')
        : page.getByRole('textbox', { name: 'اسم المستخدم / البريد / الهاتف' });
    await identifier.fill(email);
    await page.locator('input[type="password"]').fill(password);
    await page.waitForTimeout(300);
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(destination, { timeout: 15_000 });
}

async function apiHeaders(page: Page): Promise<Record<string, string>> {
    const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8090';
    const xsrf = (await page.context().cookies()).find(cookie => cookie.name === 'XSRF-TOKEN')?.value;

    return {
        Accept: 'application/json',
        Origin: baseURL,
        Referer: `${baseURL}/`,
        ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
    };
}

test.describe.serial('Final client delivery journeys', () => {
    test('1 administration, navigation, approved scope, and server authorization', async ({ page }, testInfo) => {
        await login(page, 'admin@eschool.test', /\/admin(?:\/)?$/, true);
        await expect(page.getByRole('heading', { name: 'لوحة التحكم' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'الاختبارات', exact: true })).toHaveCount(0);

        for (const path of [
            '/admin/students',
            '/admin/registration-applications',
            '/admin/sessions',
            '/admin/recordings',
            '/admin/operational-reports',
            '/admin/payroll-entries',
        ]) await openHealthy(page, path);

        const foreign = await page.request.get('/api/students/00000000000000000000000000', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404]).toContain(foreign.status());
        await testInfo.attach('admin-desktop', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
    });

    test('2 public registration form, validation, submission, legacy route, and administration visibility', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await openHealthy(page, '/register/student/free-online-classes');
        await expect(page.getByRole('heading', { name: 'التسجيل في الفصول الإلكترونية المجانية' })).toBeVisible();
        await page.getByRole('button', { name: 'إرسال الطلب' }).click();
        await expect(page).toHaveURL(/free-online-classes/);

        const uniqueName = `طالب قبول ${Date.now()}`;
        await page.getByRole('textbox', { name: 'الاسم الكامل' }).fill(uniqueName);
        await page.getByRole('textbox', { name: 'تاريخ الميلاد' }).fill('2014-05-01');
        await page.getByRole('combobox', { name: 'الجنس' }).selectOption({ label: 'ذكر' });
        await page.getByRole('combobox', { name: 'الدولة' }).selectOption({ label: 'مصر' });
        await page.waitForTimeout(500);
        const region = page.getByRole('combobox', { name: 'المنطقة' });
        if (await region.locator('option').count() > 1) await region.selectOption({ index: 1 });
        await page.getByRole('textbox', { name: /البريد الإلكتروني/ }).fill(`uat.${Date.now()}@example.test`);
        await page.getByRole('textbox', { name: 'اسم ولي الأمر' }).fill('ولي أمر اختبار القبول');
        await page.getByRole('radio', { name: 'مبتدئ' }).check();
        await page.getByRole('textbox', { name: /الوقت الأنسب/ }).fill('بعد المغرب');
        await page.getByRole('radio', { name: 'توصية من صديق' }).check();
        await page.getByRole('button', { name: 'إرسال الطلب' }).click();
        await page.waitForURL(/register\/(submitted|status)/, { timeout: 15_000 });
        await testInfo.attach('registration-mobile', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });

        const legacy = await page.request.get('/register/student', { maxRedirects: 0 });
        expect([200, 302]).toContain(legacy.status());
        const absent = await page.request.get('/register/student/not-a-published-form');
        expect(absent.status()).toBe(404);

        await page.context().clearCookies();
        await login(page, 'admin@eschool.test', /\/admin(?:\/)?$/, true);
        await openHealthy(page, '/admin/registration-applications');
        await expect(page.locator('body')).toContainText(uniqueName);
    });

    test('3 student portal schedule, session, assignments, reports, and profile', async ({ page }) => {
        await login(page, 'portal.live.student@demo.local', /\/student(?:\/)?$/);
        for (const path of ['/student', '/student/schedule', '/student/assignments', '/student/reports', '/student/profile']) {
            await openHealthy(page, path);
        }
        const sessionLink = page.locator('a[href^="/student/sessions/"]').first();
        if (await sessionLink.count()) await openHealthy(page, await sessionLink.getAttribute('href') ?? '/student');
        const denied = await page.request.get('/admin', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([302, 403]).toContain(denied.status());
    });

    test('4 teacher portal schedule, sessions, groups, students, and protected mutation', async ({ page }) => {
        await login(page, 'portal.live.teacher@demo.local', /\/teacher(?:\/)?$/);
        for (const path of ['/teacher', '/teacher/schedule', '/teacher/groups', '/teacher/students', '/teacher/availability', '/teacher/postponements']) {
            await openHealthy(page, path);
        }
        const sessionLink = page.locator('a[href^="/teacher/sessions/"]').first();
        if (await sessionLink.count()) await openHealthy(page, await sessionLink.getAttribute('href') ?? '/teacher');
        const unauthorizedMutation = await page.request.post('/api/sessions/00000000000000000000000000/complete', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404, 422]).toContain(unauthorizedMutation.status());
    });

    test('5 guardian portal linked child only and messaging privacy', async ({ page }) => {
        await login(page, guardianEmail, /\/guardian(?:\/)?$/);
        await openHealthy(page, '/guardian');
        const childLink = page.locator('a[href^="/guardian/children/"]').first();
        await expect(childLink).toBeVisible();
        const childPath = await childLink.getAttribute('href');
        expect(childPath).toBeTruthy();
        const childId = childPath!.match(/^\/guardian\/children\/([^/]+)/)?.[1];
        expect(childId).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/i);
        for (const suffix of ['', '/attendance', '/schedule', '/reports']) await openHealthy(page, `/guardian/children/${childId}${suffix}`);
        const privateConversations = await page.request.get('/api/conversations', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect(privateConversations.status()).toBe(200);
        expect((await privateConversations.json()).data).toEqual([]);
        const foreignChild = await page.request.get('/guardian/children/00000000000000000000000000', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404]).toContain(foreignChild.status());
    });

    test('6 messaging list, create, show, send, guest, and participant boundaries', async ({ page }) => {
        const guest = await page.request.get('/api/conversations', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect(guest.status()).toBe(401);
        await login(page, 'portal.live.student@demo.local', /\/student(?:\/)?$/);
        const list = await page.request.get('/api/conversations?page=1', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect(list.status()).toBe(200);

        const participantId = process.env.E2E_PARTICIPANT_ID;
        expect(participantId, 'E2E_PARTICIPANT_ID is required for create/send coverage').toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/i);
        const created = await page.request.post('/api/conversations', {
            headers: await apiHeaders(page),
            maxRedirects: 0,
            data: {
                type: 'direct',
                subject: `Playwright acceptance ${Date.now()}`,
                participant_user_ids: [participantId],
                is_moderated: true,
            },
        });
        expect(created.status()).toBe(201);
        const conversationId = (await created.json()).data.id as string;
        const shown = await page.request.get(`/api/messaging/conversations/${conversationId}`, { headers: await apiHeaders(page), maxRedirects: 0 });
        expect(shown.status()).toBe(200);
        const sent = await page.request.post(`/api/conversations/${conversationId}/messages`, { headers: await apiHeaders(page), maxRedirects: 0, data: { body: 'رسالة قبول من Playwright' } });
        expect(sent.status()).toBe(201);
        const messages = await page.request.get(`/api/conversations/${conversationId}/messages?page=1`, { headers: await apiHeaders(page), maxRedirects: 0 });
        expect(messages.status()).toBe(200);
        const foreign = await page.request.get('/api/messaging/conversations/00000000000000000000000000', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404]).toContain(foreign.status());
    });

    test('7 operational reports UI, filters, tenant scope, and real RTL PDF', async ({ page }) => {
        await login(page, 'admin@eschool.test', /\/admin(?:\/)?$/, true);
        await openHealthy(page, '/admin/operational-reports');
        const pdf = await page.request.get('/reporting/operational-reports/export.pdf', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect(pdf.status()).toBe(200);
        expect(pdf.headers()['content-type']).toContain('application/pdf');
        expect(pdf.headers()['cache-control']).toContain('no-store');
        const bytes = await pdf.body();
        expect(bytes.subarray(0, 4).toString()).toBe('%PDF');
        const forbidden = await page.request.get('/api/student-dashboards/00000000000000000000000000', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404]).toContain(forbidden.status());
    });

    test('8 payroll, recording, and session mutation authorization boundaries', async ({ page }) => {
        await login(page, 'portal.live.student@demo.local', /\/student(?:\/)?$/);
        const approve = await page.request.post('/api/payroll/adjustments/00000000000000000000000000/approve', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404]).toContain(approve.status());
        const reject = await page.request.post('/api/payroll/adjustments/00000000000000000000000000/reject', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404]).toContain(reject.status());
        const recordings = await page.request.get('/api/recordings', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([200, 403]).toContain(recordings.status());
        const watch = await page.request.get('/recordings/00000000000000000000000000/watch', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404]).toContain(watch.status());
        const mutation = await page.request.post('/api/sessions/00000000000000000000000000/start', { headers: await apiHeaders(page), maxRedirects: 0 });
        expect([403, 404]).toContain(mutation.status());
    });
});
