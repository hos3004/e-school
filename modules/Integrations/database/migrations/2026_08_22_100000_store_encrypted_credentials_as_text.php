<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $credentials = DB::table('integration_connections')
            ->whereNotNull('credentials')
            ->pluck('credentials', 'id')
            ->all();

        DB::statement(
            'ALTER TABLE integration_connections '
            .'ALTER COLUMN credentials TYPE text USING credentials::text',
        );

        /** @var Encrypter $encrypter */
        $encrypter = app(Encrypter::class);

        foreach ($credentials as $id => $stored) {
            $json = is_string($stored)
                ? $stored
                : json_encode($stored, JSON_THROW_ON_ERROR);

            DB::table('integration_connections')
                ->where('id', (string) $id)
                ->update(['credentials' => $encrypter->encrypt($json, false)]);
        }
    }

    public function down(): void
    {
        $credentials = DB::table('integration_connections')
            ->whereNotNull('credentials')
            ->pluck('credentials', 'id')
            ->all();

        DB::table('integration_connections')
            ->whereNotNull('credentials')
            ->update(['credentials' => null]);

        DB::statement(
            'ALTER TABLE integration_connections '
            .'ALTER COLUMN credentials TYPE jsonb USING credentials::jsonb',
        );

        /** @var Encrypter $encrypter */
        $encrypter = app(Encrypter::class);

        foreach ($credentials as $id => $stored) {
            $json = $encrypter->decrypt((string) $stored, false);

            DB::update(
                'UPDATE integration_connections SET credentials = CAST(? AS jsonb) WHERE id = ?',
                [(string) $json, (string) $id],
            );
        }
    }
};
