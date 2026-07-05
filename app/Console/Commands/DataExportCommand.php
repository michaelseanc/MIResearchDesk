<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MigratesData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Export domain data to newline-delimited JSON (one file per table) for migrating to another
 * environment. Run on the SOURCE (e.g. local dev). Streams row-by-row, so it's memory-safe on large
 * tables like finance_transactions. See the MigratesData trait for exactly which tables are copied.
 */
class DataExportCommand extends Command
{
    use MigratesData;

    protected $signature = 'data:export {--path=migration : subfolder under storage/app to write into}';

    protected $description = 'Export domain data (entities, finance, stories… — not users/roles) to JSONL for migration.';

    public function handle(): int
    {
        $dir = storage_path('app/' . $this->option('path'));
        File::ensureDirectoryExists($dir);

        $this->info("Exporting domain data → {$dir}");
        $total = 0;

        foreach ($this->migratableTables() as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn("  · skipped {$table} (table not found)");
                continue;
            }

            $handle = fopen("{$dir}/{$table}.jsonl", 'w');
            $count = 0;
            foreach (DB::table($table)->cursor() as $row) {
                // Substitute (don't fail on) malformed UTF-8 from imported source data, so no row is
                // silently dropped as a blank line on the other side.
                fwrite($handle, json_encode($row, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE) . "\n");
                $count++;
            }
            fclose($handle);

            $this->line(sprintf('  %-26s %s rows', $table, number_format($count)));
            $total += $count;
        }

        $this->info('Done. ' . number_format($total) . " rows across " . count($this->migratableTables()) . " tables.");
        $this->line("Next: copy the '{$this->option('path')}' folder to the target server's storage/app/, then run data:import there.");

        return self::SUCCESS;
    }
}
