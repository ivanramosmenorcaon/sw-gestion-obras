<?php

namespace App\Support;

use App\Models\Project;
use App\Models\WorkOrder;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type Concept array{code: string, unit: string, name: string, price: ?float, type: string}
 * @phpstan-type Edge array{code: string, factor: float, quantity: float}
 * @phpstan-type Measurement array{quantity: float, details: list<array<string, mixed>>, label: string}
 */
final class Bc3BudgetImporter
{
    /** @var array<string, Concept> */
    private array $concepts = [];

    /** @var array<string, list<Edge>> */
    private array $edges = [];

    /** @var array<string, string> */
    private array $texts = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, array<string, Measurement>> */
    private array $measurements = [];

    private int $partialDecimals = 3;

    private int $resourceCount = 0;

    public function import(Project $project, string $path, string $name): WorkOrder
    {
        $document = $this->inspect($path);
        if ($document['currency'] !== null && $document['currency'] !== $project->currency_code) {
            $this->fail('La divisa del BC3 no coincide con la de la obra. No se convierten monedas automáticamente.');
        }
        unset($document['currency']);

        return app(ProjectMeasurementImporter::class)->import($project, $document, $name, 'Presupuesto BC3');
    }

    /** @return array{chapters: list<array{code: string, name: string, parent?: string}>, items: list<array<string, mixed>>, measurements: int, currency: ?string, instructions: string} */
    public function inspect(string $path): array
    {
        $this->concepts = $this->edges = $this->texts = $this->aliases = $this->measurements = [];
        $this->partialDecimals = 3;
        $this->resourceCount = 0;
        if (! is_file($path) || filesize($path) > 5 * 1024 * 1024) {
            $this->fail('Seleccione un archivo BC3 de hasta 5 MB.');
        }
        $bytes = file_get_contents($path);
        if ($bytes === false || str_contains($bytes, "\0")) {
            $this->fail('El archivo no es un BC3 de texto válido.');
        }
        $bytes = preg_replace('/^\xEF\xBB\xBF/', '', $bytes);
        if (! preg_match('/~V\|[^|]*\|FIEBDC-3\//', $bytes)) {
            $this->fail('No se ha encontrado una cabecera FIEBDC-3 válida.');
        }
        $versionFields = explode('|', explode('~', substr($bytes, strpos($bytes, '~V') + 1))[0]);
        $charset = strtoupper(trim($versionFields[5] ?? 'ANSI'));
        $encoding = match ($charset) {
            'ANSI', '' => 'Windows-1252',
            '850', 'OEM' => 'CP850',
            'UTF-8', 'UTF8' => 'UTF-8',
            default => null,
        };
        if ($encoding === null || ! mb_check_encoding($bytes, $encoding)) {
            $this->fail('La codificación del BC3 no está admitida o el archivo está dañado.');
        }
        $text = mb_convert_encoding($bytes, 'UTF-8', $encoding);
        $records = array_values(array_filter(array_map('trim', explode('~', $text)), fn (string $record): bool => $record !== ''));
        if (count($records) > 30000) {
            $this->fail('El BC3 supera el límite de 30000 registros.');
        }
        $currency = null;
        foreach ($records as $record) {
            $fields = explode('|', $record);
            switch ($fields[0]) {
                case 'K':
                    $parameters = explode('\\', $fields[1] ?? '');
                    $currency = trim($parameters[8] ?? '') ?: null;
                    $decimals = trim($parameters[1] ?? '');
                    if ($decimals !== '') {
                        if (! ctype_digit($decimals) || (int) $decimals > 6) {
                            $this->fail('La precisión de medición del BC3 no está admitida (máximo 6 decimales).');
                        }
                        $this->partialDecimals = (int) $decimals;
                    }
                    foreach (explode('\\', $fields[2] ?? '') as $coefficient) {
                        if ($this->number($coefficient, 0) !== 0.0) {
                            $this->fail('El BC3 contiene impuestos, bajas o coeficientes globales no nulos. Esta importación admite precios unitarios directos; no aplica esos coeficientes.');
                        }
                    }
                    break;
                case 'C':
                    $codes = explode('\\', $fields[1] ?? '');
                    $code = trim(array_shift($codes));
                    if ($code === '' || mb_strlen($code) > 80 || isset($this->concepts[$code])) {
                        $this->fail('El BC3 contiene un concepto vacío, repetido o con código demasiado largo.');
                    }
                    $prices = array_values(array_filter(explode('\\', $fields[4] ?? ''), fn (string $price): bool => trim($price) !== ''));
                    if (count($prices) > 1) {
                        $this->fail('El BC3 contiene precios alternativos. Exporte el presupuesto con una sola tarifa.');
                    }
                    $price = $prices === [] ? null : $this->number($prices[0]);
                    if ($price !== null && ($price < 0 || $price > 99999999999999)) {
                        $this->fail('Precio fuera de rango para el concepto '.$code.'.');
                    }
                    $this->concepts[$code] = ['code' => $code, 'unit' => trim($fields[2] ?? ''), 'name' => trim($fields[3] ?? ''), 'price' => $price, 'type' => trim($fields[6] ?? '0')];
                    foreach ([$code, ...$codes] as $alias) {
                        $alias = trim($alias);
                        if ($alias !== '') {
                            if (isset($this->aliases[$alias]) && $this->aliases[$alias] !== $code) {
                                $this->fail('El código o alias '.$alias.' identifica más de un concepto.');
                            }
                            $this->aliases[$alias] = $code;
                        }
                    }
                    if (str_ends_with($code, '#') && rtrim($code, '#') !== '') {
                        $this->aliases[rtrim($code, '#')] = $code;
                    }
                    break;
                case 'D':
                case 'Y':
                    $parent = trim($fields[1] ?? '');
                    $children = $this->groups($fields[2] ?? '', 3);
                    if (trim($fields[3] ?? '') !== '') {
                        $this->fail('El BC3 contiene una descomposición porcentual extendida no admitida.');
                    }
                    if ($fields[0] === 'D') {
                        $this->edges[$parent] = [];
                    }
                    foreach ($children as [$child, $factor, $quantity]) {
                        if (trim($child) === '') {
                            $this->fail('Hay un código hijo vacío en la descomposición de '.$parent.'.');
                        }
                        $this->edges[$parent][] = ['code' => trim($child), 'factor' => $this->number($factor, 1), 'quantity' => $this->number($quantity, 1)];
                    }
                    break;
                case 'T':
                    $this->texts[trim($fields[1] ?? '')] = trim($fields[2] ?? '');
                    break;
                case 'P':
                case 'B':
                    $this->fail('El BC3 contiene conceptos paramétricos o cambios de código. Exporte un presupuesto resuelto antes de importarlo.');
            }
        }
        $normalizedEdges = [];
        foreach ($this->edges as $parent => $edges) {
            $parent = $this->canonical((string) $parent);
            foreach ($edges as $edge) {
                $edge['code'] = $this->canonical($edge['code']);
                $normalizedEdges[$parent][] = $edge;
            }
        }
        $this->edges = $normalizedEdges;
        $normalizedTexts = [];
        foreach ($this->texts as $code => $description) {
            $normalizedTexts[$this->canonical((string) $code)] = $description;
        }
        $this->texts = $normalizedTexts;
        foreach ($records as $index => $record) {
            $fields = explode('|', $record);
            if (in_array($fields[0], ['M', 'N'], true)) {
                $this->readMeasurement($fields, $index + 1);
            }
        }
        $roots = array_keys(array_filter($this->concepts, fn (array $concept): bool => str_ends_with($concept['code'], '##')));
        if (count($roots) !== 1) {
            $this->fail('Se necesita un único presupuesto raíz (código terminado en ##), con capítulos y partidas.');
        }
        $chapters = [];
        $items = [];
        $this->walk((string) $roots[0], null, [], [], $chapters, $items);
        if ($items === []) {
            $this->fail('El BC3 no contiene partidas presupuestadas.');
        }
        $measurementCount = array_sum(array_map(fn (array $item): int => count($item['details']), $items));

        return [
            'chapters' => array_values($chapters), 'items' => $items, 'measurements' => $measurementCount,
            'currency' => $currency,
            'instructions' => 'Presupuesto importado desde BC3. Precios unitarios directos; no se añaden impuestos ni gastos generales. Los descompuestos se conservan como referencia y no se convierten en productos ni costes estimados.',
        ];
    }

