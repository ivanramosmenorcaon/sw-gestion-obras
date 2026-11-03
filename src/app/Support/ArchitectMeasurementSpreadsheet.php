<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Unit;
use App\Models\WorkOrder;
use Exception;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xls;

/**
 * @phpstan-type Measurement array{label: string, units: ?float, length: ?float, width: ?float, height: ?float, subtotal: ?float, source_row: int}
 * @phpstan-type Chapter array{code: string, name: string}
 * @phpstan-type Item array{code: string, chapter: string, unit: string, title: string, description: string, details: list<Measurement>, quantity: ?float, price: ?float, amount: ?float, source_row: int}
 */
final class ArchitectMeasurementSpreadsheet
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
    ];

    /** @return array{chapters: list<Chapter>, items: list<Item>, measurements: int} */
    public function inspect(string $path): array
    {
        return $this->parse($this->rows($path));
    }

    public function import(Project $project, string $path, string $name): WorkOrder
    {
        $document = $this->inspect($path);
        $fingerprint = hash('sha256', json_encode($document, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($project, $document, $fingerprint, $name): WorkOrder {
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
                    ]);
                    $chapters->put($chapter['code'], $created);
                }
            }
            $units = [];
            foreach ($document['items'] as $item) {
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
                'reference' => 'MED-'.Str::ulid(), 'name' => $name,
                'description' => 'Importación de mediciones de arquitecto: '.count($document['chapters']).' capítulos y '.count($document['items']).' partidas.',
                'status' => 'draft', 'priority' => 'normal', 'measurement_import_hash' => $fingerprint,
            ]);
            foreach ($document['items'] as $position => $item) {
                $unit = $units[self::UNITS[mb_strtolower($item['unit'])][0]];
                $order->workOrderLines()->create([
                    'project_id' => $project->id, 'project_chapter_id' => $chapters->get($item['chapter'])->id,
                    'position' => $position + 1, 'type' => 'service',
                    'source_code' => $item['code'], 'description' => $item['title'].($item['description'] === '' ? '' : "\n\n".$item['description']),
                    'unit_id' => $unit->id, 'base_unit_id' => $unit->id, 'unit_label' => $item['unit'], 'base_unit_factor' => '1',
                    'planned_quantity' => $this->decimal($item['quantity']),
                    'estimated_unit_cost' => '0', 'estimated_unit_sale_price' => $this->decimal($item['price'] ?? 0),
                    'measurement_details' => $item['details'],
                    'notes' => 'Fila de origen: '.$item['source_row'].'.'.($item['price'] === null ? ' Sin precio unitario en el documento original.' : ''),
                ]);
            }

            return $order;
        });
    }

    /** @return Generator<int, list<mixed>> */
    private function rows(string $path): Generator
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);
        try {
            $xlsReader = new Xls;
            if ($xlsReader->canRead($path)) {
                yield from $this->xlsRows($xlsReader, $path);

                return;
            }
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    if ($line > 20000) {
                        $this->fail('El documento supera el límite de 20000 filas.');
                    }
                    $values = $row->toArray();
                    foreach ($row->getCells() as $index => $cell) {
                        if ($cell instanceof FormulaCell) {
                            $values[$index] = $cell->getComputedValue();
                            if ($values[$index] === null) {
                                $this->fail('Fila '.$line.': hay una fórmula sin resultado guardado. Guarde el Excel después de recalcularlo.');
                            }
                        }
                    }
                    yield $line => $values;
                }
                break;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            report($exception);
            $this->fail('No se puede leer este Excel. Utilice un archivo .xls o .xlsx con el listado desglosado de mediciones.');
        } finally {
            $reader->close();
        }
    }

    /** @return Generator<int, list<mixed>> */
    private function xlsRows(Xls $reader, string $path): Generator
    {
        $sheets = $reader->listWorksheetInfo($path);
        $firstSheet = $sheets[0] ?? null;
        if ($firstSheet === null || $firstSheet['totalRows'] > 20000) {
            $this->fail('El documento no tiene hojas o supera el límite de 20000 filas.');
        }
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setLoadSheetsOnly($firstSheet['worksheetName']);
        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheet(0);
            for ($line = 1; $line <= $firstSheet['totalRows']; $line++) {
                $values = [];
                for ($column = 1; $column <= 11; $column++) {
                    $cell = $sheet->getCell([$column, $line]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $value = $cell->getOldCalculatedValue();
                        if ($value === null) {
                            $this->fail('Fila '.$line.': hay una fórmula sin resultado guardado. Guarde el Excel después de recalcularlo.');
                        }
                    } else {
                        $value = $cell->getValue();
                    }
                    $values[] = $value;
                }
                yield $line => $values;
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param  iterable<int, list<mixed>>  $rows
     * @return array{chapters: list<Chapter>, items: list<Item>, measurements: int}
     */
    private function parse(iterable $rows): array
    {
        $chapters = [];
        $items = [];
        $chapterCode = null;
        $item = null;
        $header = false;
        $measurements = 0;
        foreach ($rows as $line => $values) {
            $values = array_pad($values, 11, null);
            $code = $this->text($values[0], $line);
            $unit = $this->text($values[1], $line);
            $text = $this->text($values[2], $line);
            if (! $header) {
                if (Str::ascii(mb_strtolower($code)) === 'codigo' && Str::ascii(mb_strtolower($text)) === 'descripcion') {
                    $expected = ['unidades', 'largo', 'ancho', 'alto', 'parcial', 'medicion', 'pre.uni.', 'importe'];
                    foreach ($expected as $index => $label) {
                        if (Str::ascii(mb_strtolower($this->text($values[$index + 3], $line))) !== $label) {
                            $this->fail('Fila '.$line.': el orden de las columnas de medición no coincide con el formato esperado.');
                        }
                    }
                    $header = true;
                }

                continue;
            }
            if ($code !== '') {
                if ($item !== null) {
                    $this->fail('Fila '.$line.': falta TOTAL PARTIDA para '.$item['code'].'.');
                }
                if ($unit === '*') {
                    $chapterCode = rtrim($code, '#');
                    if ($chapterCode === '' || mb_strlen($chapterCode) > 50 || $text === '' || mb_strlen($text) > 255 || isset($chapters[$chapterCode])) {
                        $this->fail('Fila '.$line.': capítulo vacío, repetido o demasiado largo.');
                    }
                    $chapters[$chapterCode] = ['code' => $chapterCode, 'name' => $text];

                    continue;
                }
                if ($chapterCode === null || $text === '' || mb_strlen($code) > 80 || isset($items[$code]) || ! isset(self::UNITS[mb_strtolower($unit)])) {
                    $this->fail('Fila '.$line.': partida sin capítulo, código repetido o unidad no reconocida ('.$unit.').');
                }
                $item = [
                    'code' => $code, 'chapter' => $chapterCode, 'unit' => $unit, 'title' => $text,
                    'description' => '', 'details' => [], 'quantity' => null, 'price' => null,
                    'amount' => null, 'source_row' => $line,
                ];

                continue;
            }
            if ($text === 'TOTAL PARTIDA') {
                if ($item === null) {
                    $this->fail('Fila '.$line.': total sin partida.');
                }
                $item['quantity'] = $this->number($values[8], $line, true);
                $item['price'] = $this->number($values[9], $line);
                $item['amount'] = $this->number($values[10], $line);
                if ($item['quantity'] < 0 || ($item['price'] ?? 0) < 0 || $item['quantity'] > 99999999999999 || ($item['price'] ?? 0) > 99999999999999) {
                    $this->fail('Fila '.$line.': medición o precio fuera del rango permitido.');
                }
                $sum = array_sum(array_column($item['details'], 'subtotal'));
                if ($item['details'] !== [] && abs($sum - $item['quantity']) > 0.001) {
                    $this->fail('Fila '.$line.': el desglose no coincide con la medición total de '.$item['code'].'.');
                }
                $items[$item['code']] = $item;
                $item = null;

                continue;
            }
            if (str_starts_with($text, 'TOTAL CAPITULO')) {
                if ($item !== null) {
                    $this->fail('Fila '.$line.': la partida '.$item['code'].' no tiene total.');
                }

                continue;
            }
            if ($item === null) {
                if ($text !== '' || collect(array_slice($values, 3, 8))->contains(fn (mixed $value): bool => $value !== null && $value !== '')) {
                    $this->fail('Fila '.$line.': contenido no reconocido fuera de una partida.');
                }

                continue;
            }
            $hasDimensions = collect(array_slice($values, 3, 5))->contains(fn (mixed $value): bool => $value !== null && $value !== '');
            if ($hasDimensions) {
                $item['details'][] = [
                    'label' => $text, 'units' => $this->number($values[3], $line),
                    'length' => $this->number($values[4], $line), 'width' => $this->number($values[5], $line),
                    'height' => $this->number($values[6], $line), 'subtotal' => $this->number($values[7], $line, true),
                    'source_row' => $line,
                ];
                $measurements++;
            } elseif ($text !== '') {
                $item['description'] .= ($item['description'] === '' ? '' : "\n").$text;
            }
        }
        if (! $header || $item !== null || $items === []) {
            $this->fail('El documento no contiene un listado desglosado completo: faltan cabeceras, partidas o sus totales.');
        }

        return ['chapters' => array_values($chapters), 'items' => array_values($items), 'measurements' => $measurements];
    }

    private function text(mixed $value, int $line): string
    {
        if (! is_scalar($value) && $value !== null) {
            $this->fail('Fila '.$line.': valor no reconocido.');
        }

        return trim((string) $value);
    }

    private function number(mixed $value, int $line, bool $required = false): ?float
    {
        if ($value === null || $value === '') {
            if ($required) {
                $this->fail('Fila '.$line.': falta una medición o un parcial obligatorio.');
            }

            return null;
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            $this->fail('Fila '.$line.': la medición o el precio no es numérico.');
        }

        return (float) $value;
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
