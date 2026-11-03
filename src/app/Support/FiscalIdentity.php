<?php

namespace App\Support;

use App\Models\Party;
use Illuminate\Validation\ValidationException;

final class FiscalIdentity
{
    /** @return array{NombreRazon: string, NIF?: string, IDOtro?: array{CodigoPais: string, IDType: string, ID: string}} */
    public static function recipient(Party $party): array
    {
        FiscalValidator::validate($party);
        if (blank($party->legal_name) || mb_strlen($party->legal_name) > 120) {
            throw ValidationException::withMessages(['legal_name' => __('fiscal.messages.identity_name')]);
        }
        $number = $party->tax_identification_number;
        $type = $party->tax_identification_type;
        if (blank($number) || mb_strlen($number) > ($type === 'nif' ? 9 : 20)) {
            throw ValidationException::withMessages(['tax_identification_number' => __('fiscal.messages.identity_number')]);
        }
        $result = ['NombreRazon' => $party->legal_name];
        if ($type === 'nif') {
            if (! preg_match('/^(?:[0-9]{8}[A-Z]|[XYZ][0-9]{7}[A-Z]|[A-Z][0-9]{7}[A-Z0-9])$/', $number)) {
                throw ValidationException::withMessages(['tax_identification_number' => __('fiscal.messages.identity_number')]);
            }

            return $result + ['NIF' => $number];
        }
        if ($type === '02') {
            $prefixes = ['AT' => 'AT', 'BE' => 'BE', 'BG' => 'BG', 'HR' => 'HR', 'CY' => 'CY', 'CZ' => 'CZ', 'DK' => 'DK', 'EE' => 'EE', 'FI' => 'FI', 'FR' => 'FR', 'DE' => 'DE', 'GR' => 'EL', 'HU' => 'HU', 'IE' => 'IE', 'IT' => 'IT', 'LV' => 'LV', 'LT' => 'LT', 'LU' => 'LU', 'MT' => 'MT', 'NL' => 'NL', 'PL' => 'PL', 'PT' => 'PT', 'RO' => 'RO', 'SK' => 'SK', 'SI' => 'SI', 'SE' => 'SE'];
            $prefix = $prefixes[$party->tax_country_code] ?? null;
            if (! $prefix || ! preg_match('/^'.$prefix.'[A-Z0-9]{2,12}$/', $number)) {
                throw ValidationException::withMessages(['tax_identification_number' => __('fiscal.messages.identity_number')]);
            }
        }

        return $result + ['IDOtro' => ['CodigoPais' => $party->tax_country_code, 'IDType' => $type, 'ID' => $number]];
    }
}
