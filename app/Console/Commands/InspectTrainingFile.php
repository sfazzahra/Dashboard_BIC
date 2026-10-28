<?php

namespace App\Console\Commands;

use App\Services\Import\TrainingFileAnalyzer;
use Illuminate\Console\Command;

class InspectTrainingFile extends Command
{
    protected $signature = 'training:inspect {path : Lokasi file Excel (.xlsx)}';

    protected $description = 'Analisis file Excel training (tanpa menyimpan ke database)';

    public function handle(TrainingFileAnalyzer $analyzer): int
    {
        @set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $path = $this->argument('path');
        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        $this->info('Membaca file... (bisa memakan waktu beberapa puluh detik)');
        $start = microtime(true);
        $r = $analyzer->analyze($path);

        if (!$r['ok']) {
            $this->error($r['error']);
            return self::FAILURE;
        }

        $this->newLine();
        $this->line("Sheet terdeteksi : <info>{$r['sheet']}</info> (header di baris {$r['header_row']})");
        $this->line('Kolom dikenali   : ' . count($r['matched']));
        $this->line('Kolom tak dikenal: ' . ($r['unknown_headers'] ? implode(', ', $r['unknown_headers']) : '-'));
        $this->line('Wajib hilang     : ' . ($r['missing_required'] ? implode(', ', $r['missing_required']) : '-'));
        $this->line('Disarankan hilang: ' . ($r['missing_recommended'] ? implode(', ', $r['missing_recommended']) : '-'));
        foreach ($r['skipped_sheets'] as $s) {
            $this->line("  dilewati: {$s['name']} ({$s['reason']})");
        }

        $this->newLine();
        $this->line("Total baris data : <info>{$r['total_rows']}</info>");
        $this->line("Baris valid      : <info>{$r['ok_rows']}</info>");
        $this->line("Baris gagal      : <info>{$r['failed_rows']}</info>");
        $this->line("Baris identik    : {$r['duplicates']} (tetap diimpor)");

        $this->counts('STATUS', $r['status']);
        $this->counts('LEVEL (Group)', $r['level']);
        $this->counts('DIVISION', $r['division']);
        $this->counts('TRAINING TYPE (setelah penyeragaman huruf)', $r['training_type']);

        if ($r['merged_variants']) {
            $this->newLine();
            $this->line('<comment>Variasi huruf yang disatukan:</comment>');
            foreach ($r['merged_variants'] as $field => $groups) {
                foreach ($groups as $canonical => $counts) {
                    $parts = [];
                    foreach ($counts as $variant => $n) {
                        $parts[] = "{$variant} ({$n})";
                    }
                    $this->line("  {$field}: {$canonical} <= " . implode(', ', $parts));
                }
            }
        }

        $this->newLine();
        if ($r['years']) {
            $this->line('<comment>Training year</comment> ' . min(array_keys($r['years'])) . ' - ' . max(array_keys($r['years'])) . ", kosong: {$r['years_null']}");
            $this->line('  ' . $this->pairs($r['years']));
        }
        $this->line('<comment>Renewal year</comment> terisi: ' . array_sum($r['renewal']) . ", kosong: {$r['renewal_null']}");
        $this->line('  ' . $this->pairs($r['renewal']));

        $this->newLine();
        $this->line('<comment>Total</comment> hours=' . $r['sums']['hours'] . '  days=' . $r['sums']['days']
            . '  course_fee=' . $r['sums']['fee'] . '  total_cost=' . $r['sums']['cost']
            . "  (baris ber-cost: {$r['fee_rows']})");

        if ($r['issues']) {
            $this->newLine();
            $this->line('<comment>Catatan pembersihan (nilai tidak bisa diproses penuh):</comment>');
            foreach ($r['issues'] as $k => $n) {
                $this->line("  {$k}: {$n}  contoh: " . implode(' ; ', $r['issue_samples'][$k]));
            }
        }

        if ($r['error_samples']) {
            $this->newLine();
            $this->error('Contoh baris gagal:');
            foreach ($r['error_samples'] as $e) {
                $this->line("  baris {$e['row']}: " . $e['errors'][0]['field'] . ' ' . $e['errors'][0]['type']);
            }
        }

        $this->newLine();
        $this->line('Contoh 1 baris hasil bersih:');
        if ($r['samples']) {
            $this->line(json_encode($r['samples'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->newLine();
        $this->info('Selesai dalam ' . round(microtime(true) - $start, 1) . ' detik.');
        return self::SUCCESS;
    }

    private function counts(string $title, array $counts): void
    {
        $this->newLine();
        $rows = [];
        foreach ($counts as $k => $n) {
            $rows[] = [$k, $n];
        }
        $this->line("<comment>{$title}</comment> (" . count($counts) . ' nilai)');
        $this->table(['Nilai', 'Jumlah'], $rows);
    }

    private function pairs(array $a): string
    {
        $out = [];
        foreach ($a as $k => $n) {
            $out[] = "{$k}:{$n}";
        }
        return implode('  ', $out);
    }
}