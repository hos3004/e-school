<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Contracts;

/**
 * تعداد خفيف لمعرّفات المؤسسات — للمهام المجدولة التي يجب أن تمرّ على
 * كل مؤسسة نشطة (كالتقرير الشهري المجمَّع) دون افتراض مستأجر واحد.
 */
interface OrganizationDirectoryQueries
{
    /** @return list<string> */
    public function activeOrganizationIds(): array;
}
