<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\TrainingRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan isi file Excel ke tabel training_records untuk satu batch.
 * Data baru tidak terlihat dashboard sampai batch diaktifkan (di langkah terakhir).
 */
class TrainingImporter
{
    private const CHUNK = 500;
    private const PROGRESS_EVERY = 250;
    private const MAX_ERRORS_LOGGED = 200;

    /** Batas panjang kolom string (sesuai migration). Kolom string lain: 255. */
    private const LIMITS = [
        'status' => 30, 'npk' => 30, 'employee_level' => 30, 'division' => 50,
        'unit' => 100, 'certificate_availability' => 30, 'training_type' => 100,
        'training_type_raw' => 100, 'certificate_note' => 50, 'training_month' => 30,
        'grade' => 20, 'eselon' => 20, 'year_of_service' => 30, 'age' => 30,
        'yos_balance' => 30, 'education' => 100,
        'course_title' => null, 'extra_data' => null, // tipe text/json: tanpa batas
    ];

    public function run(ImportBatch $batch): void
    {
        @set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $batch->update([
            'status'          => 'processing',
            'started_at'      => now(),
            'successful_rows' => 0,
            'failed_rows'     => 0,
        ]);

        $path = $batch->filePath();
        if (!is_file($path)) {
            throw new \RuntimeException('File Excel tidak ditemukan di penyimpanan.');
        }

        $alreadyImported = ImportBatch::where('file_hash', $batch->file_hash)
            ->where('id', '!=', $batch->id)
            ->whereIn('status', ['success', 'partial'])
            ->exists();
        if ($alreadyImported) {
            throw new \RuntimeException('File identik sudah pernah diimpor.');
        }

        $reader = new TrainingSheetReader($path);
        $det = $reader->detect();
        if ($det === null) {
            throw new \RuntimeException('Sheet data training tidak ditemukan.');
        }
        if ($det['missing_required']) {
            throw new \RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', $det['missing_required']));
        }

        // Pass 1: variasi huruf kategori -> peta penyeragaman
        $maps = [];
        foreach ($reader->collectVariants($det) as $field => $groups) {
            $maps[$field] = Canonicalizer::buildMap($groups);
        }

        // Pass 2: baca, bersihkan, simpan per chunk
        $columns = array_values(array_diff(Schema::getColumnListing('training_records'), ['id']));
        $now = now()->toDateTimeString();

        $buffer = [];
        $ok = 0;
        $failed = 0;
        $processed = 0;
        $failedRows = [];

        foreach ($reader->rows($det, $maps) as $rowNumber => $rec) {
            $processed++;

            if ($rec['errors']) {
                $failed++;
                if (count($failedRows) < self::MAX_ERRORS_LOGGED) {
                    $failedRows[] = [
                        'row'   => $rowNumber,
                        'field' => $rec['errors'][0]['field'],
                        'type'  => $rec['errors'][0]['type'],
                    ];
                }
            } else {
                $buffer[] = $this->toDbRow($rec['data'], $columns, $batch->id, $now);
                $ok++;
            }

            if (count($buffer) >= self::CHUNK) {
                DB::table('training_records')->insert($buffer);
                $buffer = [];
            }

            if ($processed % self::PROGRESS_EVERY === 0) {
                $batch->update(['successful_rows' => $ok, 'failed_rows' => $failed]);
            }
        }

        if ($buffer !== []) {
            DB::table('training_records')->insert($buffer);
        }

        // Langkah akhir dalam satu transaksi: status + aktivasi snapshot
        DB::transaction(function () use ($batch, $ok, $failed, $failedRows) {
            $status = $ok === 0 ? 'failed' : ($failed > 0 ? 'partial' : 'success');

            $batch->update([
                'total_rows'      => $ok + $failed,
                'successful_rows' => $ok,
                'failed_rows'     => $failed,
                'status'          => $status,
                'completed_at'    => now(),
                'error_log'       => json_encode([
                    'message'     => $ok === 0 ? 'Tidak ada baris valid yang dapat diimpor.' : null,
                    'failed_rows' => $failedRows,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            if ($ok > 0) {
                $batch->activate();
            }
        });
    }

    /** Dipanggil saat job gagal: hapus baris setengah jadi, catat alasan. Data aktif lama tidak disentuh. */
    public function markFailed(ImportBatch $batch, string $message): void
    {
        TrainingRecord::where('import_batch_id', $batch->id)->delete();

        $batch->update([
            'status'       => 'failed',
            'is_active'    => false,
            'completed_at' => now(),
            'error_log'    => json_encode(['message' => $message, 'failed_rows' => []], JSON_UNESCAPED_UNICODE),
        ]);
        $batch->deleteFile();
    }

    private function toDbRow(array $data, array $columns, int $batchId, string $now): array
    {
        $row = [];
        foreach ($columns as $col) {
            $value = $data[$col] ?? null;

            if ($col === 'extra_data' && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            if (is_string($value)) {
                $limit = array_key_exists($col, self::LIMITS) ? self::LIMITS[$col] : 255;
                if ($limit !== null && mb_strlen($value) > $limit) {
                    $value = mb_substr($value, 0, $limit);
                }
            }

            $row[$col] = $value;
        }

        $row['import_batch_id'] = $batchId;
        $row['created_at'] = $now;
        $row['updated_at'] = $now;

        return $row;
    }
}