<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('school:backup-database')]
#[Description('Create a verified database snapshot in private storage before a school migration')]
class BackupSchoolDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = DB::connection();
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/before-school-isolation-'.now()->format('Ymd-Hisu').'.sql.gz';
        $temporaryPath = $path.'.partial';
        $file = gzopen($temporaryPath, 'wb9');
        if (! $file) {
            throw new \RuntimeException('The private backup file could not be opened.');
        }
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            gzclose($file);
            throw new \RuntimeException('A verified backup is required for this database driver before migration.');
        }
        $this->write($file, "-- SIMS pre-school-isolation backup\n");
        if ($driver === 'mysql') {
            $this->write($file, "SET FOREIGN_KEY_CHECKS=0;\n");
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
        $connection->beginTransaction();
        try {
            foreach ($connection->getSchemaBuilder()->getTables($driver === 'mysql' ? $connection->getDatabaseName() : 'main') as $table) {
                $name = $table['name'];
                $quoted = $driver === 'mysql' ? '`'.str_replace('`', '``', $name).'`' : '"'.str_replace('"', '""', $name).'"';
                $create = $driver === 'mysql'
                    ? array_values((array) $connection->selectOne('SHOW CREATE TABLE '.$quoted))[1]
                    : $connection->selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$name])->sql;
                $this->write($file, 'DROP TABLE IF EXISTS '.$quoted.";\n".$create.";\n");
                foreach ($connection->table($name)->cursor() as $row) {
                    $values = array_map(fn ($value) => $value === null ? 'NULL' : $connection->getPdo()->quote((string) $value), (array) $row);
                    $this->write($file, 'INSERT INTO '.$quoted.' VALUES ('.implode(',', $values).");\n");
                }
            }
            if ($driver === 'mysql') {
                $this->write($file, "SET FOREIGN_KEY_CHECKS=1;\n");
            } else {
                foreach ($connection->select("SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL") as $index) {
                    $this->write($file, $index->sql.";\n");
                }
            }
            $this->write($file, "-- END VERIFIED DATABASE SNAPSHOT\n");
            $connection->commit();
        } catch (\Throwable $error) {
            $connection->rollBack();
            throw $error;
        } finally {
            gzclose($file);
        }
        $verify = gzopen($temporaryPath, 'rb');
        $verifiedBytes = 0;
        $tail = '';
        while (! gzeof($verify)) {
            $chunk = gzread($verify, 1024 * 1024);
            if ($chunk === false) {
                throw new \RuntimeException('The database backup verification failed.');
            }
            $verifiedBytes += strlen($chunk);
            $tail = substr($tail.$chunk, -100);
        }
        gzclose($verify);
        if ($verifiedBytes < 100 || ! str_contains($tail, '-- END VERIFIED DATABASE SNAPSHOT')) {
            throw new \RuntimeException('The database backup is incomplete.');
        }
        if (! rename($temporaryPath, $path)) {
            throw new \RuntimeException('The verified snapshot could not be finalized.');
        }
        $this->info('Private database snapshot verified: '.$path);

        return self::SUCCESS;
    }

    private function write(mixed $file, string $contents): void
    {
        if (gzwrite($file, $contents) !== strlen($contents)) {
            throw new \RuntimeException('The database snapshot could not be fully written.');
        }
    }
}
