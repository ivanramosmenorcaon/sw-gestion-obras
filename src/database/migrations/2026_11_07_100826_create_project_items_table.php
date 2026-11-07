<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('work_orders', 'purpose')) {
            Schema::table('work_orders', function (Blueprint $table): void {
                $table->string('purpose', 20)->default('intervention');
            });
        }
        DB::table('work_orders')->whereNotNull('measurement_import_hash')->update(['purpose' => 'measurement']);
        if (! Schema::hasTable('project_items')) {
            Schema::create('project_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('project_chapter_id')->nullable();
                $table->foreignId('source_budget_id')->nullable()->constrained('budgets')->restrictOnDelete();
                $table->unsignedInteger('source_budget_position')->nullable();
                $table->unsignedInteger('position')->default(1);
                $table->string('source_code', 80)->nullable();
                $table->string('type', 20)->default('service');
                $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('product_unit_id')->nullable()->constrained()->restrictOnDelete();
                $table->text('description');
                $table->foreignId('unit_id')->constrained()->restrictOnDelete();
                $table->string('unit_label', 100);
                $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
                $table->decimal('base_unit_factor', 24, 12)->default(1);
                $table->decimal('planned_quantity', 20, 6)->default(0);
                $table->decimal('estimated_unit_cost', 20, 6)->default(0);
                $table->decimal('estimated_unit_sale_price', 20, 6)->default(0);
                $table->decimal('discount_percentage', 7, 4)->default(0);
                $table->json('measurement_details')->nullable();
                $table->json('budget_breakdown')->nullable();
                $table->text('notes')->nullable();
                $table->unique(['project_id', 'id']);
                $table->unique(['source_budget_id', 'source_budget_position'], 'project_items_budget_position_unique');
                $table->foreign(['project_id', 'project_chapter_id'], 'project_items_chapter_scope_fk')->references(['project_id', 'id'])->on('project_chapters')->restrictOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('work_order_lines', 'project_item_id')) {
            Schema::table('work_order_lines', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_item_id')->nullable();
                $table->foreign(['project_id', 'project_item_id'], 'order_lines_project_item_scope_fk')->references(['project_id', 'id'])->on('project_items')->restrictOnDelete();
            });
        }
        DB::table('work_order_lines')->whereNull('project_item_id')->orderBy('id')->chunkById(200, function ($lines): void {
            foreach ($lines as $line) {
                DB::transaction(function () use ($line): void {
                    $line = DB::table('work_order_lines')->where('id', $line->id)->lockForUpdate()->first();
                    if ($line->project_item_id !== null) {
                        return;
                    }
                    $attributes = array_intersect_key((array) $line, array_flip([
                        'project_id', 'project_chapter_id', 'position', 'source_code', 'type', 'product_id', 'product_unit_id',
                        'description', 'unit_id', 'unit_label', 'base_unit_id', 'base_unit_factor', 'planned_quantity',
                        'estimated_unit_cost', 'estimated_unit_sale_price', 'discount_percentage', 'measurement_details',
                        'budget_breakdown', 'notes', 'created_at', 'updated_at',
                    ]));
                    $budgetId = DB::table('work_orders')->where('id', $line->work_order_id)->value('accepted_budget_id');
                    $attributes['source_budget_id'] = $budgetId;
                    $attributes['source_budget_position'] = $budgetId ? $line->position : null;
                    $itemId = DB::table('project_items')->insertGetId($attributes);
                    DB::table('work_order_lines')->where('id', $line->id)->update(['project_item_id' => $itemId]);
                });
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('project_items')->exists()) {
            throw new RuntimeException('La reversión eliminaría partidas de obra. Conserve los datos y aplique una migración correctiva.');
        }
        Schema::table('work_order_lines', function (Blueprint $table): void {
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['project_id', 'project_item_id'] : 'order_lines_project_item_scope_fk');
            $table->dropColumn('project_item_id');
        });
        Schema::dropIfExists('project_items');
        Schema::table('work_orders', fn (Blueprint $table) => $table->dropColumn('purpose'));
    }
};
