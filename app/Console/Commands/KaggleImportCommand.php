<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KaggleImportCommand extends Command
{
    protected $signature = 'kaggle:import
        {--force : Re-import even if already imported}';

    protected $description = 'Download and import the Kaggle International Football Results dataset';

    private const GITHUB_RAW = 'https://raw.githubusercontent.com/martj42/international_results/master';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $files = [
            'results'      => ['table' => 'kaggle_results',      'file' => 'results.csv'],
            'goalscorers'  => ['table' => 'kaggle_goalscorers',  'file' => 'goalscorers.csv'],
            'shootouts'    => ['table' => 'kaggle_shootouts',    'file' => 'shootouts.csv'],
            'former_names' => ['table' => 'kaggle_former_names', 'file' => 'former_names.csv'],
        ];

        foreach ($files as $key => $info) {
            $table = $info['table'];
            $existing = DB::table($table)->count();

            if ($existing > 0 && !$force) {
                $this->info("{$table} already has {$existing} rows. Use --force to re-import.");
                continue;
            }

            if ($existing > 0 && $force) {
                $this->line("Truncating {$table}...");
                DB::table($table)->truncate();
            }

            $url = self::GITHUB_RAW . '/' . $info['file'];
            $this->info("Opening {$url}...");

            $fh = fopen($url, 'r');
            if (!$fh) {
                $this->error("Failed to open {$url}");
                continue;
            }

            $header = fgetcsv($fh);
            if (!$header) {
                fclose($fh);
                $this->error("Empty CSV: {$url}");
                continue;
            }

            $inserted = 0;
            $batch = [];

            while (($values = fgetcsv($fh)) !== false) {
                if (count($values) !== count($header)) continue;

                $row = array_combine($header, $values);
                $row = $this->normalizeRow($key, $row);
                if (!$row) continue;

                $row['created_at'] = now();
                $row['updated_at'] = now();
                $batch[] = $row;
                $inserted++;

                if (count($batch) >= 100) {
                    DB::table($table)->insert($batch);
                    $batch = [];
                    if ($inserted % 1000 === 0) {
                        $this->output->write(".");
                    }
                }
            }

            fclose($fh);

            if (!empty($batch)) {
                DB::table($table)->insert($batch);
            }

            $this->newLine();
            $this->info("Inserted {$inserted} rows into {$table}");
        }

        $this->info('Kaggle dataset imported successfully.');
        return Command::SUCCESS;
    }

    private function normalizeRow(string $key, array $row): ?array
    {
        if ($key === 'results') {
            $row['home_score'] = (int) $row['home_score'];
            $row['away_score'] = (int) $row['away_score'];
            $row['neutral'] = strtoupper($row['neutral'] ?? 'FALSE') === 'TRUE';
            $row['match_date'] = $row['date'];
            unset($row['date']);
            return $row;
        }

        if ($key === 'goalscorers') {
            $row['own_goal'] = strtoupper($row['own_goal'] ?? 'FALSE') === 'TRUE';
            $row['penalty'] = strtoupper($row['penalty'] ?? 'FALSE') === 'TRUE';
            $row['match_date'] = $row['date'];
            unset($row['date']);
            return $row;
        }

        if ($key === 'shootouts') {
            $row['match_date'] = $row['date'];
            unset($row['date']);
            return $row;
        }

        if ($key === 'former_names') {
            return $row;
        }

        return null;
    }
}
