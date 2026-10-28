@extends('layouts.app')

@section('title', 'Preview Import')

@section('content')
@php
    $int = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dec = fn ($v) => number_format((float) $v, 2, ',', '.');
    $blocked = !empty($s['missing_required']) || ($s['ok_rows'] ?? 0) === 0;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Preview Import</h4>
        <div class="text-muted small">
            {{ $batch->file_name }} &middot; SHA-256 {{ substr($batch->file_hash, 0, 12) }}&hellip;
        </div>
    </div>
    <span class="badge text-bg-warning">Menunggu konfirmasi</span>
</div>

{{-- Ringkasan angka --}}
<div class="row g-3 mb-3">
    @foreach ([['Total baris', $s['total_rows'], 'primary'], ['Baris valid', $s['ok_rows'], 'success'], ['Baris gagal', $s['failed_rows'], 'danger'], ['Baris identik', $s['duplicates'], 'secondary']] as [$label, $val, $color])
        <div class="col-6 col-lg-3">
            <div class="card border-{{ $color }}">
                <div class="card-body py-3">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-4 fw-semibold text-{{ $color }}">{{ $int($val) }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Deteksi struktur --}}
<div class="card mb-3">
    <div class="card-header fw-semibold">Struktur file</div>
    <div class="card-body small">
        <div>Sheet terdeteksi: <strong>{{ $s['sheet'] }}</strong> (header di baris {{ $s['header_row'] }})</div>
        <div>Kolom dikenali: <strong>{{ count($s['matched']) }}</strong></div>

        @if (!empty($s['missing_required']))
            <div class="alert alert-danger mt-2 mb-0">
                Kolom wajib tidak ditemukan: <strong>{{ implode(', ', $s['missing_required']) }}</strong>.
                Import tidak dapat dilanjutkan.
            </div>
        @endif
        @if (!empty($s['missing_recommended']))
            <div class="alert alert-warning mt-2 mb-0">
                Kolom yang dibutuhkan dashboard tidak ditemukan: <strong>{{ implode(', ', $s['missing_recommended']) }}</strong>.
                Grafik terkait akan kosong.
            </div>
        @endif
        @if (!empty($s['unknown_headers']))
            <div class="alert alert-info mt-2 mb-0">
                Kolom baru (belum dikenal, disimpan apa adanya): {{ implode(', ', $s['unknown_headers']) }}
            </div>
        @endif
        @if (!empty($s['skipped_sheets']))
            <div class="mt-2 text-muted">
                Sheet dilewati:
                @foreach ($s['skipped_sheets'] as $sk){{ $sk['name'] }} ({{ $sk['reason'] }})@if (!$loop->last), @endif @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Sebaran nilai --}}
