<?php

namespace App\Services\Finance;

use App\Models\FinanceImportBatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Fetches Colorado TRACER's official bulk campaign-finance files. These are the sanctioned,
 * weekly-refreshed CSV-in-ZIP downloads at predictable URLs — no scraping, no manual upload.
 *
 *   https://tracer.sos.colorado.gov/PublicSite/Docs/BulkDataDownloads/{YEAR}_{Dataset}.csv.zip
 */
class TracerClient
{
    private const BASE = 'https://tracer.sos.colorado.gov/PublicSite/Docs/BulkDataDownloads';

    private const DATASETS = [
        'contributions' => 'ContributionData',
        'expenditures' => 'ExpenditureData',
        'loans' => 'LoanData',
    ];

    public function url(string $dataType, int $year): string
    {
        $file = self::DATASETS[$dataType] ?? throw new RuntimeException("Unknown TRACER dataset: {$dataType}");

        return config('services.tracer.base', self::BASE) . "/{$year}_{$file}.csv.zip";
    }

    /**
     * Download the ZIP, preserve the untouched original on the private disk, stamp the batch with
     * the source hash + last-modified, and return the local path to the extracted CSV.
     */
    public function fetchCsv(FinanceImportBatch $batch): string
    {
        $url = $batch->source_url ?: $this->url($batch->data_type, $batch->year);

        $zipTmp = storage_path('app/tmp-tracer-' . Str::random(10) . '.zip');
        @mkdir(dirname($zipTmp), 0775, true);

        $response = Http::timeout(600)->withOptions(['sink' => $zipTmp])->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("TRACER download failed ({$response->status()}) for {$url}");
        }

        $hash = hash_file('sha256', $zipTmp);
        $stored = sprintf('finance/tracer/%d_%s_%s.zip', $batch->year, $batch->data_type, now()->format('Ymd_His'));
        Storage::disk('local')->putFileAs('finance/tracer', new \Illuminate\Http\File($zipTmp), basename($stored));

        $batch->update([
            'source_url' => $url,
            'raw_file_path' => $stored,
            'file_hash' => $hash,
            'source_last_modified' => $this->parseLastModified($response->header('Last-Modified')),
        ]);

        return $this->extractCsv($zipTmp);
    }

    /** Extract the first CSV entry from the downloaded ZIP to a temp path. */
    public function extractCsv(string $zipPath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open TRACER ZIP: {$zipPath}");
        }

        $entry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (Str::endsWith(strtolower($name), '.csv')) {
                $entry = $name;
                break;
            }
        }

        if ($entry === null) {
            $zip->close();
            throw new RuntimeException('No CSV found inside the TRACER ZIP.');
        }

        $dir = storage_path('app/tmp-tracer-extract-' . Str::random(10));
        @mkdir($dir, 0775, true);
        $zip->extractTo($dir, $entry);
        $zip->close();

        return $dir . '/' . $entry;
    }

    private function parseLastModified(?string $header): ?string
    {
        if (! $header) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($header)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
