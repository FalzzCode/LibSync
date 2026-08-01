<?php

return [
    // Tarif ini sengaja dipusatkan agar mudah disesuaikan tanpa mengubah logika transaksi.
    'fine_per_day' => (int) env('LIBRARY_FINE_PER_DAY', 1000),
];
