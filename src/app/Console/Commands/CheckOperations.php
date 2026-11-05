<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('operations:check')]
#[Description('Comprueba pendientes técnicos de operación sin mostrar secretos')]
class CheckOperations extends Command
{
    public function handle(): int
    {
        $problems = [];
        if (! app()->environment('production')) {
            $problems[] = 'APP_ENV no es production.';
        }
        if (config('app.debug')) {
            $problems[] = 'APP_DEBUG está activado.';
        }
        if (! str_starts_with(config('app.url'), 'https://')) {
            $problems[] = 'APP_URL no utiliza HTTPS.';
        }
        if (DB::table('failed_jobs')->exists()) {
            $problems[] = 'Hay tareas fallidas en la cola.';
        }
        $backups = glob(storage_path('app/private/backups/operations-*.sql.enc')) ?: [];
        if ($backups === [] || max(array_map('filemtime', $backups)) < time() - 86400 * 2) {
            $problems[] = 'No se encuentra una copia cifrada reciente.';
        }
        $certificate = config('billing.certificate_path');
        if ($certificate && is_readable($certificate)) {
            $details = openssl_x509_parse(file_get_contents($certificate));
            if ($details && ($details['validTo_time_t'] ?? 0) < time() + 86400 * 30) {
                $problems[] = 'El certificado fiscal caduca en menos de 30 días o ya está caducado.';
            }
        }
        foreach ($problems as $problem) {
            $this->warn($problem);
        }
        $this->line('El scheduler y los workers deben estar supervisados por el despliegue. Este comando no acredita que estén activos.');
        $this->line('La emisión fiscal real continúa bloqueada hasta su validación.');

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
