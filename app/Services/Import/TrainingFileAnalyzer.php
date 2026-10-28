<?php

namespace App\Services\Import;

/**
 * Membaca seluruh file lalu merangkum hasilnya (tanpa menyimpan ke database).
 * Dipakai untuk uji sekarang, dan nanti untuk halaman Preview Import.
 */
class TrainingFileAnalyzer
{
    public function analyze(string $path, int $sampleSize = 3): array
    {
        $reader = new TrainingSheetReader($path);

        $det = $reader->detect();
        if ($det === null) {
            return [
                'ok'    => false,
                'error' => 'Tidak ditemukan sheet data training (header Course Title, Training Type, dst tidak dikenali).',
            ];
        }

        // Pass 1: variasi penulisan kategori -> peta penyeragaman
        $variants = $reader->collectVariants($det);
        $maps = [];
        $mergedVariants = [];
        foreach ($variants as $field => $groups) {
            $maps[$field] = Canonicalizer::buildMap($groups);
            foreach ($groups as $key => $counts) {
                if (count($counts) > 1) {
                    $mergedVariants[$field][$maps[$field][$key]] = $counts;
                }
            }
        }

        // Pass 2: baca + bersihkan semua baris
        $r = [
            'ok' => true,
            'sheet' => $det['sheet'],
            'header_row' => $det['header_row'],
            'matched' => $det['headers'],
            'unknown_headers' => array_values($det['unknown']),
            'missing_required' => $det['missing_required'],
            'missing_recommended' => $det['missing_recommended'],
            'skipped_sheets' => $det['skipped_sheets'],
            'merged_variants' => $mergedVariants,
            'total_rows' => 0, 'ok_rows' => 0, 'failed_rows' => 0,
            'error_samples' => [],
            'status' => [], 'level' => [], 'division' => [], 'training_type' => [],
            'years' => [], 'years_null' => 0, 'renewal' => [], 'renewal_null' => 0,
            'sums' => ['hours' => 0.0, 'days' => 0.0, 'fee' => 0.0, 'cost' => 0.0],
            'fee_rows' => 0,
            'duplicates' => 0,
            'issues' => [], 'issue_samples' => [],
            'samples' => [],
        ];

        $hashes = [];

        foreach ($reader->rows($det, $maps) as $rowNumber => $rec) {
            $r['total_rows']++;

            if ($rec['errors']) {
                $r['failed_rows']++;
                if (count($r['error_samples']) < 20) {
                    $r['error_samples'][] = ['row' => $rowNumber, 'errors' => $rec['errors']];
                }
                continue;
            }

            $r['ok_rows']++;
            $d = $rec['data'];

            $this->bump($r['status'], $d['status'] ?? null);
            $this->bump($r['level'], $d['employee_level'] ?? null);
            $this->bump($r['division'], $d['division'] ?? null);
            $this->bump($r['training_type'], $d['training_type'] ?? null);

            if (($d['training_year'] ?? null) === null) {
                $r['years_null']++;
            } else {
                $r['years'][$d['training_year']] = ($r['years'][$d['training_year']] ?? 0) + 1;
            }
            if (($d['renewal_year'] ?? null) === null) {
                $r['renewal_null']++;
            } else {
                $r['renewal'][$d['renewal_year']] = ($r['renewal'][$d['renewal_year']] ?? 0) + 1;
            }

            $r['sums']['hours'] += (float) ($d['training_hours'] ?? 0);
            $r['sums']['days'] += (float) ($d['training_days'] ?? 0);
            $r['sums']['fee'] += (float) ($d['course_fee'] ?? 0);
            $r['sums']['cost'] += (float) ($d['total_cost'] ?? 0);
            if (($d['total_cost'] ?? null) !== null) {
                $r['fee_rows']++;
            }

            $h = $d['row_hash'];
            if (isset($hashes[$h])) {
                $r['duplicates']++;
            }
            $hashes[$h] = true;

            foreach ($rec['issues'] as $issue) {
                $k = $issue['field'] . ' | ' . $issue['type'];
                $r['issues'][$k] = ($r['issues'][$k] ?? 0) + 1;
                if (count($r['issue_samples'][$k] ?? []) < 3) {
                    $r['issue_samples'][$k][] = $issue['value'];
                }
            }

            if (count($r['samples']) < $sampleSize) {
                $r['samples'][] = $d;
            }
        }

        arsort($r['status']);
        arsort($r['level']);
        arsort($r['division']);
        arsort($r['training_type']);
        ksort($r['years']);
        ksort($r['renewal']);
        arsort($r['issues']);

        return $r;
    }

    private function bump(array &$bucket, ?string $value): void
    {
        $key = $value ?? '(kosong)';
        $bucket[$key] = ($bucket[$key] ?? 0) + 1;
    }
}