<?php

return [
    // Tahun awal untuk chart "2012 - 2026". Bisa ditimpa lewat app_settings (key: chart_start_year).
    'chart_start_year' => 2012,

    // Panjang jendela renewal (5 = tahun ini sampai +4). Awal jendela default = tahun berjalan,
    // bisa ditimpa lewat app_settings (key: renewal_start_year).
    'renewal_span' => 5,

    'cache_minutes' => 10,

    // Tampilan label Group (nilai asli di database tidak berubah)
    'level_labels' => [
        'EXECUTIVE' => 'EXE',
        'MANAGER'   => 'MGR',
        'NON-EXEC'  => 'NON EXE',
    ],

    /*
     * Definisi chart.
     *  period    : none | training_all | training_recent | renewal
     *  dimension : training_type | division | level | training_year | renewal_year
     *  metric    : frq (COUNT course_title) | hours | days | fund
     *  visual    : hbar | bar | pareto
     * Placeholder judul: {from} {to} {recent_from} {rfrom} {rto}
     */
    'charts' => [
        1 => [
            'title'     => 'BIC TRAINING {from} - {to} (FRQ)',
            'period'    => 'training_all',
            'dimension' => 'training_type',
            'metric'    => 'frq',
            'visual'    => 'hbar',
        ],
        2 => [
            'title'     => 'BIC TRAINING {recent_from} - {to} (FRQ)',
            'period'    => 'training_recent',
            'dimension' => 'training_type',
            'metric'    => 'frq',
            'visual'    => 'hbar',
        ],
        3 => [
            'title'     => 'TRAINING RENEWAL {rfrom} - {rto}',
            'period'    => 'renewal',
            'dimension' => 'renewal_year',
            'metric'    => 'frq',
            'visual'    => 'bar',
        ],
        4 => [
            'title'     => 'TRAINING RENEWAL PRIORITY {rfrom}-{rto} (FRQ)',
            'period'    => 'renewal',
            'dimension' => 'training_type',
            'metric'    => 'frq',
            'visual'    => 'pareto',
        ],
        5 => [
            'title'     => 'TRAINING FUND {recent_from} - {to} (TOTAL)',
            'period'    => 'training_recent',
            'dimension' => 'training_year',
            'metric'    => 'fund',
            'visual'    => 'bar',
        ],
        6 => [
            'title'     => 'TRAINING FUND RENEWAL {rfrom} - {rto} (TOTAL)',
            'period'    => 'renewal',
            'dimension' => 'renewal_year',
            'metric'    => 'fund',
            'visual'    => 'bar',
        ],
    ],
];