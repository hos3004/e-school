<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Models;

use Shared\Concerns\HasModuleFactory;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Database\Factories\PasswordResetTokenFactory;

/**
 * رمز إعادة تعيين كلمة المرور — سجل مؤقت بلا معرّف ULID.
 *
 * المفتاح الأساسي هو البريد نفسه (سجل واحد لكل بريد). لا يُعرض هذا
 * النموذج عبر أي API — يستهلكه مسار «نسيت كلمة المرور» فقط.
 *
 * @property string $email
 * @property string $token
 * @property CarbonImmutable|null $created_at
 */
final class PasswordResetToken extends Model
{
    use HasModuleFactory;
    use HasTimestamps;

    public const UPDATED_AT = null;

    protected $table = 'password_reset_tokens';

    protected static string $factory = PasswordResetTokenFactory::class;

    protected $primaryKey = 'email';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    /** هل الرمز ما زال داخل نافذة الصلاحية حسب إعدادات auth؟ */
    public function isFresh(): bool
    {
        $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);

        return $this->created_at !== null
            && $this->created_at->addMinutes($expiresInMinutes)->isFuture();
    }
}
