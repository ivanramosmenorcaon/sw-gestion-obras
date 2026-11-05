<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('operations:backup')]
#[Description('Crea y verifica una copia SQL cifrada y privada de MySQL')]
class BackupOperations extends Command
{
    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error('Esta copia requiere MySQL.');

            return self::FAILURE;
        }
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory, 0700);
        $pdo = DB::connection()->getPdo();
        $pdo->beginTransaction();
        try {
            $dump = "SET FOREIGN_KEY_CHECKS=0;\n";
            $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_COLUMN);
            $counts = [];
            foreach ($tables as $table) {
                $quoted = '`'.str_replace('`', '``', $table).'`';
                $definition = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(\PDO::FETCH_NUM)[1];
                $dump .= 'DROP TABLE IF EXISTS '.$quoted.";\n".$definition.";\n";
                $rows = $pdo->query('SELECT * FROM '.$quoted);
                $counts[$table] = 0;
                while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                    $counts[$table]++;
                    $columns = implode(',', array_map(fn (string $column): string => '`'.str_replace('`', '``', $column).'`', array_keys($row)));
                    $values = implode(',', array_map(fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), $row));
                    $dump .= 'INSERT INTO '.$quoted.' ('.$columns.') VALUES ('.$values.");\n";
                }
            }
            $dump .= "SET FOREIGN_KEY_CHECKS=1;\n-- SNAPSHOT_COUNTS: ".base64_encode(json_encode($counts, JSON_THROW_ON_ERROR))."\n";
            $pdo->commit();
            $path = $directory.'/operations-'.now()->format('Ymd-His').'.sql.enc';
            file_put_contents($path, Crypt::encryptString($dump), LOCK_EX);
            chmod($path, 0600);
            if (! hash_equals(hash('sha256', $dump), hash('sha256', Crypt::decryptString(file_get_contents($path))))) {
                throw new \RuntimeException('No se pudo verificar la copia cifrada.');
            }
            file_put_contents($path.'.sha256', hash_file('sha256', $path)."\n");
            chmod($path.'.sha256', 0600);
            $this->info('Copia cifrada y verificada: '.basename($path));
            $this->warn('Conserve APP_KEY separada y una copia externa. Esta comprobación no sustituye un ensayo de restauración.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
