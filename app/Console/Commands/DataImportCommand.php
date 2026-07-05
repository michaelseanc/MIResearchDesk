<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MigratesData;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Import domain data produced by data:export into THIS environment (e.g. production MySQL). Preserves
 * primary keys (so all cross-table links stay intact) and remaps user-reference columns to this
 * environment's owner (source user ids don't exist here). Auth/system tables are never touched, so
 * the existing logins/2FA/roles survive. Streams row-by-row and inserts in chunks — memory-safe.
 *
 * Idempotent: each table is cleared and reloaded, so it can be re-run.
 */
class DataImportCommand extends Command
{
    use MigratesData;

    protected $signature = 'data:import
        {--path=migration : subfolder under storage/app to read from}
        {--owner= : user id to attribute imported records to (defaults to the first user)}';

    protected $description = 'Import domain data exported by data:export into this environment.';

    public function handle(): int
    {
        $dir = storage_path('app/' . $this->option('path'));
        if (! is_dir($dir)) {
            $this->error("No export folder at {$dir}. Copy the export here first.");

            return self::FAILURE;
        }

        $ownerId = $this->option('owner') ?: optional(DB::table('users')->orderBy('id')->first())->id;
        if (! $ownerId) {
            $this->error('No user exists in this environment to attribute records to. Create the owner first.');

            return self::FAILURE;
        }
        $this->info("Importing into this environment; attributing authored records to user #{$ownerId}.");

        $mysql = DB::getDriverName() === 'mysql';
        $mysql ? DB::statement('SET FOREIGN_KEY_CHECKS=0') : DB::statement('PRAGMA foreign_keys=OFF');

        $userCols = $this->userColumns();
        $total = 0;

        try {
            foreach ($this->migratableTables() as $table) {
                $file = "{$dir}/{$table}.jsonl";
                if (! is_file($file)) {
                    $this->warn("  · skipped {$table} (no export file)");
                    continue;
                }

                // The org row is upserted (never deleted) because users/entities FK to it.
                if ($table === 'organizations') {
                    $count = $this->upsertOrganizations($file);
                    $this->line(sprintf('  %-26s %s rows (upsert)', $table, number_format($count)));
                    $total += $count;
                    continue;
                }

                DB::table($table)->delete(); // clear target (FK checks are off)

                $handle = fopen($file, 'r');
                $buffer = [];
                $count = 0;
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $row = json_decode($line, true);
                    foreach ($userCols as $col) {
                        if (array_key_exists($col, $row) && $row[$col] !== null) {
                            $row[$col] = $ownerId;
                        }
                    }
                    $buffer[] = $row;
                    $count++;
                    if (count($buffer) >= 500) {
                        DB::table($table)->insert($buffer);
                        $buffer = [];
                    }
                }
                if ($buffer) {
                    DB::table($table)->insert($buffer);
                }
                fclose($handle);

                $this->line(sprintf('  %-26s %s rows', $table, number_format($count)));
                $total += $count;
            }
        } finally {
            $mysql ? DB::statement('SET FOREIGN_KEY_CHECKS=1') : DB::statement('PRAGMA foreign_keys=ON');
        }

        $this->info('Done. Imported ' . number_format($total) . ' rows.');

        return self::SUCCESS;
    }

    /** Update org rows in place (or insert if missing) so dependent users/entities never lose their FK. */
    private function upsertOrganizations(string $file): int
    {
        $handle = fopen($file, 'r');
        $count = 0;
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            DB::table('organizations')->updateOrInsert(['id' => $row['id']], Arr::except($row, ['id']));
            $count++;
        }
        fclose($handle);

        return $count;
    }
}
