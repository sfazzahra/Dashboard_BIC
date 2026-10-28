<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportExcelRequest;
use App\Jobs\ImportTrainingBatch;
use App\Models\ImportBatch;
use App\Services\Import\TrainingFileAnalyzer;

class ImportController extends Controller
{
    /** Kolom yang boleh tampil di sample preview (tanpa NPK/nama karyawan) */
    private const SAMPLE_FIELDS = [
        'source_row', 'status', 'employee_level', 'division', 'training_type',
        'course_title', 'training_year', 'renewal_year', 'training_days', 'training_hours',
    ];

    public function index()
    {
        return view('import.index', [
            'batches' => ImportBatch::latest('id')->limit(10)->get(),
        ]);
    }

    public function store(ImportExcelRequest $request, TrainingFileAnalyzer $analyzer)
    {
        @set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        // File identik yang sudah pernah berhasil diimpor ditolak
        $existing = ImportBatch::where('file_hash', $hash)
            ->whereIn('status', ['success', 'partial'])
            ->latest('id')
            ->first();

        if ($existing) {
            $when = $existing->completed_at?->format('d M Y H:i') ?? '-';
            return back()->withErrors([
                'file' => "File ini identik dengan import #{$existing->id} ({$when}). Import ulang file yang sama ditolak agar data tidak ganda.",
            ]);
        }

        $batch = ImportBatch::create([
            'file_name'   => $file->getClientOriginalName(),
            'file_hash'   => $hash,
            'imported_by' => auth()->id(),
            'status'      => 'analyzing',
            'started_at'  => now(),
        ]);

        $file->storeAs('imports', "{$batch->id}.xlsx", 'local');

        try {
            $result = $analyzer->analyze($batch->filePath(), 15);
        } catch (\Throwable $e) {
            return $this->fail($batch, 'File tidak dapat dibaca: ' . $e->getMessage());
        }

        if (!$result['ok']) {
            return $this->fail($batch, $result['error']);
        }

        // Sample hanya berisi kolom non-pribadi
        $result['samples'] = array_map(
            fn (array $row) => array_intersect_key($row, array_flip(self::SAMPLE_FIELDS)),
            $result['samples']
        );

        $batch->update([
            'sheet_name'       => $result['sheet'],
            'total_rows'       => $result['total_rows'],
            'status'           => 'pending',
            'detected_columns' => $result,   // ringkasan analisis untuk halaman preview
        ]);

        return redirect()->route('import.preview', $batch);
    }

    public function preview(ImportBatch $batch)
    {
        if ($batch->status !== 'pending') {
            return redirect()->route('import.show', $batch);
        }

        return view('import.preview', [
            'batch' => $batch,
            's'     => $batch->detected_columns,
        ]);
    }

    public function confirm(ImportBatch $batch)
    {
        if ($batch->status !== 'pending') {
            return redirect()->route('import.show', $batch);
        }

        $s = $batch->detected_columns ?? [];
        if (!empty($s['missing_required']) || ($s['ok_rows'] ?? 0) === 0) {
            return redirect()->route('import.preview', $batch)
                ->withErrors(['file' => 'Import tidak dapat dilanjutkan: kolom wajib hilang atau tidak ada baris valid.']);
        }

        $busy = ImportBatch::whereIn('status', ['queued', 'processing'])
            ->where('id', '!=', $batch->id)
            ->exists();
        if ($busy) {
            return redirect()->route('import.preview', $batch)
                ->withErrors(['file' => 'Masih ada import lain yang berjalan. Tunggu sampai selesai.']);
        }

        $batch->update(['status' => 'queued', 'successful_rows' => 0, 'failed_rows' => 0]);
        ImportTrainingBatch::dispatch($batch->id);

        return redirect()->route('import.show', $batch);
    }

    public function show(ImportBatch $batch)
    {
        if ($batch->status === 'pending') {
            return redirect()->route('import.preview', $batch);
        }

        $log = json_decode((string) $batch->error_log, true);
        $message = is_array($log) ? ($log['message'] ?? null) : $batch->error_log;
        $failedRows = is_array($log) ? ($log['failed_rows'] ?? []) : [];

        return view('import.show', [
            'batch'      => $batch,
            'message'    => $message,
            'failedRows' => $failedRows,
        ]);
    }

    /** JSON untuk polling progress */
    public function status(ImportBatch $batch)
    {
        $done = in_array($batch->status, ['success', 'partial', 'failed'], true);
        $processed = $batch->successful_rows + $batch->failed_rows;

        if ($done && $batch->status !== 'failed') {
            $percent = 100;
        } elseif ($batch->total_rows > 0) {
            $percent = min(99, (int) floor($processed / $batch->total_rows * 100));
        } else {
            $percent = 0;
        }

        return response()->json([
            'status'    => $batch->status,
            'done'      => $done,
            'total'     => $batch->total_rows,
            'processed' => $processed,
            'percent'   => $percent,
        ]);
    }

    public function cancel(ImportBatch $batch)
    {
        if ($batch->status !== 'pending') {
            return redirect()->route('import.index')
                ->withErrors(['file' => 'Hanya upload yang masih menunggu konfirmasi yang bisa dibatalkan.']);
        }

        $batch->deleteFile();
        $batch->delete();

        return redirect()->route('import.index')->with('status', 'Upload dibatalkan dan file dihapus.');
    }

    private function fail(ImportBatch $batch, string $message)
    {
        $batch->update([
            'status'       => 'failed',
            'error_log'    => json_encode(['message' => $message, 'failed_rows' => []], JSON_UNESCAPED_UNICODE),
            'completed_at' => now(),
        ]);
        $batch->deleteFile();

        return back()->withErrors(['file' => $message]);
    }
}