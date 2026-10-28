<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ImportBatch;
use App\Models\TrainingRecord;
use Illuminate\Support\Facades\Cache;

/**
 * Semua agregasi dashboard dilakukan di database (COUNT/SUM + GROUP BY).
 * PHP hanya mengurutkan hasil group dan menghitung persentase.
 */
class DashboardService
{
    /** Dimensi => kolom DB. Nama kolom TIDAK pernah berasal dari input pengguna. */
    private const DIMENSIONS = [
        'training_type' => ['column' => 'training_type', 'kind' => 'category', 'null_label' => '(Tanpa Type)'],
        'division'      => ['column' => 'division',      'kind' => 'category', 'null_label' => '(Kosong)'],
        'level'         => ['column' => 'employee_level', 'kind' => 'category', 'null_label' => 'Unknown'],
        'training_year' => ['column' => 'training_year', 'kind' => 'year'],
        'renewal_year'  => ['column' => 'renewal_year',  'kind' => 'year'],
    ];

    private const METRICS = [
        'frq'   => 'Frekuensi (jumlah training)',
        'hours' => 'Total jam',
        'days'  => 'Total hari',
        'fund'  => 'Training fund (Rp)',
    ];

    private const FUND_COLUMNS = ['total_cost', 'course_fee'];

    public function activeBatch(): ?ImportBatch
    {
        return ImportBatch::active();
    }

    public function hasChart(int $id): bool
    {
        return isset(config('dashboard.charts')[$id]);
    }

    /** Ubah input filter ("All"/kosong => null) */
    public function normalizeFilters(array $in): array
    {
        $clean = fn ($v) => ($v === null || $v === '' || strcasecmp((string) $v, 'All') === 0)
            ? null
            : (string) $v;

        $year = $clean($in['year'] ?? null);

        return [
            'year'          => $year !== null ? (int) $year : null,
            'division'      => $clean($in['division'] ?? null),
            'training_type' => $clean($in['training_type'] ?? null),
        ];
    }

    /** Isi dropdown filter + daftar chart + info batch */
    public function meta(): array
    {
        $batch = $this->activeBatch();
        if (!$batch) {
            return ['has_data' => false];
        }

        $period = $this->periods($batch->id);
        $key = "dash:{$batch->id}:meta:" . md5(json_encode($period));

        return Cache::remember($key, $this->ttl(), function () use ($batch, $period) {
            $base = fn () => TrainingRecord::query()->where('import_batch_id', $batch->id);

            $charts = [];
            foreach (config('dashboard.charts') as $id => $def) {
                $charts[] = [
                    'id'     => $id,
                    'title'  => $this->title($def, $period),
                    'visual' => $def['visual'],
                ];
            }
            usort($charts, fn ($a, $b) => $a['id'] <=> $b['id']);

            return [
                'has_data'       => true,
                'batch'          => [
                    'id'           => $batch->id,
                    'file_name'    => $batch->file_name,
                    'completed_at' => $batch->completed_at?->format('d M Y H:i'),
                    'rows'         => $batch->successful_rows,
                ],
                'years'          => $base()->whereNotNull('training_year')->distinct()
                                        ->orderBy('training_year')->pluck('training_year')->all(),
                'divisions'      => $base()->whereNotNull('division')->distinct()
                                        ->orderBy('division')->pluck('division')->all(),
                'training_types' => $base()->whereNotNull('training_type')->distinct()
                                        ->orderBy('training_type')->pluck('training_type')->all(),
                'charts'         => $charts,
            ];
        });
    }

    /** Data satu chart (null jika id tidak ada atau belum ada batch aktif) */
    public function chart(int $id, array $filters): ?array
    {
        $def = config('dashboard.charts')[$id] ?? null;
        $batch = $this->activeBatch();
        if ($def === null || $batch === null) {
            return null;
        }

        $filters = $this->normalizeFilters($filters);
        $period = $this->periods($batch->id);
        $key = "dash:{$batch->id}:c{$id}:" . md5(json_encode([$filters, $period]));

        return Cache::remember(
            $key,
            $this->ttl(),
            fn () => $this->build($id, $def, $batch->id, $filters, $period)
        );
    }

    /* ------------------------------------------------------------------ */

    private function ttl(): \DateTimeInterface
    {
        return now()->addMinutes((int) config('dashboard.cache_minutes', 10));
    }

    /** Rentang tahun yang dipakai judul dan filter periode chart */
    public function periods(int $batchId): array
    {
        $range = Cache::remember("dash:{$batchId}:yearrange", $this->ttl(), function () use ($batchId) {
            $row = TrainingRecord::query()
                ->where('import_batch_id', $batchId)
                ->selectRaw('MIN(training_year) AS mn, MAX(training_year) AS mx')
                ->first();

            return [
                'mn' => $row?->mn !== null ? (int) $row->mn : null,
                'mx' => $row?->mx !== null ? (int) $row->mx : null,
            ];
        });

        $start = (int) AppSetting::get('chart_start_year', config('dashboard.chart_start_year'));
        $rs = (int) AppSetting::get('renewal_start_year', now()->year);
        $span = max(1, (int) config('dashboard.renewal_span', 5));

        return [
            'from'        => $range['mn'],
            'to'          => $range['mx'],
            'recent_from' => $range['mn'] !== null ? max($start, $range['mn']) : null,
            'rfrom'       => $rs,
            'rto'         => $rs + $span - 1,
            'fund_column' => $this->fundColumn(),
        ];
    }

