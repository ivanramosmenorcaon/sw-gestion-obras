<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProjectMeasurementImporter
{
    private const UNITS = [
        'u' => ['unit', 'Unidad', 'ud', 'count'],
        'ud' => ['unit', 'Unidad', 'ud', 'count'],
        'm' => ['meter', 'Metro', 'm', 'length'],
        'ml' => ['meter', 'Metro', 'm', 'length'],
        'm.' => ['meter', 'Metro', 'm', 'length'],
        'm2' => ['square_meter', 'Metro cuadrado', 'm²', 'area'],
        'm²' => ['square_meter', 'Metro cuadrado', 'm²', 'area'],
        'm3' => ['cubic_meter', 'Metro cúbico', 'm³', 'volume'],
        'm³' => ['cubic_meter', 'Metro cúbico', 'm³', 'volume'],
        'kg' => ['kilogram', 'Kilogramo', 'kg', 'mass'],
        'h' => ['hour', 'Hora', 'h', 'time'],
        'h.' => ['hour', 'Hora', 'h', 'time'],
        'l' => ['liter', 'Litro', 'l', 'volume'],
        't' => ['tonne', 'Tonelada', 't', 'mass'],
        'tn' => ['tonne', 'Tonelada', 't', 'mass'],
    ];

    public static function supportsUnit(string $unit): bool
    {
        return isset(self::UNITS[mb_strtolower($unit)]);
    }

    /**
     * @param  array{chapters: list<array{code: string, name: string, parent?: string}>, items: list<array<string, mixed>>, measurements: int, instructions?: string}  $document
     */
    public function import(Project $project, array $document, string $name, string $source = 'Mediciones de arquitecto'): WorkOrder
    {
        $fingerprint = hash('sha256', json_encode($document, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($project, $document, $fingerprint, $name, $source): WorkOrder {
            Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            if ($project->workOrders()->where('measurement_import_hash', $fingerprint)->exists()) {
                $this->fail('Este documento ya se ha importado en esta obra. No se han duplicado las partidas.');
            }
            $chapters = $project->projectChapters()->get()->keyBy('code');
            $chapterPosition = (int) $chapters->max('position');
            foreach ($document['chapters'] as $chapter) {
                if (! $chapters->has($chapter['code'])) {
                    $created = $project->projectChapters()->create([
                        'code' => $chapter['code'], 'name' => $chapter['name'],
                        'position' => ++$chapterPosition, 'is_active' => true,
                        'parent_id' => isset($chapter['parent']) ? $chapters->get($chapter['parent'])?->id : null,
                    ]);
                    $chapters->put($chapter['code'], $created);
                }
            }
            $units = [];
            foreach ($document['items'] as $item) {
                if (! self::supportsUnit($item['unit'])) {
                    $this->fail('Unidad no reconocida en la partida '.$item['code'].': '.$item['unit'].'.');
                }
                [$code, $unitName, $symbol, $magnitude] = self::UNITS[mb_strtolower($item['unit'])];
                if (! isset($units[$code])) {
                    $unit = Unit::query()->firstOrCreate(['code' => $code], [
                        'name' => $unitName, 'symbol' => $symbol, 'magnitude' => $magnitude,
                        'display_precision' => 3, 'is_active' => true,
                    ]);
                    if ($unit->magnitude !== $magnitude || ! $unit->is_active) {
                        $this->fail('La unidad '.$code.' está inactiva o tiene una magnitud incompatible.');
                    }
                    $units[$code] = $unit;
                }
            }
            $order = $project->workOrders()->create([
                'purpose' => 'measurement',
                'reference' => 'MED-'.Str::ulid(), 'name' => $name,
                'description' => $source.': '.count($document['chapters']).' capítulos y '.count($document['items']).' partidas.',
                'status' => 'draft', 'priority' => 'normal', 'measurement_import_hash' => $fingerprint,
                'instructions' => $document['instructions'] ?? null,
            ]);
            foreach ($document['items'] as $position => $item) {
                $unit = $units[self::UNITS[mb_strtolower($item['unit'])][0]];
                $line = $order->workOrderLines()->create([
                    'project_id' => $project->id, 'project_chapter_id' => $chapters->get($item['chapter'])->id,
                    'position' => $position + 1, 'type' => 'service',
                    'source_code' => $item['code'], 'description' => $item['title'].($item['description'] === '' ? '' : "\n\n".$item['description']),
                    'unit_id' => $unit->id, 'base_unit_id' => $unit->id, 'unit_label' => $item['unit'], 'base_unit_factor' => '1',
                    'planned_quantity' => $this->decimal($item['quantity']),
                    'estimated_unit_cost' => '0', 'estimated_unit_sale_price' => $this->decimal($item['price'] ?? 0),
                    'measurement_details' => $item['details'],
                    'budget_breakdown' => $item['breakdown'] ?? null,
                    'notes' => ($item['notes'] ?? 'Fila de origen: '.$item['source_row'].'.').($item['price'] === null ? ' Sin precio unitario en el documento original.' : ''),
                ]);
                app(ProjectScope::class)->captureLine($line);
            }

            return $order;
        });
    }

    private function decimal(float $value): string
    {
        return number_format($value, 6, '.', '');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
