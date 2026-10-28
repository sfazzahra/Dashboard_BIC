@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .chart-wrap { position: relative; }
    .chart-title { font-size: .8rem; font-weight: 600; color: #1b2a41; line-height: 1.3; }
    .chart-msg { color: #6c7a8d; text-align: center; padding: 3rem 1rem; font-size: .9rem; }
    .chart-card.loading { opacity: .55; transition: opacity .15s; }
</style>
@endpush

@section('content')
@if (!$batch)
    <div class="alert alert-info">
        Belum ada data training.
        <a href="{{ route('import.index') }}" class="alert-link">Import file Excel</a> terlebih dahulu.
    </div>
@else
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small fw-semibold mb-1" for="fYear">YEAR</label>
                    <select id="fYear" class="form-select form-select-sm"><option value="All">All</option></select>
                </div>
                <div class="col-6 col-md-4 col-xl-3">
                    <label class="form-label small fw-semibold mb-1" for="fDivision">DIVISION</label>
                    <select id="fDivision" class="form-select form-select-sm"><option value="All">All</option></select>
                </div>
                <div class="col-8 col-md-4 col-xl-3">
                    <label class="form-label small fw-semibold mb-1" for="fType">TYPE OF TRAINING</label>
                    <select id="fType" class="form-select form-select-sm"><option value="All">All</option></select>
                </div>
                <div class="col-4 col-md-auto">
                    <button id="btnReset" type="button" class="btn btn-sm btn-outline-secondary">Reset Filter</button>
                </div>
            </div>
        </div>
    </div>

    <div id="batchInfo" class="small text-muted mb-2"></div>
    <div id="grid" class="row g-3"></div>
@endif
@endsection

@if ($batch)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
(function () {
    const metaUrl = @json(route('api.dashboard.meta'));
    const chartBase = @json(url('/api/dashboard/charts'));

    Chart.register(ChartDataLabels);
    Chart.defaults.set('plugins.datalabels', { display: false });
    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

    const BLUE = '#2f6fc7';
    const ORANGE = '#ed7d31';
    const instances = {};
    const tokens = {};
    let meta = null;

    const $ = id => document.getElementById(id);
    const nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    const cf = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
    const pf = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

    function addOptions(select, values) {
        values.forEach(v => {
            const o = document.createElement('option');
            o.value = v;
            o.textContent = v;
            select.appendChild(o);
        });
    }

    function buildCard(c) {
        const col = document.createElement('div');
        col.className = 'col-12 col-md-6 col-xl-4';
        col.innerHTML =
            '<div class="card h-100 shadow-sm chart-card" id="card-' + c.id + '">' +
              '<div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">' +
                '<span class="chart-title"></span>' +
                '<span class="badge text-bg-light border flex-shrink-0 chart-total"></span>' +
              '</div>' +
              '<div class="card-body">' +
                '<div class="chart-wrap" id="wrap-' + c.id + '"><canvas id="cv-' + c.id + '"></canvas></div>' +
                '<div class="chart-msg d-none" id="msg-' + c.id + '"></div>' +
              '</div>' +
            '</div>';
        col.querySelector('.chart-title').textContent = c.title;
        return col;
    }

    function query() {
        return new URLSearchParams({
            year: $('fYear').value,
            division: $('fDivision').value,
            training_type: $('fType').value,
        }).toString();
    }

    function loadAll() {
        meta.charts.forEach(c => loadChart(c));
    }

    async function loadChart(c) {
        const token = (tokens[c.id] = (tokens[c.id] || 0) + 1);
        const card = $('card-' + c.id);
        card.classList.add('loading');

        try {
            const res = await fetch(chartBase + '/' + c.id + '?' + query(), {
                headers: { 'Accept': 'application/json' },
            });
            if (token !== tokens[c.id]) return; // respons usang, abaikan
            if (!res.ok) {
                let m = 'Gagal memuat data (kode ' + res.status + ').';
                try { m = (await res.json()).message || m; } catch (e) {}
                showMessage(c.id, m);
                setTotal(c.id, '-');
                return;
            }
            const d = await res.json();
            if (token !== tokens[c.id]) return;
            render(c.id, d);
        } catch (e) {
            if (token === tokens[c.id]) {
                showMessage(c.id, 'Gagal memuat data.');
                setTotal(c.id, '-');
            }
        } finally {
            if (token === tokens[c.id]) card.classList.remove('loading');
        }
    }

    function setTotal(id, text) {
        $('card-' + id).querySelector('.chart-total').textContent = text;
    }

    function destroy(id) {
        if (instances[id]) { instances[id].destroy(); delete instances[id]; }
    }

    function showMessage(id, text) {
        destroy(id);
        $('wrap-' + id).classList.add('d-none');
        const m = $('msg-' + id);
        m.classList.remove('d-none');
        m.textContent = text;
    }

    function render(id, d) {
        destroy(id);

        const isFund = d.metric === 'fund';
        const fmt = v => isFund ? 'Rp ' + cf.format(v) : nf.format(v);
        setTotal(id, 'Total: ' + (isFund ? 'Rp ' + nf.format(d.total) : nf.format(d.total)));

        if (d.empty) {
            showMessage(id, 'Belum ada data untuk filter ini.');
            return;
        }

        const wrap = $('wrap-' + id);
        $('msg-' + id).classList.add('d-none');
        wrap.classList.remove('d-none');
        wrap.style.height = d.visual === 'hbar'
            ? Math.max(280, d.labels.length * 24 + 70) + 'px'
            : '320px';

        const ctx = $('cv-' + id);
        const common = { responsive: true, maintainAspectRatio: false };
        let cfg;

        if (d.visual === 'hbar') {
            cfg = {
                type: 'bar',
                data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: BLUE, borderWidth: 0 }] },
                options: {
                    ...common,
                    indexAxis: 'y',
                    layout: { padding: { right: 95 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: c => fmt(c.parsed.x) + ' (' + pf.format(d.percents[c.dataIndex]) + '%)' } },
                        datalabels: {
                            display: true, anchor: 'end', align: 'end', font: { size: 10 },
                            formatter: (v, c) => fmt(v) + ' (' + pf.format(d.percents[c.dataIndex]) + '%)',
                        },
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { callback: v => fmt(v) } },
                        y: { ticks: { autoSkip: false, font: { size: 10 } } },
                    },
                },
            };
        } else if (d.visual === 'pareto') {
            cfg = {
                data: {
                    labels: d.labels,
                    datasets: [
                        {
                            type: 'bar', label: d.metric_label, data: d.values, backgroundColor: BLUE,
                            yAxisID: 'y', order: 2,
                            datalabels: { display: true, anchor: 'end', align: 'end', font: { size: 10 }, formatter: v => fmt(v) },
                        },
                        {
                            type: 'line', label: 'Kumulatif %', data: d.cumulative, borderColor: ORANGE,
                            backgroundColor: ORANGE, yAxisID: 'y1', order: 1, tension: 0, pointRadius: 3,
                            datalabels: { display: true, align: 'top', color: '#c0392b', font: { size: 9 }, formatter: v => Math.round(v) + '%' },
                        },
                    ],
                },
                options: {
                    ...common,
                    layout: { padding: { top: 18 } },
                    plugins: { legend: { display: true, position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true, position: 'left', ticks: { callback: v => fmt(v) } },
                        y1: { min: 0, max: 100, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } },
                        x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 45, font: { size: 10 } } },
                    },
                },
            };
        } else { // bar
            cfg = {
                type: 'bar',
                data: { labels: d.labels, datasets: [{ label: d.metric_label, data: d.values, backgroundColor: BLUE, borderWidth: 0 }] },
                options: {
                    ...common,
                    layout: { padding: { top: 20 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: c => fmt(c.parsed.y) } },
                        datalabels: {
                            display: true, anchor: 'end', align: 'end', font: { size: 10 },
                            formatter: v => v ? fmt(v) : '',
                        },
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: v => fmt(v) } },
                        x: { ticks: { autoSkip: false, font: { size: 10 } } },
                    },
                },
            };
        }

        instances[id] = new Chart(ctx, cfg);
    }

    async function init() {
        const res = await fetch(metaUrl, { headers: { 'Accept': 'application/json' } });
        meta = await res.json();

        if (!meta.has_data) {
            $('grid').innerHTML = '<div class="col-12"><div class="alert alert-info">Belum ada data. Import file Excel terlebih dahulu.</div></div>';
            return;
        }

        addOptions($('fYear'), meta.years);
        addOptions($('fDivision'), meta.divisions);
        addOptions($('fType'), meta.training_types);

        $('batchInfo').textContent =
            'Sumber data: ' + meta.batch.file_name + ' · diimpor ' + (meta.batch.completed_at || '-') +
            ' · ' + nf.format(meta.batch.rows) + ' baris';

        const grid = $('grid');
        meta.charts.forEach(c => grid.appendChild(buildCard(c)));

        ['fYear', 'fDivision', 'fType'].forEach(id => $(id).addEventListener('change', loadAll));
        $('btnReset').addEventListener('click', () => {
            ['fYear', 'fDivision', 'fType'].forEach(id => { $(id).value = 'All'; });
            loadAll();
        });

        loadAll();
    }

    init().catch(() => {
        $('grid').innerHTML = '<div class="col-12"><div class="alert alert-danger">Gagal memuat dashboard.</div></div>';
    });
})();
</script>
@endpush
@endif