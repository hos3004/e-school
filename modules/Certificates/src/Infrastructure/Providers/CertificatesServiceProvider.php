<?php

declare(strict_types=1);

namespace Modules\Certificates\Infrastructure\Providers;

use Modules\Certificates\Application\Policies\BadgeAwardPolicy;
use Modules\Certificates\Application\Policies\BadgePolicy;
use Modules\Certificates\Application\Policies\CertificatePolicy;
use Modules\Certificates\Application\Policies\CertificateTemplatePolicy;
use Modules\Certificates\Domain\Models\Badge;
use Modules\Certificates\Domain\Models\BadgeAward;
use Modules\Certificates\Domain\Models\Certificate;
use Modules\Certificates\Domain\Models\CertificateTemplate;
use Shared\Module\BaseModuleServiceProvider;

final class CertificatesServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Certificates';
    }

    /**
     * السياسات الأربع كانت مكتوبة ولا يربطها أحد بنماذجها، فكانت كل صلاحيات
     * الشهادات والشارات تعود false دائمًا — Gate بلا سياسة يرفض. لم يظهر ذلك
     * لأن موارد الموديول كانت بلا صفحات، فلم يُسأل Gate عنها قط.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Badge::class => BadgePolicy::class,
            BadgeAward::class => BadgeAwardPolicy::class,
            Certificate::class => CertificatePolicy::class,
            CertificateTemplate::class => CertificateTemplatePolicy::class,
        ];
    }
}
