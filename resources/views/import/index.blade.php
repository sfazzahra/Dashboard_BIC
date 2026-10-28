@extends('layouts.app')

@section('title', 'Import Excel')

@section('content')
@php
    $badge = fn ($s) => match ($s) {
        'success'   => ['success', 'Berhasil'],
        'partial'   => ['warning', 'Sebagian'],
        'failed'    => ['danger', 'Gagal'],
        'pending'   => ['info', 'Menunggu konfirmasi'],
        'queued'     => ['secondary', 'Antre'],
        'processing' => ['primary', 'Diproses'],
        'analyzing' => ['secondary', 'Dianalisis'],
        default     => ['secondary', $s],
    };
@endphp

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Upload Excel Training</div>
            <div class="card-body">
                <form id="uploadForm" action="{{ route('import.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file" accept=".xlsx"
                           class="form-control @error('file') is-invalid @enderror" required>
                    @error('file')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Format .xlsx, maksimal 50 MB. Sistem mendeteksi sheet dan kolom data training secara otomatis.
                    </div>

                    <button id="btnUpload" type="submit" class="btn btn-primary mt-3">Upload &amp; Analisis</button>

                    <div id="loading" class="d-none mt-3 text-muted">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Sedang membaca file... biasanya 1-2 menit, jangan tutup halaman ini.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Riwayat Import Terakhir</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>#</th><th>File</th><th>Waktu</th><th class="text-end">Baris</th><th>Status</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($batches as $b)
                        @php [$color, $label] = $badge($b->status); @endphp
                        <tr>
                            <td>{{ $b->id }}</td>
                            <td class="text-break">{{ $b->file_name }}</td>
                            <td class="small">{{ $b->created_at->format('d M Y H:i') }}</td>
                            <td class="text-end">{{ number_format($b->total_rows, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge text-bg-{{ $color }}">{{ $label }}</span>
                                @if ($b->is_active)<span class="badge text-bg-primary">Aktif</span>@endif
                            </td>
                            <td>
                                @if ($b->status === 'pending')
                                    <a href="{{ route('import.preview', $b) }}" class="btn btn-sm btn-outline-primary">Lihat preview</a>
                                @else
                                    <a href="{{ route('import.show', $b) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada import.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('uploadForm').addEventListener('submit', function () {
        document.getElementById('btnUpload').disabled = true;
        document.getElementById('loading').classList.remove('d-none');
    });
</script>
@endsection