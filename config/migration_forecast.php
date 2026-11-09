<?php

return [
    // Philippine calendar; ang current/incomplete month dili apil sa WMA.
    'timezone' => 'Asia/Manila',
    'window_months' => 6, // 3 hangtod 6 ka sunod-sunod ug verified nga bulan.
    'baseline_rate' => (float) env('MIGRATION_BASELINE_RATE', 0.001),
];
