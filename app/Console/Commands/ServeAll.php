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

        // Best-effort orphan guard (issue #301): if ServeAll itself is
        // SIGTERM/SIGINT'd, stop the crawlers on the way out instead of
        // leaving them running. pcntl is not available on Windows (this is
        // a dev-tooling command, mostly run there), so guard by feature.
        if (\function_exists('pcntl_async_signals') && \function_exists('pcntl_signal')) {
            \pcntl_async_signals(true);
            $stopCrawlers = function () {
                $this->stopCrawlers();
                exit(0);
            };
            if (\defined('SIGTERM')) {
                \pcntl_signal(SIGTERM, $stopCrawlers);
            }
            if (\defined('SIGINT')) {
                \pcntl_signal(SIGINT, $stopCrawlers);
            }
        }

        $this->info('Crawling news...');
        $this->crawlers[] = $this->startCrawler($php, 'crawl:news');

        $this->info('Crawling wanted list...');
        $this->crawlers[] = $this->startCrawler($php, 'crawl:wanted-list');

        $this->info('Starting Laravel server...');
        // Chạy server ở foreground, output sẽ hiển thị trực tiếp.
        $server = new Process([$php, 'artisan', 'serve'], base_path(), null, null, null);
        // Issue #301: Symfony evaluates a Process timeout ONLY inside
        // checkTimeout(), which run()/wait() call for the process being
        // waited on. The crawlers are start()ed and never polled, so
        // setTimeout(300) in startCrawler() was dead configuration — a hung
        // crawl stayed alive for the whole dev session. Tick the deadlines
        // from the server's output callback: every line the dev server
        // writes (requests, boot messages) gets the crawlers checked first.
        // Known limitation, documented honestly: a server that emits no
        // output never ticks — but a quiet `artisan serve` still answers
        // requests while the watchdog is dormant only for that window.
        $exitCode = $server->run(function ($type, $buffer) {
            $this->stopExpiredCrawlers();
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

    /**
     * Enforce the per-crawler timeout that Symfony would only enforce if we
     * polled the process ourselves (issue #301). checkTimeout()'s own
     * docblock: "In case you run a background process (with the start
     * method), you should trigger this method regularly." We replicate the
     * elapsed-vs-deadline comparison so the kill is a graceful stop(3)
     * (which on Windows is taskkill /F /T, taking the whole child tree with
     * it) instead of checkTimeout()'s hard stop(0), and so finished
     * processes are never disturbed. isRunning() refreshes the status from
     * the OS first, so a crawl that exited on its own is skipped and keeps
     * its exit code.
     */
    public function stopExpiredCrawlers(): void
    {
        foreach ($this->crawlers as $process) {
            if (! $process->isRunning()) {
                continue;
            }

            $timeout = $process->getTimeout();
            if ($timeout === null) {
                continue;
            }

            if (microtime(true) - $process->getStartTime() > $timeout) {
                // Output may be uninitialized when this is called outside a
                // command run (tests); guard the notice, never the kill.
                if (isset($this->output)) {
                    $this->warn('Crawler vượt quá timeout — dừng nó để không treo cả phiên dev.');
                }
                $process->stop(3);
            }
        }
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
