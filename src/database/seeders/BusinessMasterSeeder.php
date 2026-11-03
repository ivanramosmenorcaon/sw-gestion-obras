<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $units = [
                ['unit', 'Unidad', 'ud', 'count', 0],
                ['hour', 'Hora', 'h', 'time', 2],
                ['minute', 'Minuto', 'min', 'time', 2],
                ['gram', 'Gramo', 'g', 'mass', 3],
                ['kilogram', 'Kilogramo', 'kg', 'mass', 3],
                ['millimeter', 'Milímetro', 'mm', 'length', 3],
                ['centimeter', 'Centímetro', 'cm', 'length', 3],
                ['meter', 'Metro', 'm', 'length', 3],
                ['square_meter', 'Metro cuadrado', 'm²', 'area', 3],
                ['cubic_meter', 'Metro cúbico', 'm³', 'volume', 6],
                ['liter', 'Litro', 'l', 'volume', 3],
            ];

            foreach ($units as [$code, $name, $symbol, $magnitude, $precision]) {
                DB::table('units')->insertOrIgnore([
                    'code' => $code,
                    'name' => $name,
                    'symbol' => $symbol,
                    'magnitude' => $magnitude,
                    'display_precision' => $precision,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $unitIds = DB::table('units')->pluck('id', 'code');

            $conversions = [
                ['hour', 'minute', '60'],
                ['minute', 'hour', '0.016666666667'],
                ['kilogram', 'gram', '1000'],
                ['gram', 'kilogram', '0.001'],
                ['meter', 'centimeter', '100'],
                ['centimeter', 'meter', '0.01'],
                ['meter', 'millimeter', '1000'],
                ['millimeter', 'meter', '0.001'],
                ['centimeter', 'millimeter', '10'],
                ['millimeter', 'centimeter', '0.1'],
                ['cubic_meter', 'liter', '1000'],
                ['liter', 'cubic_meter', '0.001'],
            ];

            foreach ($conversions as [$from, $to, $factor]) {
                DB::table('unit_conversions')->insertOrIgnore([
                    'from_unit_id' => $unitIds[$from],
                    'to_unit_id' => $unitIds[$to],
                    'factor' => $factor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $terms = [
                'immediate' => ['Contado', [['100', 0, 'invoice_date']]],
                'net_30' => ['30 días desde factura', [['100', 30, 'invoice_date']]],
                'net_60' => ['60 días desde factura', [['100', 60, 'invoice_date']]],
                'split_30_60' => ['50 % a 30 días y 50 % a 60 días', [['50', 30, 'invoice_date'], ['50', 60, 'invoice_date']]],
                'start_30_completion_70' => ['30 % al inicio y 70 % al finalizar', [['30', 0, 'work_start'], ['70', 0, 'work_completion']]],
            ];

            foreach ($terms as $code => [$name, $installments]) {
                if (DB::table('payment_terms')->where('code', $code)->exists()) {
                    continue;
                }

                $termId = DB::table('payment_terms')->insertGetId([
                    'code' => $code,
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($installments as $index => [$percentage, $days, $event]) {
                    DB::table('payment_term_installments')->insert([
                        'payment_term_id' => $termId,
                        'sequence' => $index + 1,
                        'percentage' => $percentage,
                        'days_after_reference' => $days,
                        'reference_event' => $event,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