    private function title(array $def, array $p): string
    {
        $map = [
            '{from}'        => (string) ($p['from'] ?? '-'),
            '{to}'          => (string) ($p['to'] ?? '-'),
            '{recent_from}' => (string) ($p['recent_from'] ?? '-'),
            '{rfrom}'       => (string) ($p['rfrom'] ?? '-'),
            '{rto}'         => (string) ($p['rto'] ?? '-'),
        ];

        return strtr($def['title'], $map);
    }

    private function range(string $period, array $p): ?array
    {
        return match ($period) {
            'training_all'    => ['column' => 'training_year', 'from' => $p['from'], 'to' => $p['to']],
            'training_recent' => ['column' => 'training_year', 'from' => $p['recent_from'], 'to' => $p['to']],
            'renewal'         => ['column' => 'renewal_year', 'from' => $p['rfrom'], 'to' => $p['rto']],
            default           => null,
        };
    }

    private function fundColumn(): string
    {
        $c = (string) AppSetting::get('fund_column', 'total_cost');

        return in_array($c, self::FUND_COLUMNS, true) ? $c : 'total_cost';
    }

    private function metricSql(string $metric): string
    {
        return match ($metric) {
            'frq'   => 'COUNT(course_title)',
            'hours' => 'SUM(training_hours)',
            'days'  => 'SUM(training_days)',
            'fund'  => 'SUM(' . $this->fundColumn() . ')',
        };
    }

    private function labelFor(string $dimKey, array $dim, mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return $dim['null_label'] ?? '(Kosong)';
        }
        if ($dimKey === 'level') {
            $map = config('dashboard.level_labels', []);

            return $map[mb_strtoupper((string) $raw)] ?? (string) $raw;
        }

        return (string) $raw;
    }

    private function build(int $id, array $def, int $batchId, array $filters, array $period): array
    {
        $dim = self::DIMENSIONS[$def['dimension']];
        $col = $dim['column'];
        $range = $this->range($def['period'], $period);

        $q = TrainingRecord::query()
            ->where('import_batch_id', $batchId)
            ->filter($filters);

        if ($range !== null) {
            if ($range['from'] === null || $range['to'] === null) {
                $q->whereRaw('1 = 0');
            } else {
                $q->whereBetween($range['column'], [$range['from'], $range['to']]);
            }
        }
        if ($dim['kind'] === 'year') {
            $q->whereNotNull($col);
        }

        $rows = $q->selectRaw("{$col} AS dim_label, {$this->metricSql($def['metric'])} AS metric_value")
            ->groupBy($col)
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $label = $this->labelFor($def['dimension'], $dim, $r->dim_label);
            $map[$label] = ($map[$label] ?? 0) + (float) $r->metric_value;
        }

        if ($dim['kind'] === 'year') {
            // Tahun diurutkan natural dan tahun tanpa data diisi 0
            if ($range !== null && $range['column'] === $col && $range['from'] !== null) {
                $years = range($range['from'], $range['to']);
            } else {
                $keys = array_map('intval', array_keys($map));
                $years = $keys === [] ? [] : range(min($keys), max($keys));
            }
            $labels = [];
            $values = [];
            foreach ($years as $y) {
                $labels[] = (string) $y;
                $values[] = (float) ($map[$y] ?? 0);
            }
        } else {
            // Kategori diurutkan nilai terbesar dulu (seperti pivot Excel)
            $map = array_filter($map, fn ($v) => $v > 0);
            $pairs = [];
            foreach ($map as $k => $v) {
                $pairs[] = [(string) $k, $v];
            }
            usort($pairs, fn ($a, $b) => [$b[1], $a[0]] <=> [$a[1], $b[0]]);
            $labels = array_column($pairs, 0);
            $values = array_column($pairs, 1);
        }

        $isInt = $def['metric'] === 'frq';
        $values = array_map(fn ($v) => $isInt ? (int) round($v) : round($v, 2), $values);
        $total = round(array_sum($values), 2);

        $percents = [];
        $cumulative = [];
        $run = 0;
        foreach ($values as $v) {
            $run += $v;
            $percents[] = $total > 0 ? round($v / $total * 100, 1) : 0;
            $cumulative[] = $total > 0 ? round($run / $total * 100, 1) : 0;
        }

        return [
            'id'           => $id,
            'title'        => $this->title($def, $period),
            'visual'       => $def['visual'],
            'metric'       => $def['metric'],
            'metric_label' => self::METRICS[$def['metric']],
            'labels'       => $labels,
            'values'       => $values,
            'percents'     => $percents,
            'cumulative'   => $cumulative,
            'total'        => $total,
            'empty'        => $total <= 0,
        ];
    }
}