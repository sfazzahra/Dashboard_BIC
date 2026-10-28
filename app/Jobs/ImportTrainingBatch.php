<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Import\TrainingImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportTrainingBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;       // jangan diulang otomatis
    public int $timeout = 3600;  // maks 1 jam

    public function __construct(public int $batchId)
    {
    }

    public function handle(TrainingImporter $importer): void
    {
        $batch = ImportBatch::find($this->batchId);

        if (!$batch || $batch->status !== 'queued') {
            return; // sudah diproses atau dibatalkan
        }

        $importer->run($batch);
    }

    public function failed(?Throwable $e): void
    {
        $batch = ImportBatch::find($this->batchId);
        if ($batch) {
            app(TrainingImporter::class)->markFailed($batch, $e?->getMessage() ?? 'Import gagal.');
        }
    }
}