<?php

namespace App\Support;

/**
 * @phpstan-type Node array{key: int, code: string, name: string, unit: string, price: ?float, factor: float, quantity: float, description: string, kind: string, color: string, icon: string, parent_code: string, depth: int, children: list<Node>}
 */
final class BudgetBreakdownTree
{
    /**
     * @param  list<array{code?: string, name?: string, unit?: string, price?: ?float, factor?: float, quantity?: float, description?: string, type?: string, parent_code?: string, depth?: int}>  $rows
     * @return list<Node>
     */
    public function build(array $rows): array
    {
        $position = 0;

        return $this->children($rows, $position, 0);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<Node>
     */
    private function children(array $rows, int &$position, int $parentDepth): array
    {
        $nodes = [];
        while ($position < count($rows)) {
            $row = $rows[$position];
            $depth = (int) ($row['depth'] ?? 1);
            if ($depth <= $parentDepth) {
                break;
            }
            $key = $position++;
            $children = $this->children($rows, $position, $depth);
            [$kind, $color, $icon] = match (true) {
                ($row['unit'] ?? '') === '%' => ['Porcentaje / medios auxiliares', 'gray', 'heroicon-o-calculator'],
                (string) ($row['type'] ?? '') === '1' => ['Mano de obra', 'info', 'heroicon-o-user-group'],
                (string) ($row['type'] ?? '') === '2' => ['Maquinaria', 'warning', 'heroicon-o-truck'],
                (string) ($row['type'] ?? '') === '3' => ['Material', 'success', 'heroicon-o-cube'],
                (string) ($row['type'] ?? '') === '4' => ['Residuo', 'danger', 'heroicon-o-trash'],
                $children !== [] => ['Auxiliar compuesto', 'primary', 'heroicon-o-squares-2x2'],
                default => ['Concepto auxiliar', 'gray', 'heroicon-o-puzzle-piece'],
            };
            $nodes[] = [
                'key' => $key, 'code' => (string) ($row['code'] ?? ''), 'name' => (string) ($row['name'] ?? ''),
                'unit' => (string) ($row['unit'] ?? ''), 'price' => isset($row['price']) ? (float) $row['price'] : null,
                'factor' => (float) ($row['factor'] ?? 1), 'quantity' => (float) ($row['quantity'] ?? 1),
                'description' => (string) ($row['description'] ?? ''), 'kind' => $kind, 'color' => $color, 'icon' => $icon,
                'parent_code' => (string) ($row['parent_code'] ?? ''), 'depth' => $depth, 'children' => $children,
            ];
        }

        return $nodes;
    }
}
