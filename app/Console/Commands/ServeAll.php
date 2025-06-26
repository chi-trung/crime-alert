<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeAll extends Command
{
    protected $signature = 'serve:all';
    protected $description = 'Serve Laravel and crawl news & wanted list at the same time';

    public function handle()
    {
        $this->info('Crawling news...');
        $crawlNews = new Process(['php', 'artisan', 'crawl:news']);
        $crawlNews->start();

        $this->info('Crawling wanted list...');
        $crawlWanted = new Process(['php', 'artisan', 'crawl:wanted-list']);
        $crawlWanted->start();

        $this->info('Starting Laravel server...');
        // Chạy server ở foreground, output sẽ hiển thị trực tiếp
        passthru('php artisan serve');
    }
} 