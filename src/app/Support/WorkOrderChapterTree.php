<?php

namespace App\Support;

use App\Models\WorkOrder;

final class WorkOrderChapterTree
{
    /** @return list<array<string, mixed>> */
    public function snapshots(WorkOrder $order): array
    {
        $chapters = $order->project->projectChapters()->get()->keyBy('id');
        $items = [];
        foreach ($order->workOrderLines()->orderBy('position')->get() as $line) {
            $path = [];
            $chapter = $chapters->get($line->project_chapter_id);
            $seen = [];
            while ($chapter !== null && ! isset($seen[$chapter->id])) {
                $seen[$chapter->id] = true;
                array_unshift($path, ['id' => $chapter->id, 'code' => $chapter->code, 'name' => $chapter->name]);
                $chapter = $chapters->get($chapter->parent_id);
            }
            $items[] = [
                'source_line_id' => $line->id, 'source_code' => $line->source_code,
                'description' => $line->description, 'unit' => $line->unit_label,
                'quantity' => $line->planned_quantity, 'price' => $line->estimated_unit_sale_price, 'discount_percentage' => $line->discount_percentage,
                'chapter_path' => $path, 'measurement_details' => $line->measurement_details ?? [],
                'budget_breakdown' => $line->budget_breakdown ?? [],
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{key: string, code: string, name: string, item_keys: list<int>, items: list<int>, children: array}>
     */
    public function group(array $items): array
    {
        $roots = [];
        foreach ($items as $index => $item) {
            $nodes = &$roots;
            $path = $item['chapter_path'] ?: [['id' => 'none', 'code' => '—', 'name' => 'Sin capítulo']];
            foreach ($path as $chapter) {
                $key = (string) $chapter['id'];
                $nodes[$key] ??= ['key' => $key, 'code' => $chapter['code'], 'name' => $chapter['name'], 'item_keys' => [], 'items' => [], 'children' => []];
                $nodes[$key]['item_keys'][] = $index;
                $current = &$nodes[$key];
                $nodes = &$current['children'];
            }
            $current['items'][] = $index;
            unset($nodes, $current);
        }

        return array_values($roots);
    }
}
