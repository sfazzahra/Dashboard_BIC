<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ImportBatch extends Model
{
    protected $fillable = [
        'file_name', 'file_hash', 'imported_by', 'sheet_name',
        'total_rows', 'successful_rows', 'failed_rows', 'status',
        'is_active', 'detected_columns', 'error_log',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'detected_columns' => 'array',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(TrainingRecord::class);
    }

    /** Batch yang sedang dipakai dashboard (null jika belum ada import). */
    public static function active(): ?self
    {
        return static::where('is_active', true)->latest('id')->first();
    }

    /** Jadikan batch ini aktif, nonaktifkan yang lain. */
    public function activate(): void
    {
        static::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /** Lokasi file Excel yang tersimpan untuk batch ini (relatif terhadap disk "local"). */
    public function storedFile(): string
    {
        return "imports/{$this->id}.xlsx";
    }

    /** Lokasi lengkap file di komputer/server. */
    public function filePath(): string
    {
        return Storage::disk('local')->path($this->storedFile());
    }

    public function deleteFile(): void
    {
        Storage::disk('local')->delete($this->storedFile());
    }
}