    /** @param list<string> $fields */
    private function readMeasurement(array $fields, int $record): void
    {
        $codes = explode('\\', trim($fields[1] ?? '', '\\'));
        if (count($codes) !== 2) {
            $this->fail('Registro '.$record.': se necesitan mediciones estructuradas con código padre e hijo.');
        }
        [$parent, $child] = array_map(fn (string $code): string => $this->canonical($code), $codes);
        $position = trim($fields[2] ?? '', '\\');
        $quantity = $this->number($fields[3] ?? '');
        $details = [];
        $sum = 0.0;
        foreach ($this->groups($fields[4] ?? '', 6) as [$type, $comment, $units, $length, $width, $height]) {
            if (! in_array(trim($type), ['', '0', '1', '2'], true)) {
                $this->fail('Registro '.$record.': hay una fórmula de medición no admitida. Exporte sus resultados como líneas de medición simples.');
            }
            if (in_array(trim($type), ['1', '2'], true)) {
                continue;
            }
            $dimensions = array_map(fn (string $value): ?float => trim($value) === '' ? null : $this->number($value), [$units, $length, $width, $height]);
            $subtotal = null;
            if (array_filter($dimensions, fn (?float $value): bool => $value !== null) !== []) {
                $subtotal = round(array_product(array_map(fn (?float $value): float => $value ?? 1.0, $dimensions)), $this->partialDecimals);
                $sum += $subtotal;
            }
            $details[] = [
                'label' => $comment, 'units' => $dimensions[0], 'length' => $dimensions[1], 'width' => $dimensions[2], 'height' => $dimensions[3],
                'subtotal' => $subtotal, 'source_row' => $record,
            ];
        }
        if ($details !== [] && abs($sum - $quantity) > 0.00101) {
            $this->fail('Registro '.$record.': el desglose no coincide con el total de '.$child.'.');
        }
        $key = $parent.'\\'.$child;
        if ($fields[0] === 'N' && isset($this->measurements[$key][$position])) {
            $previous = $this->measurements[$key][$position];
            $quantity += $previous['quantity'];
            $details = [...$previous['details'], ...$details];
        }
        $this->measurements[$key][$position] = ['quantity' => $quantity, 'details' => $details, 'label' => trim($fields[5] ?? '')];
    }

