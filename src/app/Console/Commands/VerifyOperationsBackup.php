<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

#[Signature('operations:verify-backup {file : Nombre de una copia operations-*.sql.enc} {--restore : Ensayar restauración en una base MySQL temporal aislada y eliminarla al terminar}')]
#[Description('Verifica una copia cifrada y permite ensayar su restauración aislada')]
class VerifyOperationsBackup extends Command
{
    public function handle(): int
    {
        $file = $this->argument('file');
        if (basename($file) !== $file || ! preg_match('/^operations-[0-9-]+\.sql\.enc$/', $file)) {
            $this->error('Indique solo el nombre de una copia operations-*.sql.enc.');

            return self::FAILURE;
        }
        $path = storage_path('app/private/backups/'.$file);
        if (! is_file($path) || ! is_file($path.'.sha256') || ! hash_equals(trim(file_get_contents($path.'.sha256')), hash_file('sha256', $path))) {
            $this->error('La copia falta o su checksum no coincide.');

            return self::FAILURE;
        }
        $dump = Crypt::decryptString(file_get_contents($path));
        $this->info('Checksum y descifrado correctos.');
        if (! $this->option('restore')) {
            return self::SUCCESS;
        }
        if (config('database.default') !== 'mysql') {
            $this->error('El ensayo requiere la conexión MySQL.');

            return self::FAILURE;
        }
        $connection = config('database.connections.mysql');
        $pdo = new \PDO('mysql:host='.$connection['host'].';port='.$connection['port'].';charset=utf8mb4', $connection['username'], $connection['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $database = 'operations_restore_'.bin2hex(random_bytes(6));
        try {
            $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4');
        } catch (\PDOException $exception) {
            $this->error('La cuenta no puede crear la base temporal aislada. El descifrado se verificó, pero el ensayo de restauración no se realizó.');

            return self::FAILURE;
        }
        try {
            $pdo->exec('USE `'.$database.'`');
            $pdo->exec($dump);
            $count = $pdo->query('SHOW TABLES')->rowCount();
            if ($count < 1) {
                throw new \RuntimeException('La restauración no contiene tablas.');
            }
            if (preg_match('/-- SNAPSHOT_COUNTS: ([A-Za-z0-9+\/=]+)/', $dump, $match)) {
                foreach (json_decode(base64_decode($match[1]), true, flags: JSON_THROW_ON_ERROR) as $table => $expected) {
                    if ((int) $pdo->query('SELECT COUNT(*) FROM `'.str_replace('`', '``', $table).'`')->fetchColumn() !== $expected) {
                        throw new \RuntimeException('La cantidad de registros restaurados no coincide.');
                    }
                }
            }
            $this->info('Restauración aislada correcta: '.$count.' tablas. La base temporal se elimina al terminar.');
        } finally {
            $pdo->exec('DROP DATABASE `'.$database.'`');
        }

        return self::SUCCESS;
    }
}
