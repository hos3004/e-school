<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts\DTOs;

/** أمر إنشاء حساب من موديول آخر؛ المؤسسة إلزامية ولا تُستنتج من جهة الاتصال. */
final readonly class CreateUserAccountData
{
    public function __construct(
        public string $organizationId,
        public string $name,
        public ?string $email,
        public string $username,
        public ?string $phone,
        public string $password,
        public ?string $phoneCountry = null,
        public string $locale = 'ar',
        public string $timezone = 'Africa/Cairo',
    ) {}
}
