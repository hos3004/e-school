<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Reporting\Domain\Exceptions\ReportPdfRenderingException;
use Modules\Reporting\Infrastructure\Pdf\MpdfReportPdfRenderer;
use Psr\Log\LoggerInterface;

it('renders Arabic UTF-8 HTML into real PDF bytes and cleans every job file', function (): void {
    $filesystem = new Filesystem;
    $sandbox = sys_get_temp_dir().'/reporting-mpdf-test-'.bin2hex(random_bytes(8));
    $jobs = $sandbox.'/jobs';
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldNotReceive('error');

    try {
        $renderer = new MpdfReportPdfRenderer(
            logger: $logger,
            temporaryDirectory: $jobs,
            format: 'A4-L',
            defaultFont: 'dejavusans',
        );

        $html = view('reporting::pdf.report', [
            'title' => 'تقرير الحصص اليومي',
            'organizationName' => 'مدرسة الاختبار',
            'generatedAt' => '2026-08-31 16:30',
            'timezone' => 'Europe/Istanbul',
            'direction' => 'rtl',
            'locale' => 'ar',
            'periodLabel' => '31 أغسطس 2026',
            'filterLabels' => ['الحالة' => 'مكتملة'],
            'summary' => ['إجمالي الحصص' => 1],
            'rows' => [[
                'title' => 'اللغة العربية',
                'scheduled_start_display' => '10:00',
                'scheduled_end_display' => '11:00',
                'duration_minutes' => 60,
                'course' => 'اللغة العربية',
                'group' => 'المجموعة الأولى',
                'actual_teacher' => 'أحمد',
                'original_teacher' => 'أحمد',
                'students_display' => 'ليلى — حاضرة',
                'attendance_summary' => '1 حاضر',
                'status_label' => 'مكتملة',
                'status_color' => 'success',
                'report_status_label' => 'مقدم',
                'session_type_label' => 'اعتيادية',
            ]],
        ])->render();

        $pdf = $renderer->render($html);

        expect($pdf)->toStartWith('%PDF-')
            ->and(strlen($pdf))->toBeGreaterThan(1000)
            ->and($jobs)->toBeDirectory()
            ->and(iterator_count(new FilesystemIterator($jobs)))->toBe(0);
    } finally {
        $filesystem->deleteDirectory($sandbox);
    }
});

it('wraps mPDF failures with structured logging and leaves no job directory', function (): void {
    $filesystem = new Filesystem;
    $sandbox = sys_get_temp_dir().'/reporting-mpdf-test-'.bin2hex(random_bytes(8));
    $blocked = $sandbox.'/blocked';
    $filesystem->makeDirectory($sandbox, 0700, true);
    $filesystem->put($blocked, 'not-a-directory');

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')
        ->once()
        ->with('reporting.pdf.render_failed', Mockery::on(
            static fn (array $context): bool => $context['reason'] === 'temporary_directory_unavailable'
                && $context['renderer'] === 'mpdf',
        ));

    try {
        $renderer = new MpdfReportPdfRenderer(
            logger: $logger,
            temporaryDirectory: $blocked,
            format: 'A4-L',
            defaultFont: 'dejavusans',
        );

        expect(fn (): string => $renderer->render('<html><body>Report</body></html>'))
            ->toThrow(ReportPdfRenderingException::class);
    } finally {
        $filesystem->deleteDirectory($sandbox);
    }
});

it('renders the multilingual report view with optional row details absent', function (): void {
    $html = view('reporting::pdf.report', [
        'title' => 'تقرير الحصص اليومي',
        'organizationName' => 'مدرسة الاختبار',
        'generatedAt' => '2026-08-31 16:30',
        'timezone' => 'Europe/Istanbul',
        'direction' => 'rtl',
        'locale' => 'ar',
        'periodLabel' => '31 أغسطس 2026',
        'filterLabels' => ['الحالة' => 'مكتملة'],
        'summary' => ['إجمالي الحصص' => 1],
        'rows' => [[
            'title' => 'اللغة العربية',
            'scheduled_start_display' => '10:00',
            'actual_teacher' => 'أحمد',
            'students_display' => 'ليلى',
            'status_label' => 'مكتملة',
            'status_color' => 'success',
        ]],
    ])->render();

    expect($html)->toContain('dir="rtl"')
        ->and($html)->toContain('<htmlpagefooter name="report-footer">')
        ->and($html)->toContain('{PAGENO}')
        ->and($html)->toContain('تقرير الحصص اليومي')
        ->and($html)->toContain('اللغة العربية')
        ->and($html)->toContain('ليلى')
        ->and($html)->not->toContain('reporting::pdf.');
});
