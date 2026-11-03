<?php

namespace App\Support;

use App\Models\Project;
use App\Models\WorkOrder;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PdfBudgetImporter
{
    private const NUMBER = '-?(?:\d{1,3}(?:\.\d{3})+|\d+),\d{2,6}';

    public function __construct(private PdfBudgetReader $reader) {}

    public function import(Project $project, string $path, string $name): WorkOrder
    {
        if ($project->currency_code !== 'EUR') {
            $this->fail('Estos diseños de PDF expresan sus precios en euros. La obra debe utilizar EUR; no se convierten monedas automáticamente.');
        }
        $document = $this->inspect($path);
        unset($document['net_total'], $document['page_count']);

        return app(ProjectMeasurementImporter::class)->import($project, $document, $name, 'Presupuesto PDF');
    }

    /** @return array{chapters: list<array{code: string, name: string}>, items: list<array<string, mixed>>, measurements: int, instructions: string, net_total: string, page_count: int} */
    public function inspect(string $path): array
    {
        $pages = $this->reader->pages($path);
        $chapters = $items = $chapterTotals = $chapterSums = [];
        $chapterCode = null;
        $chapterOpen = false;
        $item = null;
        $summaryNet = null;
        $taxNotes = [];
        $hasCompactMeasurements = false;
        foreach ($pages as $page) {
            $header = null;
            foreach ($page['rows'] as $row) {
                $ascii = Str::ascii(mb_strtolower($row['text']));
                if (str_contains($ascii, 'codigo') && str_contains($ascii, 'importe') && (str_contains($ascii, 'descripcion') || str_contains($ascii, 'resumen'))) {
                    $header = $row;
                    break;
                }
            }
            if ($header === null) {
                foreach ($page['rows'] as $row) {
                    $ascii = Str::ascii(mb_strtoupper($row['text']));
                    if (str_starts_with($ascii, 'TOTAL EJECUCION MATERIAL') && preg_match('/('.self::NUMBER.')\s*$/', $row['text'], $match)) {
                        $summaryNet = $this->number($match[1]);
                    }
                    if (preg_match('/^(?:IVA\b|T O T A L\b)/', $ascii)) {
                        $taxNotes[] = $row['text'];
                    }
                }

                continue;
            }
            $explicit = str_contains(Str::ascii(mb_strtolower($header['text'])), 'descripcion');
            $hasCompactMeasurements = $hasCompactMeasurements || ! $explicit;
            $columns = $explicit ? $this->columns($header['spans']) : [];
            $codeLimit = $explicit ? $columns['unit'] - 8 : 30;
            $baseFont = $header['spans'][0]['font'];
            foreach ($page['rows'] as $rowIndex => $row) {
                if ($row['y'] >= $header['y'] - 2 || $row['y'] < 15) {
                    continue;
                }
                $text = $row['text'];
                $ascii = Str::ascii(mb_strtoupper($text));
                if (preg_match('/^(?:UDS\.|UNITARIO|SUMA Y SIGUE|SUMA ANTERIOR|SON\b)/', $ascii)) {
                    continue;
                }
                $first = $row['spans'][0];
                if (preg_match('/TOTAL CAPITULO\s+([^\s.]+)/', $ascii, $match)) {
                    if ($item !== null) {
                        $this->fail('Página '.$page['page'].': falta el total de la partida '.$item['code'].'.');
                    }
                    $code = $match[1];
                    if (! isset($chapters[$code]) || ! preg_match('/('.self::NUMBER.')\s*$/', $text, $amount)) {
                        $this->fail('Página '.$page['page'].': total de capítulo no reconocido.');
                    }
                    $chapterTotals[$code] = $this->number($amount[1]);
                    $chapterOpen = false;

                    continue;
                }
                if (preg_match('/^TOTAL\.+\s*('.self::NUMBER.')$/', $ascii, $match)) {
                    $summaryNet = $this->number($match[1]);

                    continue;
                }
                $newChapter = null;
                if (! $explicit && preg_match('/^CAPITULO\s+(\S+)\s+(.+)$/', $ascii, $match)) {
                    $newChapter = [$match[1], preg_replace('/^CAP[IÍ]TULO\s+\S+\s+/ui', '', $text)];
                } elseif ($explicit && $first['x'] < $codeLimit && preg_match('/^(\d{1,3}(?:\.\d+)*)\s+(.+)$/u', $text, $match)) {
                    if (! preg_match('/^\S+\s+\S+\s+/', $first['text']) && ! ProjectMeasurementImporter::supportsUnit(explode(' ', $match[2])[0])) {
                        $newChapter = [$match[1], $match[2]];
                    }
                }
                if ($newChapter !== null) {
                    [$code, $name] = $newChapter;
                    if ($item !== null && $code !== $chapterCode) {
                        $this->fail('Página '.$page['page'].': hay una partida sin cerrar antes del capítulo '.$code.'.');
                    }
                    if (isset($chapters[$code]) && $code !== $chapterCode) {
                        $this->fail('Página '.$page['page'].': capítulo repetido '.$code.'.');
                    }
                    $chapters[$code] ??= ['code' => $code, 'name' => $name];
                    $chapterCode = $code;
                    $chapterOpen = true;

                    continue;
                }
                if ($first['x'] < $codeLimit && preg_match('/^([A-Za-z0-9_.#-]*\d[A-Za-z0-9_.#-]*)\s+(\S+)\s+(.+)$/u', $text, $match)
                    && (strlen($match[1]) >= 4 || ProjectMeasurementImporter::supportsUnit($match[2]))) {
                    if ($item !== null || $chapterCode === null || ! $chapterOpen || isset($items[$match[1]]) || ! ProjectMeasurementImporter::supportsUnit($match[2])) {
                        $this->fail('Página '.$page['page'].': código, unidad o estructura no reconocidos en «'.$text.'».');
                    }
                    $item = [
                        'code' => $match[1], 'chapter' => $chapterCode, 'unit' => $match[2],
                        'title' => $match[3], 'description' => '', 'details' => [], 'quantity' => null, 'price' => null,
                        'amount' => null, 'source_row' => $rowIndex + 1,
                        'notes' => 'Presupuesto PDF. Página de origen: '.$page['page'].'.',
                    ];

                    continue;
                }
                $totalRow = $explicit ? str_starts_with($ascii, 'TOTAL PARTIDA') : ($first['x'] > 300 && preg_match('/^'.self::NUMBER.'\s+'.self::NUMBER.'\s+'.self::NUMBER.'$/', $text));
                if ($totalRow) {
                    if ($item === null) {
                        $this->fail('Página '.$page['page'].': total sin partida.');
                    }
                    preg_match_all('/'.self::NUMBER.'/', $text, $matches);
                    if (count($matches[0]) !== 3) {
                        $this->fail('Página '.$page['page'].': la partida '.$item['code'].' no tiene cantidad, precio e importe legibles.');
                    }
                    [$quantity, $price, $amount] = array_map(fn (string $value): string => $this->number($value), $matches[0]);
                    if (BigDecimal::of($quantity)->isNegative() || BigDecimal::of($price)->isNegative()
                        || BigDecimal::of($quantity)->isGreaterThan('99999999999999.999999') || BigDecimal::of($price)->isGreaterThan('99999999999999.999999')) {
                        $this->fail('Página '.$page['page'].': cantidad o precio fuera del rango permitido.');
                    }
                    $calculated = BigDecimal::of($quantity)->multipliedBy($price)->toScale(2, RoundingMode::HalfUp);
                    if (! $calculated->isEqualTo($amount)) {
                        $this->fail('Página '.$page['page'].': cantidad × precio no coincide con el importe impreso de '.$item['code'].'.');
                    }
                    $item['quantity'] = (float) $quantity;
                    $item['price'] = (float) $price;
                    $item['amount'] = (float) $amount;
                    $items[$item['code']] = $item;
                    $chapterSums[$chapterCode] = ($chapterSums[$chapterCode] ?? BigDecimal::of(0))->plus($amount);
                    $item = null;

                    continue;
                }
                if ($item !== null) {
                    $numericSpans = [];
                    if ($explicit) {
                        foreach ($row['spans'] as $span) {
                            if ($span['x'] >= $columns['units'] - 5 && preg_match('/^-?[\d.]+(?:,\d+)?$/', $span['text'])) {
                                $numericSpans[] = $span;
                            }
                        }
                    }
                    $isMeasurement = $explicit ? $numericSpans !== [] : abs($first['font'] - $baseFont) < 0.05;
                    if ($isMeasurement) {
                        $detail = ['label' => $text, 'units' => null, 'length' => null, 'width' => null, 'height' => null, 'subtotal' => null, 'source_row' => $rowIndex + 1, 'source_page' => $page['page']];
                        if ($explicit) {
                            $detail['label'] = implode(' ', array_column(array_filter($row['spans'], fn (array $span): bool => $span['x'] < $columns['units'] - 5), 'text'));
                            foreach ($numericSpans as $span) {
                                $distances = [];
                                foreach (['units', 'length', 'width', 'height', 'subtotal'] as $key) {
                                    $distances[$key] = abs($span['x'] - $columns[$key]);
                                }
                                asort($distances);
                                $detail[array_key_first($distances)] = (float) $this->number($span['text']);
                            }
                        }
                        $item['details'][] = $detail;
                    } else {
                        $item['description'] .= ($item['description'] === '' ? '' : "\n").$text;
                    }
                } elseif ($chapterOpen && $explicit && $chapterCode !== null) {
                    $chapters[$chapterCode]['name'] .= ' '.$text;
                }
            }
        }
        if ($item !== null || $items === [] || $chapters === []) {
            $this->fail('No se ha reconocido un presupuesto completo. Use un PDF con texto seleccionable, capítulos, partidas y totales, con uno de los diseños admitidos.');
        }
        $net = BigDecimal::of(0);
        foreach ($chapters as $code => $chapter) {
            if (! isset($chapterTotals[$code], $chapterSums[$code]) || ! $chapterSums[$code]->isEqualTo($chapterTotals[$code])) {
                $this->fail('El capítulo '.$code.' no tiene un total válido o la suma de sus partidas no coincide con el total impreso.');
            }
            if (mb_strlen($chapter['name']) > 255) {
                $this->fail('El nombre del capítulo '.$code.' es demasiado largo. Revise el diseño del PDF.');
            }
            $net = $net->plus($chapterTotals[$code]);
        }
        if ($summaryNet !== null && ! $net->isEqualTo($summaryNet)) {
            $this->fail('La suma de capítulos no coincide con la base total del PDF. No se ha guardado ninguna partida.');
        }
        $instructions = 'Presupuesto importado desde PDF. Total de partidas sin impuestos: '.BudgetEditor::formatAmount((string) $net).' EUR. Los impuestos del resumen no se añaden a los precios unitarios.';
        if ($taxNotes !== []) {
            $instructions .= "\nResumen original: ".implode(' · ', array_unique($taxNotes));
        }
        if ($hasCompactMeasurements) {
            $instructions .= "\nLas líneas de medición del diseño compacto se conservan como texto original. No se deducen dimensiones ni parciales cuando el PDF no separa esas columnas. Las cantidades totales de las partidas se importan de sus filas de precio e importe.";
        }

        return [
            'chapters' => array_values($chapters), 'items' => array_values($items),
            'measurements' => array_sum(array_map(fn (array $value): int => count($value['details']), $items)),
            'instructions' => $instructions, 'net_total' => (string) $net->toScale(2), 'page_count' => count($pages),
        ];
    }

    /**
     * @param  list<array{x: float, text: string, font: float}>  $spans
     * @return array<string, float>
     */
    private function columns(array $spans): array
    {
        $mapping = ['uni' => 'unit', 'de' => 'units', 'largo' => 'length', 'ancho' => 'width', 'alto' => 'height', 'parcial' => 'subtotal'];
        $columns = [];
        foreach ($spans as $span) {
            $key = Str::ascii(mb_strtolower(trim($span['text'])));
            if (isset($mapping[$key])) {
                $columns[$mapping[$key]] = $span['x'];
            }
        }
        if (count($columns) !== 6) {
            $this->fail('No se han podido identificar las columnas de medición del PDF.');
        }

        return $columns;
    }

    private function number(string $value): string
    {
        $value = str_replace('.', '', trim($value));
        $value = str_replace(',', '.', $value);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            $this->fail('Se ha encontrado un número ilegible en el PDF.');
        }

        return $value;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
