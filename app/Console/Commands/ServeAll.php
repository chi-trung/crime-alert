<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeAll extends Command
{
    protected $signature = 'serve:all';

    protected $description = 'Serve Laravel and crawl news & wanted list at the same time';

    /** @var list<Process> */
    private array $crawlers = [];

    public function handle(): int
    {
        // PHP_BINARY = the interpreter actually running this command, so the
        // toolchain works even when `php` is not on PATH (issue #16).
        $php = PHP_BINARY;

        $this->info('Crawling news...');
        $this->crawlers[] = $this->startCrawler($php, 'crawl:news');

        $this->info('Crawling wanted list...');
        $this->crawlers[] = $this->startCrawler($php, 'crawl:wanted-list');

        $this->info('Starting Laravel server...');
        // Chạy server ở foreground, output sẽ hiển thị trực tiếp.
        $server = new Process([$php, 'artisan', 'serve'], base_path(), null, null, null);
        $exitCode = $server->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        // Server stopped (Ctrl+C / process killed): don't leave crawlers hanging.
        $this->stopCrawlers();

        return $exitCode ?? self::SUCCESS;
    }

    private function startCrawler(string $php, string $command): Process
    {
        $process = new Process([$php, 'artisan', $command], base_path());
        $process->setTimeout(300);
        $process->start();

        return $process;
    }

    private function stopCrawlers(): void
    {
        foreach ($this->crawlers as $process) {
            if ($process->isRunning()) {
                $process->stop(3);
            }
        }
    }
}
