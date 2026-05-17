<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportChangePoints extends Command
{
    protected $signature = 'landchg:import
        {--year= : Specific Taiwan year to import (93-115)}
        {--fresh : Truncate table before import}';

    protected $description = 'Import land change points from CSV files into PostGIS';

    public function handle(): int
    {
        $dataPath = '/data/docs/csv/points';

        if (!is_dir($dataPath)) {
            $this->error("Data directory not found: {$dataPath}");
            return 1;
        }

        if ($this->option('fresh')) {
            DB::table('change_points')->truncate();
            $this->info('Table truncated.');
        }

        DB::disableQueryLog();

        $years = $this->option('year')
            ? [$this->option('year')]
            : $this->getYearDirectories($dataPath);

        $totalImported = 0;

        foreach ($years as $year) {
            $yearPath = "{$dataPath}/{$year}";
            if (!is_dir($yearPath)) {
                $this->warn("Year directory not found: {$yearPath}");
                continue;
            }

            $csvFiles = glob("{$yearPath}/*.csv");
            $this->info("Year {$year}: processing " . count($csvFiles) . " files");

            $yearCount = 0;

            DB::beginTransaction();
            try {
                $allRows = [];
                foreach ($csvFiles as $csvFile) {
                    $countyCity = pathinfo($csvFile, PATHINFO_FILENAME);
                    $rows = $this->parseCsv($csvFile, (int) $year, $countyCity);
                    foreach ($rows as $row) {
                        $allRows[$row['point_id']] = $row;
                    }
                }

                $allRows = array_values($allRows);
                $yearCount = count($allRows);

                foreach (array_chunk($allRows, 500) as $chunk) {
                    DB::table('change_points')->upsert(
                        $chunk,
                        ['point_id', 'year'],
                        ['authority', 'county_city', 'verification_result', 'change_type', 'latitude', 'longitude', 'updated_at']
                    );
                }

                DB::statement("UPDATE change_points SET geom = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326) WHERE year = ? AND geom IS NULL", [$year]);

                DB::commit();
                $this->info("  Imported {$yearCount} records");
                $totalImported += $yearCount;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  Failed for year {$year}: {$e->getMessage()}");
            }
        }

        $this->info("Total imported: {$totalImported} records");
        return 0;
    }

    private function getYearDirectories(string $path): array
    {
        $dirs = array_filter(scandir($path), function ($d) use ($path) {
            return is_dir("{$path}/{$d}") && is_numeric($d);
        });
        sort($dirs, SORT_NUMERIC);
        return array_values($dirs);
    }

    private function parseCsv(string $file, int $year, string $countyCity): array
    {
        $rows = [];
        $handle = fopen($file, 'r');
        if (!$handle) {
            return $rows;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $rows;
        }

        $now = now()->toDateTimeString();

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 6) {
                continue;
            }

            $lat = (float) $data[4];
            $lng = (float) $data[5];

            if ($lat == 0 || $lng == 0) {
                continue;
            }

            $rows[] = [
                'point_id' => $data[0],
                'authority' => $data[1],
                'county_city' => $countyCity,
                'verification_result' => $data[2] ?: null,
                'change_type' => $data[3] ?: null,
                'year' => $year,
                'latitude' => $lat,
                'longitude' => $lng,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);
        return $rows;
    }
}
