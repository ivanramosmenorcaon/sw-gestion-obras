<?php

namespace App\Support;

final class FiscalCatalog
{
    /** @return array<string, string> */
    public static function countries(): array
    {
        return __('fiscal.countries');
    }

    /** @return array<string, string> */
    public static function identityTypes(): array
    {
        return __('fiscal.identity_types');
    }

    /** @return array<string, string> */
    public static function regimes(?string $taxSystem): array
    {
        return __('fiscal.regimes')[$taxSystem ?? '01'] ?? [];
    }

    /** @return array<string, string> */
    public static function exemptions(?string $taxSystem): array
    {
        return __('fiscal.exemptions')[$taxSystem ?? '01'] ?? [];
    }
}
