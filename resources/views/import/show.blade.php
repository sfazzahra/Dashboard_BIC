@extends('layouts.app')

@section('title', 'Import #' . $batch->id)

@section('content')
@php
    $int = fn ($v) => number_format((float) $v, 0, ',', '.');
    $running = in_array($batch->status, ['queued', 'processing'], true);
    $processed = $batch->successful_rows + $batch->failed_rows;
    $percent = $batch->total_rows > 0 ? min(99, (int) floor($processed / $batch->total_rows * 100)) : 0;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Import #{{ $batch->id }}</h4>
        <div class="text-muted small">{{ $batch->file_name }}</div>
    </div>
    <a href="{{ route('import.index') }}" class="btn btn-outline-secondary btn-sm">Kembali ke Import</a>
</div>

@if ($running)
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
                <strong id="msg">Menunggu worker...</strong>
                <span id="pct">{{ $percent }}%</span>
            </div>
            <div class="progress" style="height: 22px;">
                <div id="bar" class="progress-bar progress-bar-striped progress-bar-animated"
                     style="width: {{ $percent }}%"></div>
            </div>
            <div id="hint" class="alert alert-warning mt-3 mb-0 d-none">
                Import belum dimulai. Pastikan <strong>queue worker</strong> berjalan di terminal terpisah:
                <code>php artisan queue:work --timeout=0 --tries=1</code>
            </div>
        </div>
    </div>

    <script>
        const statusUrl = @json(route('import.status', $batch));
        const startedAt = Date.now();
        const fmt = n => Number(n).toLocaleString('id-ID');

        async function poll() {
            try {
                const r = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();

                document.getElementById('bar').style.width = d.percent + '%';
                document.getElementById('pct').textContent = d.percent + '%';

                if (d.done) { location.reload(); return; }

                const msg = document.getElementById('msg');
                if (d.status === 'queued') {
                    msg.textContent = 'Menunggu worker...';
                    if (Date.now() - startedAt > 15000) {
                        document.getElementById('hint').classList.remove('d-none');
                    }
                } else if (d.processed === 0) {
                    msg.textContent = 'Menyiapkan: membaca kategori dari file (sekitar 40 detik)...';
                } else {
                    msg.textContent = 'Menyimpan ' + fmt(d.processed) + ' dari ' + fmt(d.total) + ' baris';
                }
            } catch (e) { /* abaikan, coba lagi */ }
            setTimeout(poll, 2000);
        }
        poll();
    </script>
@else
    @php
        [$color, $title] = match ($batch->status) {
            'success' => ['success', 'Import berhasil'],
            'partial' => ['warning', 'Import selesai sebagian'],
            default   => ['danger', 'Import gagal'],
        };
    @endphp

    <div class="alert alert-{{ $color }}">
        <strong>{{ $title }}.</strong>
        @if ($message) {{ $message }} @endif
        @if ($batch->is_active)
            Data ini sekarang menjadi <strong>snapshot aktif</strong>.
        @endif
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['Total baris', $batch->total_rows, 'primary'], ['Berhasil', $batch->successful_rows, 'success'], ['Gagal', $batch->failed_rows, 'danger']] as [$label, $val, $c])
            <div class="col-4">
                <div class="card border-{{ $c }}">
                    <div class="card-body py-3">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-4 fw-semibold text-{{ $c }}">{{ $int($val) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <table class="table table-sm mb-0 small">
            <tr><td class="text-muted" style="width: 200px;">Sheet sumber</td><td>{{ $batch->sheet_name ?? '-' }}</td></tr>
            <tr><td class="text-muted">Mulai</td><td>{{ $batch->started_at?->format('d M Y H:i:s') ?? '-' }}</td></tr>
            <tr><td class="text-muted">Selesai</td><td>{{ $batch->completed_at?->format('d M Y H:i:s') ?? '-' }}</td></tr>
            <tr><td class="text-muted">SHA-256</td><td class="text-break">{{ $batch->file_hash }}</td></tr>
        </table>
    </div>

    @if (count($failedRows) > 0)
        <div class="card mb-3">
            <div class="card-header fw-semibold">Baris gagal (maksimal 200 ditampilkan)</div>
            <div class="table-responsive" style="max-height: 360px;">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light"><tr><th>Baris Excel</th><th>Kolom</th><th>Masalah</th></tr></thead>
                    <tbody>
                    @foreach ($failedRows as $f)
                        <tr>
                            <td>{{ $f['row'] }}</td>
                            <td>{{ $f['field'] }}</td>
                            <td>{{ $f['type'] === 'missing_required' ? 'Kolom wajib kosong' : $f['type'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
@endsection