    /**
     * @param  list<int>  $positions
     * @param  list<string>  $ancestors
     * @param  array<string, array{code: string, name: string, parent?: string}>  $chapters
     * @param  list<array<string, mixed>>  $items
     */
    private function walk(string $parent, ?string $chapter, array $positions, array $ancestors, array &$chapters, array &$items): void
    {
        if (count($ancestors) > 50 || in_array($parent, $ancestors, true)) {
            $this->fail('La jerarquía BC3 contiene un ciclo o supera 50 niveles.');
        }
        $ancestors[] = $parent;
        foreach ($this->edges[$parent] ?? [] as $index => $edge) {
            $code = $edge['code'];
            $concept = $this->concepts[$code];
            $path = [...$positions, $index + 1];
            if (str_ends_with($code, '#')) {
                $chapterCode = rtrim($code, '#');
                if ($chapterCode === '' || mb_strlen($chapterCode) > 50 || isset($chapters[$chapterCode]) || $concept['name'] === '' || mb_strlen($concept['name']) > 255) {
                    $this->fail('Capítulo BC3 repetido, vacío o con código o nombre demasiado largo: '.$code.'.');
                }
                $chapters[$chapterCode] = ['code' => $chapterCode, 'name' => $concept['name']];
                if ($chapter !== null) {
                    $chapters[$chapterCode]['parent'] = $chapter;
                }
                $this->walk($code, $chapterCode, $path, $ancestors, $chapters, $items);

                continue;
            }
            if ($chapter === null) {
                $chapters['GENERAL'] ??= ['code' => 'GENERAL', 'name' => 'General'];
            }
            $key = $parent.'\\'.$code;
            $candidates = $this->measurements[$key] ?? [];
            $measurement = $candidates[implode('\\', $path)] ?? (count($candidates) === 1 ? reset($candidates) : null);
            if (count($candidates) > 1 && $measurement === null) {
                $this->fail('No se puede determinar la posición de las mediciones de '.$code.'.');
            }
            $quantity = $edge['quantity'] * $edge['factor'];
            if ($measurement !== null && abs($measurement['quantity'] - $quantity) > 0.00101) {
                $this->fail('La medición y el rendimiento de '.$code.' no coinciden.');
            }
            if ($quantity < 0 || $quantity > 99999999999999 || $concept['name'] === '') {
                $this->fail('La cantidad o la descripción de '.$code.' no son válidas.');
            }
            $items[] = [
                'code' => $code, 'chapter' => $chapter ?? 'GENERAL', 'unit' => $concept['unit'],
                'title' => $concept['name'], 'description' => $this->texts[$code] ?? '',
                'details' => $measurement['details'] ?? [], 'quantity' => $measurement['quantity'] ?? $quantity,
                'price' => $concept['price'], 'amount' => null, 'source_row' => 0,
                'notes' => 'Código BC3: '.$code.'.'.(($measurement['label'] ?? '') !== '' ? ' Referencia en listado: '.$measurement['label'].'.' : ''),
                'breakdown' => $this->breakdown($code, []),
            ];
            if (count($items) > 3000) {
                $this->fail('El presupuesto supera el límite de 3000 partidas.');
            }
        }
    }