<div class="row g-3 mb-3">
    @foreach ([['Status', 'status'], ['Level (Group)', 'level'], ['Division', 'division'], ['Training Type', 'training_type']] as [$title, $key])
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-header small fw-semibold">{{ $title }} ({{ count($s[$key]) }} nilai)</div>
                <div class="table-responsive" style="max-height: 340px;">
                    <table class="table table-sm mb-0">
                        <tbody>
                        @foreach ($s[$key] as $label => $count)
                            <tr>
                                <td class="{{ $label === '(kosong)' ? 'text-muted fst-italic' : '' }}">{{ $label }}</td>
                                <td class="text-end">{{ $int($count) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Tahun dan total --}}
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header fw-semibold">Tahun</div>
            <div class="card-body small">
                <div class="mb-2">
                    <strong>Training year</strong> (kosong: {{ $int($s['years_null']) }})<br>
                    @foreach ($s['years'] as $y => $c)
                        <span class="badge text-bg-light border">{{ $y }}: {{ $int($c) }}</span>
                    @endforeach
                </div>
                <div>
                    <strong>Renewal year</strong> (terisi: {{ $int(array_sum($s['renewal'])) }}, kosong: {{ $int($s['renewal_null']) }})<br>
                    @foreach ($s['renewal'] as $y => $c)
                        <span class="badge text-bg-light border">{{ $y }}: {{ $int($c) }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Total</div>
            <table class="table table-sm mb-0">
                <tr><td>Training hours</td><td class="text-end">{{ $dec($s['sums']['hours']) }}</td></tr>
                <tr><td>Training days</td><td class="text-end">{{ $dec($s['sums']['days']) }}</td></tr>
                <tr><td>Course fee</td><td class="text-end">{{ $int($s['sums']['fee']) }}</td></tr>
                <tr><td>Total cost</td><td class="text-end">{{ $int($s['sums']['cost']) }}</td></tr>
                <tr><td>Baris ber-cost</td><td class="text-end">{{ $int($s['fee_rows']) }}</td></tr>
            </table>
        </div>
    </div>
</div>

{{-- Variasi huruf yang disatukan --}}
@if (!empty($s['merged_variants']))
    <div class="card mb-3">
        <div class="card-header fw-semibold">Variasi penulisan yang disatukan</div>
        <div class="card-body small">
            @foreach ($s['merged_variants'] as $field => $groups)
                @foreach ($groups as $canonical => $counts)
                    <div>
                        <strong>{{ $canonical }}</strong> &larr;
                        @foreach ($counts as $variant => $n){{ $variant }} ({{ $n }})@if (!$loop->last), @endif @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
@endif

{{-- Catatan pembersihan --}}
@if (!empty($s['issues']))
    <div class="card mb-3">
        <div class="card-header fw-semibold">Catatan pembersihan</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 small">
                <thead class="table-light"><tr><th>Kolom | jenis</th><th class="text-end">Jumlah</th><th>Contoh</th></tr></thead>
                <tbody>
                @foreach ($s['issues'] as $k => $count)
                    <tr>
                        <td>{{ $k }}</td>
                        <td class="text-end">{{ $int($count) }}</td>
                        <td class="text-muted">{{ implode(' ; ', $s['issue_samples'][$k] ?? []) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Tanggal berbentuk rentang atau teks bebas tidak ditebak: teks aslinya disimpan apa adanya.
            Grafik memakai kolom Years dan Year Renewal, jadi tidak terpengaruh.
        </div>
    </div>
@endif

{{-- Sample baris --}}
<div class="card mb-4">
    <div class="card-header fw-semibold">Contoh {{ count($s['samples']) }} baris pertama (tanpa NPK dan nama)</div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0 small">
            <thead class="table-light">
            <tr>
                <th>Baris</th><th>Status</th><th>Level</th><th>Division</th><th>Training Type</th>
                <th>Course Title</th><th>Year</th><th>Renewal</th><th class="text-end">Days</th><th class="text-end">Hrs</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($s['samples'] as $row)
                <tr>
                    <td>{{ $row['source_row'] ?? '' }}</td>
                    <td>{{ $row['status'] ?? '' }}</td>
                    <td>{{ $row['employee_level'] ?? '' }}</td>
                    <td>{{ $row['division'] ?? '' }}</td>
                    <td>{{ $row['training_type'] ?? '' }}</td>
                    <td>{{ $row['course_title'] ?? '' }}</td>
                    <td>{{ $row['training_year'] ?? '' }}</td>
                    <td>{{ $row['renewal_year'] ?? '' }}</td>
                    <td class="text-end">{{ $row['training_days'] ?? '' }}</td>
                    <td class="text-end">{{ $row['training_hours'] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Aksi --}}
<div class="d-flex gap-2">
    <form action="{{ route('import.cancel', $batch) }}" method="POST"
          onsubmit="return confirm('Batalkan upload ini dan hapus file-nya?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-secondary">Batalkan</button>
    </form>

    <form action="{{ route('import.confirm', $batch) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary" @disabled($blocked)>Konfirmasi Import</button>
    </form>
</div>
@if ($blocked)
    <div class="text-danger small mt-2">Import tidak dapat dilanjutkan karena ada kolom wajib yang hilang atau tidak ada baris valid.</div>
@endif
@endsection