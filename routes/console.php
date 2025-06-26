<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Đã xóa hoặc đổi tên lệnh serve custom để trả lại lệnh gốc cho Laravel
// Artisan::command('serve', function () {
//     $this->call('crawl:wanted-list');
//     $this->call('crawl:news');
//     $this->call('serve:original');
// })->purpose('Serve the application (kèm crawl wanted-list và news)');

// Artisan::command('serve:original', function () {
//     \Illuminate\Support\Facades\Artisan::call('serve', [
//         '--host' => $_SERVER['argv'][2] ?? null,
//         '--port' => $_SERVER['argv'][3] ?? null,
//     ]);
// });

