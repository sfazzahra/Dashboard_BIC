<?php

namespace App\Services\Import;

use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Membaca file Excel training:
 *  1. detect()           -> cari sheet + baris header + peta kolom secara otomatis
 *  2. collectVariants()  -> kumpulkan variasi penulisan Division/Training Type
 *  3. rows()             -> baca baris demi baris (streaming), sudah dibersihkan
 */
class TrainingSheetReader
{
    private const HEADER_SCAN_ROWS = 15;
    private const MIN_MATCHED_FIELDS = 8;

    public function __construct(private string $path)
    {
    }

    private function open(): Reader
    {
        $reader = new Reader();
        $reader->open($this->path);
        return $reader;
    }

    /**
     * Ambil nilai tiap sel dalam satu baris.
     * Untuk sel berumus dipakai HASIL hitungnya (cached value), bukan teks rumus.
     */
    private function cellValues(Row $row): array
    {
        $values = [];
        foreach ($row->getCells() as $i => $cell) {
            if ($cell instanceof FormulaCell) {
                $values[$i] = $cell->getComputedValue();
            } else {
                $values[$i] = $cell->getValue();
            }
        }
        return $values;
    }

    /**
     * Cari sheet training. Sheet tersembunyi (misal data Manpower) dilewati.
     */
    public function detect(): ?array
    {
        $reader = $this->open();
        $best = null;
        $skipped = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $name = $sheet->getName();

                if (!$sheet->isVisible()) {
                    $skipped[] = ['name' => $name, 'reason' => 'sheet tersembunyi'];
                    continue;
                }

                $found = $this->findHeader($sheet);
                if ($found === null) {
                    $skipped[] = ['name' => $name, 'reason' => 'bukan sheet data training'];
                    continue;
                }

                $found['sheet'] = $name;
                if ($best === null || $found['score'] > $best['score']) {
                    $best = $found;
                }
            }
        } finally {
            $reader->close();
        }

        if ($best === null) {
            return null;
        }

        $matchedFields = array_values($best['columns']);
        $best['missing_required'] = array_values(array_diff(ColumnMap::REQUIRED, $matchedFields));
        $best['missing_recommended'] = array_values(array_diff(ColumnMap::RECOMMENDED, $matchedFields));
        $best['skipped_sheets'] = array_values(array_filter(
            $skipped,
            fn ($s) => $s['name'] !== $best['sheet']
        ));

        return $best;
    }

    private function findHeader($sheet): ?array
    {
        $best = null;

        foreach ($sheet->getRowIterator() as $rowNumber => $row) {
            if ($rowNumber > self::HEADER_SCAN_ROWS) {
                break;
            }

            $columns = [];
            $unknown = [];
            $headers = [];
            $seen = [];

            foreach ($this->cellValues($row) as $i => $cell) {
                $raw = ValueNormalizer::headerText($cell);
                $norm = ColumnMap::normalizeHeader($raw);
                if ($norm === '') {
                    continue;
                }

                $field = ColumnMap::fieldFor($norm);
                if ($field !== null && !isset($seen[$field])) {
                    $columns[$i] = $field;
                    $headers[$i] = $raw;
                    $seen[$field] = true;
                } else {
                    $unknown[$i] = $raw;
                }
            }

            $score = count($columns);
            if ($score >= self::MIN_MATCHED_FIELDS && ($best === null || $score > $best['score'])) {
                $best = [
                    'header_row' => $rowNumber,
                    'columns'    => $columns,   // indeks kolom => field
                    'headers'    => $headers,   // indeks kolom => teks header asli
                    'unknown'    => $unknown,   // kolom tidak dikenal (disimpan di extra_data)
                    'score'      => $score,
                ];
            }
        }

        return $best;
    }

    /**
     * Pass ringan: kumpulkan variasi penulisan untuk Division dan Training Type.
     * @return array<string, array<string, array<string,int>>>
     */
    public function collectVariants(array $det): array
    {
        $variants = ['division' => [], 'training_type' => []];
        $indexes = [];
        foreach ($det['columns'] as $idx => $field) {
            if (isset($variants[$field])) {
                $indexes[$idx] = $field;
            }
        }

        foreach ($this->iterate($det) as $cells) {
            foreach ($indexes as $idx => $field) {
                $value = ValueNormalizer::category($cells[$idx] ?? null);
                if ($value === null) {
                    continue;
                }
                $key = mb_strtolower($value);
                $variants[$field][$key][$value] = ($variants[$field][$key][$value] ?? 0) + 1;
            }
        }

        return $variants;
    }

    /**
     * Baca semua baris data (streaming).
     *
     * @param array<string,array<string,string>> $canonicalMaps ['training_type' => [kunci => kanonik], 'division' => [...]]
     * @return \Generator<int, array{data: array, issues: array, errors: array}> kunci = nomor baris Excel
     */
    public function rows(array $det, array $canonicalMaps = []): \Generator
    {
        foreach ($this->iterate($det, true) as $rowNumber => $cells) {
            $record = $this->mapRow($rowNumber, $cells, $det, $canonicalMaps);
            if ($record !== null) {
                yield $rowNumber => $record;
            }
        }
    }

    /** Iterasi mentah baris data pada sheet terpilih */
    private function iterate(array $det, bool $withKeys = false): \Generator
    {
        $reader = $this->open();
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== $det['sheet']) {
                    continue;
                }
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    if ($rowNumber <= $det['header_row']) {
                        continue;
                    }
                    if ($withKeys) {
                        yield $rowNumber => $this->cellValues($row);
                    } else {
                        yield $this->cellValues($row);
                    }
                }
                break;
            }
        } finally {
            $reader->close();
        }
    }

    private function mapRow(int $rowNumber, array $cells, array $det, array $canonicalMaps): ?array
    {
        $data = [];
        $issues = [];
        $hasValue = false;

        foreach ($det['columns'] as $idx => $field) {
            $value = $cells[$idx] ?? null;
            if (!ValueNormalizer::isBlank($value)) {
                $hasValue = true;
            }
            $this->applyField($field, $value, $data, $issues);
        }

        if (!$hasValue) {
            return null; // baris kosong
        }

        // Penyeragaman huruf besar/kecil kategori
        foreach (['training_type', 'division'] as $field) {
            if (!empty($data[$field])) {
                $key = mb_strtolower($data[$field]);
                if (isset($canonicalMaps[$field][$key])) {
                    $data[$field] = $canonicalMaps[$field][$key];
                }
            }
        }

        // Kolom yang tidak dikenal disimpan, bukan dibuang
        $extra = [];
        foreach ($det['unknown'] as $idx => $header) {
            $t = ValueNormalizer::text($cells[$idx] ?? null);
            if ($t !== null) {
                $extra[$header] = $t;
            }
        }
        $data['extra_data'] = $extra === [] ? null : $extra;
        $data['source_row'] = $rowNumber;
        $data['row_hash'] = $this->hash($data);

        $errors = [];
        foreach (ColumnMap::REQUIRED as $required) {
            if (!isset($data[$required]) || $data[$required] === null) {
                $errors[] = ['field' => $required, 'type' => 'missing_required', 'value' => ''];
            }
        }

        return ['data' => $data, 'issues' => $issues, 'errors' => $errors];
    }

    private function applyField(string $field, mixed $v, array &$data, array &$issues): void
    {
        switch (ColumnMap::KINDS[$field]) {
            case 'int':
                $data[$field] = ValueNormalizer::integer($v);
                break;
            case 'status':
                $data[$field] = ValueNormalizer::status($v);
                break;
            case 'level':
                $data[$field] = ValueNormalizer::level($v);
                break;
            case 'category':
                $data[$field] = ValueNormalizer::category($v);
                if ($field === 'training_type') {
                    $data['training_type_raw'] = $data[$field]; // tulisan asli
                }
                break;
            case 'lower':
                $data[$field] = ValueNormalizer::lower($v);
                break;
            case 'text0':
                $data[$field] = ValueNormalizer::textZero($v);
                break;
            case 'month':
                $data[$field] = ValueNormalizer::month($v);
                break;
            case 'year':
                [$val, $bad] = ValueNormalizer::year($v);
                $data[$field] = $val;
                if ($bad) {
                    $issues[] = ['field' => $field, 'type' => 'invalid', 'value' => (string) ValueNormalizer::text($v)];
                }
                break;
            case 'decimal':
                [$val, $bad] = ValueNormalizer::decimal($v);
                $data[$field] = $val;
                if ($bad) {
                    $issues[] = ['field' => $field, 'type' => 'invalid', 'value' => (string) ValueNormalizer::text($v)];
                }
                break;
            case 'date':
            case 'date_only':
                $r = ValueNormalizer::date($v);
                $data[$field] = $r['date'];
                if (ColumnMap::KINDS[$field] === 'date') {
                    $data[$field . '_raw'] = $r['raw'];
                }
                if (in_array($r['status'], ['unparsed', 'invalid'], true)) {
                    $issues[] = ['field' => $field, 'type' => $r['status'], 'value' => (string) $r['raw']];
                }
                break;
            default: // text
                $data[$field] = ValueNormalizer::text($v);
        }
    }

    /** Sidik jari baris (nilai yang sudah dibersihkan) untuk deteksi baris identik */
    private function hash(array $data): string
    {
        $parts = [];
        foreach (array_keys(ColumnMap::KINDS) as $field) {
            $parts[] = (string) ($data[$field] ?? '');
            if (ColumnMap::KINDS[$field] === 'date') {
                $parts[] = (string) ($data[$field . '_raw'] ?? '');
            }
        }
        $parts[] = json_encode($data['extra_data'] ?? null);

        return hash('sha256', implode("\x1F", $parts));
    }
}