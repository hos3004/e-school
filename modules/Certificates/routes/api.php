<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Certificates\Domain\Models\Badge;
use Modules\Certificates\Domain\Models\Certificate;
use Modules\Certificates\Domain\Models\CertificateTemplate;
use Modules\Certificates\Presentation\Http\Controllers\AwardBadgeController;
use Modules\Certificates\Presentation\Http\Controllers\IssueCertificateController;
use Modules\Certificates\Presentation\Http\Controllers\ListBadgeAwardsController;
use Modules\Certificates\Presentation\Http\Controllers\ListCertificatesController;
use Modules\Certificates\Presentation\Http\Controllers\RevokeCertificateController;
use Modules\Certificates\Presentation\Http\Controllers\ShowCertificateController;
use Modules\Certificates\Presentation\Http\Controllers\StoreBadgeController;
use Modules\Certificates\Presentation\Http\Controllers\StoreCertificateTemplateController;
use Modules\Certificates\Presentation\Http\Controllers\UpdateBadgeController;
use Modules\Certificates\Presentation\Http\Controllers\UpdateCertificateTemplateController;

// ── قوالب الشهادات ──────────────────────────────────────────────────────────

Route::post('certificate-templates', StoreCertificateTemplateController::class)
    ->middleware('can:create,'.CertificateTemplate::class)
    ->name('certificate-templates.store');

Route::patch('certificate-templates/{template}', UpdateCertificateTemplateController::class)
    ->name('certificate-templates.update');

// ── الشهادات ────────────────────────────────────────────────────────────────

Route::get('certificates', ListCertificatesController::class)->name('certificates.index');
Route::get('certificates/{certificate}', ShowCertificateController::class)->name('certificates.show');

Route::post('certificates', IssueCertificateController::class)
    ->middleware('can:create,'.Certificate::class)
    ->name('certificates.store');

Route::delete('certificates/{certificate}', RevokeCertificateController::class)
    ->name('certificates.revoke');

// ── الشارات والمنح ──────────────────────────────────────────────────────────

Route::get('badge-awards', ListBadgeAwardsController::class)->name('badge-awards.index');

Route::post('badges', StoreBadgeController::class)
    ->middleware('can:create,'.Badge::class)
    ->name('badges.store');

Route::patch('badges/{badge}', UpdateBadgeController::class)->name('badges.update');

Route::post('badges/{badge}/awards', AwardBadgeController::class)->name('badges.award');
