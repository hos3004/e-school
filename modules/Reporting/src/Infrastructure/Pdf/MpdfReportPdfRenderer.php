<?php

declare(strict_types=1);

namespace Modules\Reporting\Infrastructure\Pdf;

use FilesystemIterator;
use Modules\Reporting\Domain\Contracts\ReportPdfRenderer;
use Modules\Reporting\Domain\Exceptions\ReportPdfRenderingException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/** مولد PDF محلي؛ يستقبل فقط HTML الموثوق الناتج من قالب Blade المغلق. */
final class MpdfReportPdfRenderer implements ReportPdfRenderer
{
    private readonly string $temporaryDirectory;

    private readonly string $format;

    private readonly string $defaultFont;

    public function __construct(
        private readonly LoggerInterface $logger,
        ?string $temporaryDirectory = null,
        ?string $format = null,
        ?string $defaultFont = null,
    ) {
        $this->temporaryDirectory = $temporaryDirectory ?? (string) config('reporting.pdf.temporary_directory');
        $this->format = $format ?? (string) config('reporting.pdf.format');
        $this->defaultFont = $defaultFont ?? (string) config('reporting.pdf.default_font');
    }

    public function render(string $html): string
    {
        $jobDirectory = null;

        try {
            $this->assertConfigurationIsValid();
            $jobDirectory = $this->createJobDirectory();

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => $this->format,
                'tempDir' => $jobDirectory,
                'default_font' => $this->defaultFont,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'margin_left' => 9,
                'margin_right' => 9,
                'margin_top' => 11,
                'margin_bottom' => 14,
            ]);
            $mpdf->SetDisplayMode('fullpage');
            $mpdf->WriteHTML($html);
            $pdf = $mpdf->Output('', Destination::STRING_RETURN);

            if ($pdf === '' || !str_starts_with($pdf, '%PDF-')) {
                throw ReportPdfRenderingException::outputInvalid();
            }

            return $pdf;
        } catch (ReportPdfRenderingException $exception) {
            $this->logFailure($exception);

            throw $exception;
        } catch (Throwable $exception) {
            $renderingException = ReportPdfRenderingException::renderingFailed([
                'exception_class' => $exception::class,
            ], $exception);
            $this->logFailure($renderingException);

            throw $renderingException;
        } finally {
            if ($jobDirectory !== null) {
                try {
                    $this->removeDirectory($jobDirectory);
                } catch (Throwable $cleanupException) {
                    $this->logger->warning('reporting.pdf.cleanup_failed', [
                        'renderer' => 'mpdf',
                        'exception' => $cleanupException,
                    ]);
                }
            }
        }
    }

    private function assertConfigurationIsValid(): void
    {
        $trimmedDirectory = rtrim($this->temporaryDirectory, DIRECTORY_SEPARATOR);

        if ($trimmedDirectory === '' || $trimmedDirectory === DIRECTORY_SEPARATOR
            || $this->format === '' || $this->defaultFont === '') {
            throw ReportPdfRenderingException::invalidConfiguration();
        }
    }

    private function createJobDirectory(): string
    {
        if (!is_dir($this->temporaryDirectory)
            && !@mkdir($this->temporaryDirectory, 0700, true)
            && !is_dir($this->temporaryDirectory)) {
            throw ReportPdfRenderingException::temporaryDirectoryUnavailable();
        }

        if (!is_writable($this->temporaryDirectory)) {
            throw ReportPdfRenderingException::temporaryDirectoryUnavailable();
        }

        try {
            $jobDirectory = rtrim($this->temporaryDirectory, DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR.bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            throw ReportPdfRenderingException::temporaryDirectoryUnavailable(previous: $exception);
        }

        if (!@mkdir($jobDirectory, 0700) && !is_dir($jobDirectory)) {
            throw ReportPdfRenderingException::temporaryDirectoryUnavailable();
        }

        return $jobDirectory;
    }

    private function logFailure(ReportPdfRenderingException $exception): void
    {
        $this->logger->error('reporting.pdf.render_failed', [
            'reason' => $exception->reason,
            'renderer' => 'mpdf',
            'format' => $this->format,
            'details' => $exception->context,
            'exception' => $exception,
        ]);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());

                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
