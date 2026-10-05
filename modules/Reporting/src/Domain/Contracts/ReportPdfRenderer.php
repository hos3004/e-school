<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Contracts;

use Modules\Reporting\Domain\Exceptions\ReportPdfRenderingException;

interface ReportPdfRenderer
{
    /**
     * Render a complete, UTF-8 HTML document into PDF bytes.
     *
     * @throws ReportPdfRenderingException
     */
    public function render(string $html): string;
}
