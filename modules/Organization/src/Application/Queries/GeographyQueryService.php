<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Organization\Domain\ValueObjects\RegionData;
use UnexpectedValueException;

final readonly class GeographyQueryService implements GeographyQueries
{
    /**
     * @return list<CountryData>
     */
    public function countries(bool $activeOnly = true): array
    {
        $query = DB::table('countries')->orderBy('sort_order')->orderBy('iso2');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()
            ->map(fn (object $row): CountryData => $this->mapCountry($row))
            ->all();
    }

    /**
     * @return list<RegionData>
     */
    public function regionsOf(string $countryId, bool $activeOnly = true): array
    {
        $query = DB::table('regions')
            ->where('country_id', $countryId)
            ->orderBy('sort_order')
            ->orderBy('code');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()
            ->map(fn (object $row): RegionData => $this->mapRegion($row))
            ->all();
    }

    public function findCountryByIso2(string $iso2): ?CountryData
    {
        $row = DB::table('countries')->where('iso2', strtoupper($iso2))->first();

        return $row !== null ? $this->mapCountry($row) : null;
    }

    public function regionExistsIn(string $regionId, string $countryId): bool
    {
        return DB::table('regions')
            ->where('id', $regionId)
            ->where('country_id', $countryId)
            ->exists();
    }

    private function mapCountry(object $row): CountryData
    {
        /** @var array<string, mixed> $data */
        $data = (array) $row;

        return new CountryData(
            id: $this->requiredString($data, 'id'),
            iso2: $this->requiredString($data, 'iso2'),
            iso3: $this->requiredString($data, 'iso3'),
            name: $this->localizedName($data['name'] ?? null),
            phoneCode: $this->requiredString($data, 'phone_code'),
            isActive: $this->boolean($data, 'is_active'),
            sortOrder: $this->integer($data, 'sort_order'),
        );
    }

    private function mapRegion(object $row): RegionData
    {
        /** @var array<string, mixed> $data */
        $data = (array) $row;

        return new RegionData(
            id: $this->requiredString($data, 'id'),
            countryId: $this->requiredString($data, 'country_id'),
            code: $this->requiredString($data, 'code'),
            name: $this->localizedName($data['name'] ?? null),
            isActive: $this->boolean($data, 'is_active'),
            sortOrder: $this->integer($data, 'sort_order'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new UnexpectedValueException("Invalid geography value for {$key}.");
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function localizedName(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        if (!is_array($value)) {
            throw new UnexpectedValueException('Invalid localized geography name.');
        }

        $name = [];
        foreach ($value as $locale => $translation) {
            if (!is_string($locale) || !is_string($translation)) {
                throw new UnexpectedValueException('Invalid localized geography name.');
            }

            $name[$locale] = $translation;
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function boolean(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;

        return match ($value) {
            true, 1, '1', 't', 'true' => true,
            false, 0, '0', 'f', 'false' => false,
            default => throw new UnexpectedValueException("Invalid geography value for {$key}."),
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new UnexpectedValueException("Invalid geography value for {$key}.");
        }

        return (int) $value;
    }
}
