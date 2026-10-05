# Additional country reference

The original 22 Arab countries and their 343 subdivisions remain in `geography.php`.
`additional-countries.json` adds the other 227 ISO 3166-1 entries, including dependent territories.
Turkey, Germany, the United Kingdom, France and the United States follow the existing list.
This order is a product choice for this school, not a statistical ranking.

Pinned data, assembled on 2026-09-08:
- Alpha-2 and alpha-3: Debian iso-codes 4.16.0, `iso_3166-1.json` (ISO 3166-1 identifiers).
- Arabic, English and French display names: Unicode CLDR JSON 48.0.0,
  https://github.com/unicode-org/cldr-json/tree/48.0.0/cldr-json/cldr-localenames-full/main
  (Unicode License V3; https://www.unicode.org/license.txt).
- International calling codes: Google libphonenumber v9.0.20,
  https://github.com/google/libphonenumber/blob/v9.0.20/resources/PhoneNumberMetadata.xml
  (Apache-2.0). An absent calling code is kept empty, never guessed.

No network request is made while a user fills in a form.
The searchable country field submits the existing canonical country ID.
Countries with no curated subdivisions receive an explicit `UNSPECIFIED` / “المنطقة غير محددة”
reference so existing required region relationships remain valid. A student can still enter their
city separately in the administrative profile. The fallback is not a geographic province.

Production sync, after deploying the code, is idempotent and additive:
`php artisan db:seed --class='Modules\Organization\Database\Seeders\AdditionalCountriesSeeder' --force`.
It leaves all existing country IDs, labels, activation flags and subdivisions unchanged.
