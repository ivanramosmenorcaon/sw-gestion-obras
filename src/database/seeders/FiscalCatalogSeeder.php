<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FiscalCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $rates = [
                ['VAT_21_2026', 'IVA general 21 %', 'vat', '21', false],
                ['VAT_10_2026', 'IVA reducido 10 %', 'vat', '10', true],
                ['VAT_4_2026', 'IVA superreducido 4 %', 'vat', '4', true],
                ['VAT_0_2026', 'IVA sujeto al 0 % (solo supuestos legales)', 'vat', '0', true],
                ['IRPF_15_2026', 'IRPF profesional general 15 %', 'withholding', '15', false],
                ['IRPF_7_2026', 'IRPF profesional reducido 7 % (condicionado)', 'withholding', '7', true],
                ['IRPF_1_2026', 'IRPF empresarial en estimación objetiva 1 % (condicionado)', 'withholding', '1', true],
                ['RE_5_2_2026', 'Recargo de equivalencia 5,2 %', 'surcharge', '5.2', true],
                ['RE_1_4_2026', 'Recargo de equivalencia 1,4 %', 'surcharge', '1.4', true],
                ['RE_0_5_2026', 'Recargo de equivalencia 0,5 %', 'surcharge', '0.5', true],
                ['RE_0_2026', 'Recargo de equivalencia 0 %', 'surcharge', '0', true],
            ];
            foreach ($rates as [$code, $name, $kind, $percentage, $evidence]) {
                DB::table('tax_rates')->insertOrIgnore([
                    'code' => $code, 'name' => $name, 'kind' => $kind, 'percentage' => $percentage,
                    'valid_from' => '2026-01-01', 'requires_evidence' => $evidence,
                    'legal_basis' => $kind === 'withholding' ? 'Artículo 95 del Reglamento del IRPF. Comprobar actividad, condiciones, período y documentación.' : 'Ley 37/1992 del IVA. Comprobar el supuesto y las condiciones de aplicación.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $ids = DB::table('tax_rates')->pluck('id', 'code');
            $profiles = [];
            foreach (['21', '10', '4', '0'] as $percentage) {
                $profiles[] = ['IVA_'.$percentage, 'IVA '.$percentage.' %', '01', 'S1', null, $ids['VAT_'.$percentage.'_2026'], null, 'Tipo sujeto. Ley 37/1992; confirmar condiciones de aplicación.'];
            }
            $profiles[] = ['IVA_ISP_OBRA', 'Inversión del sujeto pasivo en ejecuciones de obra', '01', 'S2', null, $ids['VAT_0_2026'], null, 'Artículo 84.Uno.2.º.f) LIVA. Verificar contrato, naturaleza de la obra y condición de los intervinientes.'];
            foreach (['E1' => '20', 'E2' => '21', 'E3' => '22', 'E4' => '23 y 24', 'E5' => '25', 'E6' => 'otros'] as $exemption => $article) {
                $profiles[] = ['IVA_'.$exemption, 'IVA exento '.$exemption, in_array($exemption, ['E2', 'E3'], true) ? '02' : '01', 'exempt', $exemption, null, null, 'Exención IVA: artículo '.$article.' LIVA. Identificar y justificar el supuesto concreto.'];
            }
            foreach (['N1', 'N2'] as $operation) {
                $profiles[] = ['IVA_'.$operation, 'IVA no sujeto '.$operation, '01', $operation, null, null, null, $operation === 'N1' ? 'Artículo 7, 14 u otro supuesto de no sujeción de la LIVA. Justificar el supuesto.' : 'Reglas de localización de la LIVA. Determinar el lugar de realización de la operación.'];
            }
            foreach (['21' => '5_2', '10' => '1_4', '4' => '0_5'] as $vat => $surcharge) {
                $profiles[] = ['IVA_'.$vat.'_RE', 'IVA '.$vat.' % con recargo de equivalencia', '01', 'S1', null, $ids['VAT_'.$vat.'_2026'], $ids['RE_'.$surcharge.'_2026'], 'Proveedor en régimen general que repercute recargo al comerciante minorista. No presupone que el emisor esté acogido al régimen 18.'];
            }
            foreach ($profiles as [$code, $name, $regime, $operation, $exemption, $rateId, $surchargeId, $basis]) {
                DB::table('tax_profiles')->insertOrIgnore([
                    'code' => $code, 'name' => $name, 'tax_system' => '01', 'regime_code' => $regime,
                    'operation_class' => $operation, 'exemption_code' => $exemption, 'tax_rate_id' => $rateId,
                    'surcharge_rate_id' => $surchargeId, 'legal_basis' => $basis, 'requires_evidence' => $code !== 'IVA_21',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }
}
