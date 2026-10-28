<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRecord extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'training_date'      => 'date',
        'certificate_date'   => 'date',
        'expiry_date'        => 'date',
        'expiry_date_helper' => 'date',
        'date_of_birth'      => 'date',
        'pension_date'       => 'date',
        'training_days'      => 'float',
        'training_hours'     => 'float',
        'course_fee'         => 'float',
        'total_cost'         => 'float',
        'extra_data'         => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    /** Hanya data dari batch aktif. */
    public function scopeInActiveBatch(Builder $query): Builder
    {
        return $query->whereIn('import_batch_id', function ($q) {
            $q->select('id')->from('import_batches')->where('is_active', true);
        });
    }

    /** Filter global dashboard. Nilai kosong atau "All" = tidak difilter. */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        $isSet = fn ($v) => $v !== null && $v !== '' && strcasecmp((string) $v, 'All') !== 0;

        if ($isSet($f['year'] ?? null)) {
            $query->where('training_year', (int) $f['year']);
        }
        if ($isSet($f['division'] ?? null)) {
            $query->where('division', $f['division']);
        }
        if ($isSet($f['training_type'] ?? null)) {
            $query->where('training_type', $f['training_type']);
        }
        return $query;
    }
}