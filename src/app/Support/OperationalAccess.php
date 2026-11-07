<?php

namespace App\Support;

use App\Models\AuditEntry;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Project;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhase;
use App\Models\WorkTimeEntry;
use App\Models\WorkTimeReversal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OperationalAccess
{
    public const ROLES = ['administrator' => 'Administrador', 'office' => 'Administración', 'manager' => 'Jefe de obra', 'foreman' => 'Encargado', 'worker' => 'Operario'];

    public static function user(): ?User
    {
        return auth()->user();
    }

    public static function canSeeCosts(): bool
    {
        return in_array(self::user()?->role, ['administrator', 'office', 'manager'], true);
    }

    /** @param class-string<Model> $model */
    public static function allows(string $model, string $operation, ?Model $record = null): bool
    {
        $user = self::user();
        if (! $user?->is_active) {
            return false;
        }
        if ($model === AuditEntry::class) {
            return $user->role === 'administrator' && in_array($operation, ['viewAny', 'view'], true);
        }
        if ($model === StockMovement::class && ! in_array($user->role, ['administrator', 'office'], true)) {
            return false;
        }
        if ($model === User::class) {
            return $user->role === 'administrator' && ($operation !== 'delete' || $record?->id !== $user->id);
        }
        if (in_array($user->role, ['administrator', 'office'], true)) {
            return true;
        }
        if ($user->role === 'worker') {
            if ($model !== WorkTimeEntry::class || $operation === 'delete') {
                return false;
            }

            return $record === null || ($record->employee_id === Employee::where('user_id', $user->id)->value('id') && ($operation !== 'update' || $record->status === 'draft'));
        }
        if (in_array($user->role, ['manager', 'foreman'], true)) {
            if ($user->role === 'foreman' && ! in_array($model, [WorkTimeEntry::class], true)) {
                return false;
            }
            if (in_array(class_basename($model), ['CompanySetting', 'InvoiceSeries', 'TaxRate', 'TaxProfile'], true) && ! in_array($operation, ['view', 'viewAny'], true)) {
                return false;
            }
            if ($operation === 'delete' || ($operation === 'create' && in_array(class_basename($model), ['CompanySetting', 'InvoiceSeries', 'TaxRate', 'TaxProfile'], true))) {
                return false;
            }

            return $record === null || self::scope($model::query(), $user)->whereKey($record->getKey())->exists();
        }

        return false;
    }

    public static function scope(Builder $query, ?User $user = null): Builder
    {
        $user ??= self::user();
        if (! $user || in_array($user->role, ['administrator', 'office'], true)) {
            return $query;
        }
        $model = $query->getModel();
        $employeeId = Employee::where('user_id', $user->id)->value('id');
        $projectIds = Project::query()->where('manager_id', $user->id)->select('id');
        $orderIds = WorkOrder::query()->where(function (Builder $orders) use ($employeeId, $projectIds): void {
            $orders->whereIn('project_id', $projectIds);
            if ($employeeId) {
                $orders->orWhere('supervisor_id', $employeeId)->orWhereHas('workOrderEmployeeAssignments', fn (Builder $assignments) => $assignments->where('employee_id', $employeeId)->where('status', '!=', 'cancelled'));
            }
        })->select('id');
        if ($model instanceof PurchaseInvoiceLine) {
            return $query->whereHas('purchaseInvoice', fn (Builder $q) => $q->whereIn('project_id', $projectIds));
        }
        if ($model instanceof PurchaseOrderLine) {
            return $query->whereHas('purchaseOrder', fn (Builder $q) => $q->whereIn('project_id', $projectIds));
        }
        if ($model instanceof WorkTimeReversal) {
            return $query->whereHas('workTimeEntry', fn (Builder $q) => $q->whereIn('work_order_id', $orderIds));
        }
        if ($model instanceof EmployeeAbsence) {
            return $user->role === 'worker' ? $query->where('employee_id', $employeeId ?? 0) : $query;
        }
        if ($model instanceof Employee) {
            return $user->role === 'worker' ? $query->whereKey($employeeId ?? 0) : $query;
        }
        if ($model instanceof Project) {
            return $query->where(fn (Builder $q) => $q->where('manager_id', $user->id)->orWhereHas('workOrders', fn (Builder $q) => $q->whereIn('id', $orderIds)));
        }
        if ($model instanceof WorkOrder) {
            return $query->whereIn($model->qualifyColumn('id'), $orderIds);
        }
        if ($model instanceof WorkOrderPhase) {
            return $query->whereIn('work_order_id', $orderIds);
        }
        if ($model instanceof WorkTimeEntry && $user->role === 'worker') {
            return $query->where('employee_id', $employeeId ?? 0);
        }
        if (in_array($model->getTable(), ['project_items', 'budgets', 'project_chapters', 'project_activities', 'project_expenses', 'contractor_agreements', 'contractor_certificates', 'purchase_orders', 'purchase_invoices', 'payments', 'payment_applications', 'invoice_maturities', 'payment_reversals', 'project_documents', 'work_progress'], true)) {
            return $query->whereIn('project_id', Project::where('manager_id', $user->id)->select('id'));
        }
        if (in_array($model->getTable(), ['work_order_lines', 'work_time_entries', 'material_consumptions', 'work_order_employee_assignments', 'work_order_contractor_assignments', 'sales_invoices'], true)) {
            return $query->whereIn('work_order_id', $orderIds);
        }

        return $user->role === 'worker' ? $query->whereRaw('1 = 0') : $query;
    }
}
