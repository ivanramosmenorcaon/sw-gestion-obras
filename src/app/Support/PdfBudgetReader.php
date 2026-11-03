<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfBudgetReader
{
    /** @return list<array{page: int, rows: list<array{y: float, text: string, spans: list<array{x: float, text: string, font: float}>}>}> */
    public function pages(string $path): array
    {
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024 || file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw ValidationException::withMessages(['file' => 'Seleccione un PDF válido de hasta 10 MB.']);
        }
        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(32 * 1024 * 1024);
            $document = (new Parser([], $config))->parseFile($path);
            $pdfPages = $document->getPages();
            if ($pdfPages === [] || count($pdfPages) > 100) {
                throw ValidationException::withMessages(['file' => 'El PDF debe tener entre 1 y 100 páginas.']);
            }
            $pages = [];
            $spanCount = 0;
            foreach ($pdfPages as $index => $page) {
                $spans = [];
                foreach ($page->getDataTm() as $data) {
                    $text = trim(preg_replace('/\s+/u', ' ', str_replace(["\u{00A0}", "\u{2212}"], [' ', '-'], $data[1])));
                    if ($text === '') {
                        continue;
                    }
                    if (++$spanCount > 100000) {
                        throw ValidationException::withMessages(['file' => 'El PDF contiene demasiado texto para esta importación.']);
                    }
                    $spans[] = ['x' => (float) $data[0][4], 'y' => (float) $data[0][5], 'text' => $text, 'font' => abs((float) $data[0][0])];
                }
                usort($spans, fn (array $a, array $b): int => $b['y'] <=> $a['y']);
                $rows = [];
                foreach ($spans as $span) {
                    $last = array_key_last($rows);
                    if ($last === null || abs($rows[$last]['y'] - $span['y']) > 1.2) {
                        $rows[] = ['y' => $span['y'], 'spans' => [], 'text' => ''];
                        $last = array_key_last($rows);
                    }
                    unset($span['y']);
                    $rows[$last]['spans'][] = $span;
                }
                foreach ($rows as &$row) {
                    usort($row['spans'], fn (array $a, array $b): int => $a['x'] <=> $b['x']);
                    $row['text'] = implode(' ', array_column($row['spans'], 'text'));
                }
                unset($row);
                $pages[] = ['page' => $index + 1, 'rows' => $rows];
            }

            return $pages;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'No se ha podido leer el PDF. Debe contener texto seleccionable y no estar protegido con contraseña.']);
        }
    }
}
