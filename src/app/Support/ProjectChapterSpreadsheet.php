<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectChapter;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProjectChapterSpreadsheet
{
    private const HEADERS = ['Código', 'Nombre', 'Código padre', 'Descripción', 'Posición', 'Activo'];

    public function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $path = tempnam(sys_get_temp_dir(), 'chapters-');

            try {
                $this->writeTemplate($path);
                readfile($path);
            } finally {
                unlink($path);
            }
        }, 'plantilla-capitulos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function writeTemplate(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('Capítulos');
            $writer->addRow(Row::fromValues(self::HEADERS, (new Style)->setFontBold()));
            foreach (['Demoliciones', 'Albañilería', 'Electricidad', 'Fontanería', 'Pintura'] as $index => $name) {
                $writer->addRow(Row::fromValues([sprintf('%02d', $index + 1), $name, '', '', $index + 1, 1]));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');
            foreach ([
                'Edite la primera hoja (Capítulos). Solo se importa esa hoja. Conserve las cabeceras.',
                'Los capítulos incluidos son ejemplos editables. Añada o quite filas según su plantilla habitual.',
                'Código y Nombre son obligatorios. Mantenga los códigos como texto para conservar ceros iniciales.',
                'Código padre es opcional: indique el código de otro capítulo del archivo o de la misma obra.',
                'Los padres pueden aparecer después de sus hijos. No se permiten referencias circulares.',
                'Descripción es opcional. Posición debe ser un entero mayor que cero. Activo debe ser 1 o 0.',
                'La importación añade códigos nuevos a la obra seleccionada y omite los que ya existen sin modificarlos.',
                'No repita códigos dentro del archivo. Máximo 1000 capítulos y 2 MB por archivo .xlsx.',
                'Si hay errores no se guarda ningún capítulo. Corrija la fila indicada y vuelva a importar.',
            ] as $instruction) {
                $writer->addRow(Row::fromValues([$instruction]));
            }
        } finally {
            $writer->close();
        }
    }

    /** @return array{created: int, skipped: int} */
    public function import(Project $project, string $path): array
    {
        $rows = $this->readRows($path);

        return DB::transaction(function () use ($project, $rows): array {
            Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            $chapters = $project->projectChapters()->get()->keyBy('code');
            $pending = [];
            $skipped = 0;

            foreach ($rows as $code => $row) {
                if ($chapters->has($code)) {
                    $skipped++;
                } else {
                    $pending[$code] = $row;
                }
            }

            $created = 0;
            while ($pending !== []) {
                $progress = false;
                foreach ($pending as $code => $row) {
                    $parentCode = $row['parent_code'];
                    if ($parentCode !== '' && ! $chapters->has($parentCode)) {
                        continue;
                    }
                    $chapter = new ProjectChapter([
                        'project_id' => $project->getKey(),
                        'parent_id' => $parentCode === '' ? null : $chapters->get($parentCode)->getKey(),
                        'code' => (string) $code,
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'position' => $row['position'],
                        'is_active' => $row['is_active'],
                    ]);
                    try {
                        $chapter->save();
                    } catch (ValidationException $exception) {
                        $this->fail('Fila '.$row['line'].': '.collect($exception->errors())->flatten()->implode(' '));
                    }
                    $chapters->put($code, $chapter);
                    unset($pending[$code]);
                    $created++;
                    $progress = true;
                }
                if (! $progress) {
                    $row = reset($pending);
                    $this->fail('Fila '.$row['line'].': el código padre no existe en esta obra ni en el archivo, o hay una referencia circular.');
                }
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /** @return array<array-key, array{name: string, parent_code: string, description: string, position: string, is_active: string, line: int}> */
    private function readRows(string $path): array
    {
        $reader = new Reader;
        $rows = [];
        $hasHeader = false;

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    foreach ($row->getCells() as $cell) {
                        if ($cell instanceof FormulaCell) {
                            $this->fail('Fila '.$line.': sustituya las fórmulas por valores de texto o números.');
                        }
                    }
                    $values = $row->toArray();
                    if (! $hasHeader) {
                        if ($values !== self::HEADERS) {
                            $this->fail('Las cabeceras no coinciden. Utilice la plantilla descargable sin cambiar sus cabeceras.');
                        }
                        $hasHeader = true;

                        continue;
                    }
                    if (count($values) > 6) {
                        $this->fail('Fila '.$line.': hay más columnas de las esperadas.');
                    }
                    $values = array_pad($values, 6, '');
                    foreach ($values as &$value) {
                        if (! is_scalar($value) && $value !== null) {
                            $this->fail('Fila '.$line.': utilice texto y números, sin fechas ni fórmulas.');
                        }
                        $value = trim((string) $value);
                    }
                    unset($value);
                    if (implode('', $values) === '') {
                        continue;
                    }
                    [$code, $name, $parentCode, $description, $position, $active] = $values;
                    $validator = Validator::make([
                        'code' => $code, 'name' => $name, 'parent_code' => $parentCode,
                        'description' => $description, 'position' => $position, 'is_active' => $active,
                    ], [
                        'code' => ['required', 'string', 'max:50'],
                        'name' => ['required', 'string', 'max:255'],
                        'parent_code' => ['nullable', 'string', 'max:50'],
                        'description' => ['nullable', 'string'],
                        'position' => ['required', 'integer', 'min:1', 'max:4294967295'],
                        'is_active' => ['required', 'boolean'],
                    ], [], __('business.fields'));
                    if ($validator->fails()) {
                        $this->fail('Fila '.$line.': '.$validator->errors()->all()[0]);
                    }
                    if (array_key_exists($code, $rows)) {
                        $this->fail('Fila '.$line.': el código '.$code.' está repetido en el archivo.');
                    }
                    $rows[$code] = [
                        'name' => $name, 'parent_code' => $parentCode, 'description' => $description,
                        'position' => $position, 'is_active' => $active, 'line' => $line,
                    ];
                    if (count($rows) > 1000) {
                        $this->fail('El archivo no puede contener más de 1000 capítulos.');
                    }
                }
                break;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            report($exception);
            $this->fail('No se ha podido leer el archivo. Suba un archivo Excel .xlsx válido basado en la plantilla.');
        } finally {
            $reader->close();
        }
        if ($rows === []) {
            $this->fail('El archivo no contiene capítulos para importar.');
        }

        return $rows;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
