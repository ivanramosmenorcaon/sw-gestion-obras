<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectExecutionSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_an_order_can_plan_catalog_and_free_text_lines_across_chapters(): void
    {
        $projectId = $this->createProject('P-1');
        $orderId = $this->createOrder($projectId, 'OT-1');
        $chapterId = $this->createChapter($projectId, '01');
        $secondChapterId = $this->createChapter($projectId, '02');
        $phaseId = $this->createPhase($orderId);
        $unitId = $this->createUnit();
        $productId = DB::table('products')->insertGetId(['reference' => 'CABLE', 'name' => 'Cable', 'type' => 'material', 'base_unit_id' => $unitId]);
        $presentationId = DB::table('product_units')->insertGetId(['product_id' => $productId, 'code' => 'roll20', 'name' => 'Rollo de 20 metros', 'unit_id' => $unitId, 'base_unit_factor' => '20']);

        DB::table('work_order_lines')->insert([
            'project_id' => $projectId, 'work_order_id' => $orderId, 'project_chapter_id' => $chapterId,
            'work_order_phase_id' => $phaseId, 'position' => 1, 'type' => 'material',
            'product_id' => $productId, 'product_unit_id' => $presentationId, 'description' => 'Rollo de cable',
            'unit_id' => $unitId, 'unit_label' => 'Rollo de 20 metros', 'base_unit_id' => $unitId,
            'base_unit_factor' => '20', 'planned_quantity' => '2.5', 'estimated_unit_cost' => '12.123456',
        ]);
        DB::table('work_order_lines')->insert([
            'project_id' => $projectId, 'work_order_id' => $orderId, 'project_chapter_id' => $secondChapterId,
            'position' => 2, 'type' => 'service', 'description' => 'Trabajo especial sin ficha de producto',
            'unit_id' => $unitId, 'unit_label' => 'Metro', 'base_unit_id' => $unitId,
        ]);

        $this->assertDatabaseCount('work_order_lines', 2);
        $this->assertDatabaseHas('work_order_lines', ['work_order_id' => $orderId, 'position' => 1, 'base_unit_factor' => 20, 'estimated_unit_cost' => '12.123456']);
        $this->assertDatabaseHas('work_order_lines', ['work_order_id' => $orderId, 'position' => 2, 'product_id' => null, 'project_chapter_id' => $secondChapterId]);
    }

    #[DataProvider('invalidCrossReferences')]
    public function test_rejects_cross_project_and_cross_order_references(string $scenario): void
    {
        $projectId = $this->createProject('P-1');
        $otherProjectId = $this->createProject('P-2');
        $orderId = $this->createOrder($projectId, 'OT-1');
        $otherOrderId = $this->createOrder($otherProjectId, 'OT-2');
        $otherChapterId = $this->createChapter($otherProjectId, '01');
        $otherPhaseId = $this->createPhase($otherOrderId);
        $unitId = $this->createUnit();
        $employeeId = DB::table('employees')->insertGetId(['code' => 'E-1', 'name' => 'Operario']);
        $contractorId = DB::table('parties')->insertGetId(['type' => 'organization', 'legal_name' => 'Contratista']);
        $line = ['project_id' => $projectId, 'work_order_id' => $orderId, 'position' => 1, 'type' => 'material', 'description' => 'Material', 'unit_id' => $unitId, 'unit_label' => 'Metro', 'base_unit_id' => $unitId];

        [$table, $row] = match ($scenario) {
            'chapter_parent' => ['project_chapters', ['project_id' => $projectId, 'parent_id' => $otherChapterId, 'code' => '02', 'name' => 'Capítulo inválido']],
            'line_order' => ['work_order_lines', array_replace($line, ['work_order_id' => $otherOrderId])],
            'line_chapter' => ['work_order_lines', array_replace($line, ['project_chapter_id' => $otherChapterId])],
            'line_phase' => ['work_order_lines', array_replace($line, ['work_order_phase_id' => $otherPhaseId])],
            'employee_phase' => ['work_order_employee_assignments', ['work_order_id' => $orderId, 'work_order_phase_id' => $otherPhaseId, 'employee_id' => $employeeId]],
            'contractor_phase' => ['work_order_contractor_assignments', ['work_order_id' => $orderId, 'work_order_phase_id' => $otherPhaseId, 'contractor_id' => $contractorId, 'scope' => 'Instalación']],
        };

        $this->expectException(QueryException::class);

        DB::table($table)->insert($row);
    }

    /** @return array<string, array{string}> */
    public static function invalidCrossReferences(): array
    {
        return [
            'capítulo de otra obra' => ['chapter_parent'],
            'orden de otra obra' => ['line_order'],
            'capítulo de línea de otra obra' => ['line_chapter'],
            'fase de otra orden en línea' => ['line_phase'],
            'fase de otra orden en asignación de empleado' => ['employee_phase'],
            'fase de otra orden en asignación de contratista' => ['contractor_phase'],
        ];
    }

    public function test_employees_without_login_and_contractors_can_share_an_order(): void
    {
        $projectId = $this->createProject('P-1');
        $orderId = $this->createOrder($projectId, 'OT-1');
        $phaseId = $this->createPhase($orderId);
        $employeeId = DB::table('employees')->insertGetId(['code' => 'E-1', 'name' => 'Operario sin acceso']);
        $contractorId = DB::table('parties')->insertGetId(['type' => 'organization', 'legal_name' => 'Instalador externo']);
        DB::table('party_roles')->insert(['party_id' => $contractorId, 'role' => 'subcontractor']);

        DB::table('work_order_employee_assignments')->insert(['work_order_id' => $orderId, 'employee_id' => $employeeId, 'planned_hours' => '12.5']);
        DB::table('work_order_contractor_assignments')->insert(['work_order_id' => $orderId, 'work_order_phase_id' => $phaseId, 'contractor_id' => $contractorId, 'scope' => 'Montaje de instalación']);

        $this->assertDatabaseHas('employees', ['id' => $employeeId, 'user_id' => null]);
        $this->assertDatabaseHas('work_order_employee_assignments', ['employee_id' => $employeeId, 'planned_hours' => '12.5', 'work_order_phase_id' => null]);
        $this->assertDatabaseHas('work_order_contractor_assignments', ['contractor_id' => $contractorId, 'work_order_phase_id' => $phaseId]);
    }

    public function test_a_project_with_execution_records_cannot_be_deleted(): void
    {
        $projectId = $this->createProject('P-1');
        $this->createOrder($projectId, 'OT-1');

        $this->expectException(QueryException::class);

        DB::table('projects')->where('id', $projectId)->delete();
    }

    public function test_an_order_line_cannot_use_another_products_presentation(): void
    {
        $projectId = $this->createProject('P-1');
        $orderId = $this->createOrder($projectId, 'OT-1');
        $unitId = $this->createUnit();
        $firstProductId = DB::table('products')->insertGetId(['reference' => 'A', 'name' => 'Producto A', 'type' => 'material', 'base_unit_id' => $unitId]);
        $secondProductId = DB::table('products')->insertGetId(['reference' => 'B', 'name' => 'Producto B', 'type' => 'material', 'base_unit_id' => $unitId]);
        $presentationId = DB::table('product_units')->insertGetId(['product_id' => $firstProductId, 'code' => 'base', 'name' => 'Metro', 'unit_id' => $unitId, 'base_unit_factor' => 1]);

        $this->expectException(QueryException::class);

        DB::table('work_order_lines')->insert([
            'project_id' => $projectId, 'work_order_id' => $orderId, 'position' => 1,
            'product_id' => $secondProductId, 'product_unit_id' => $presentationId,
            'type' => 'material', 'description' => 'Material', 'unit_id' => $unitId,
            'unit_label' => 'Metro', 'base_unit_id' => $unitId,
        ]);
    }

    public function test_chapter_codes_can_repeat_in_different_projects_but_not_in_one_project(): void
    {
        $projectId = $this->createProject('P-1');
        $otherProjectId = $this->createProject('P-2');
        $this->createChapter($projectId, '01');
        $this->createChapter($otherProjectId, '01');

        $this->assertDatabaseCount('project_chapters', 2);
        $this->expectException(QueryException::class);

        $this->createChapter($projectId, '01');
    }

    private function createProject(string $reference): int
    {
        $customerId = DB::table('parties')->insertGetId(['type' => 'organization', 'legal_name' => 'Cliente '.$reference]);

        return DB::table('projects')->insertGetId(['reference' => $reference, 'name' => 'Obra '.$reference, 'customer_id' => $customerId]);
    }

    private function createOrder(int $projectId, string $reference): int
    {
        return DB::table('work_orders')->insertGetId(['project_id' => $projectId, 'reference' => $reference, 'name' => 'Orden '.$reference]);
    }

    private function createChapter(int $projectId, string $code): int
    {
        return DB::table('project_chapters')->insertGetId(['project_id' => $projectId, 'code' => $code, 'name' => 'Capítulo '.$code]);
    }

    private function createPhase(int $orderId): int
    {
        return DB::table('work_order_phases')->insertGetId(['work_order_id' => $orderId, 'code' => 'montaje', 'name' => 'Montaje']);
    }

    private function createUnit(): int
    {
        return DB::table('units')->insertGetId(['code' => 'meter', 'name' => 'Metro', 'symbol' => 'm', 'magnitude' => 'length']);
    }
}