    /**
     * @param  list<string>  $ancestors
     * @return list<array<string, mixed>>
     */
    private function breakdown(string $parent, array $ancestors): array
    {
        if (in_array($parent, $ancestors, true) || count($ancestors) > 50) {
            $this->fail('Hay una referencia circular o demasiados niveles en los descompuestos de '.$parent.'.');
        }
        $ancestors[] = $parent;
        $rows = [];
        foreach ($this->edges[$parent] ?? [] as $edge) {
            if (++$this->resourceCount > 50000) {
                $this->fail('El presupuesto supera el límite de 50000 recursos descompuestos.');
            }
            $concept = $this->concepts[$edge['code']];
            $rows[] = [...$concept, 'parent_code' => $parent, 'depth' => count($ancestors), 'factor' => $edge['factor'], 'quantity' => $edge['quantity'], 'description' => $this->texts[$edge['code']] ?? ''];
            $rows = [...$rows, ...$this->breakdown($edge['code'], $ancestors)];
            if (count($rows) > 10000) {
                $this->fail('El descompuesto de '.$parent.' supera el límite de 10000 recursos.');
            }
        }

        return $rows;
    }

    private function canonical(string $code): string
    {
        if (! isset($this->aliases[$code])) {
            $this->fail('El BC3 referencia un concepto que no está definido: '.$code.'.');
        }

        return $this->aliases[$code];
    }

    /** @return list<list<string>> */
    private function groups(string $value, int $size): array
    {
        if ($value === '') {
            return [];
        }
        $parts = explode('\\', $value);
        if (end($parts) === '') {
            array_pop($parts);
        }
        if (count($parts) % $size !== 0) {
            $this->fail('El BC3 contiene un registro de descomposición o medición incompleto.');
        }

        return array_chunk($parts, $size);
    }

    private function number(string $value, ?float $default = null): float
    {
        $value = trim($value);
        if ($value === '' && $default !== null) {
            return $default;
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            $this->fail('El BC3 contiene un precio, factor o medición no numérico.');
        }

        return (float) $value;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
