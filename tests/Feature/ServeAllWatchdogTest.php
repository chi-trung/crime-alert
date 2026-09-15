<?php

namespace Tests\Feature;

use App\Console\Commands\ServeAll;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Issue #301 (console-2): ServeAll starts the two crawlers with
 * Process::setTimeout(300) and then blocks in $server->run(). Symfony only
 * evaluates the timeout inside checkTimeout(), which the polling loops of
 * run()/wait()/waitUntil() call for the process being waited on (verified
 * on this install: Process.php checkTimeout callsites are 417/478/484/522/
 * 712, all inside that process's own wait path). The crawler processes are
 * start()ed and NEVER polled afterwards — stopCrawlers() only runs once the
 * foreground server exits — so the "300 seconds per crawl" contract is dead
 * configuration: a crawler hung on a network read stays alive, holding a DB
 * connection, for the entire dev session. checkTimeout()'s own docblock
 * says background processes must trigger it regularly; ServeAll never does.
 */
class ServeAllWatchdogTest extends TestCase
{
    /**
     * Register processes in the command's private crawler list the same way
     * handle() does, without booting the blocking server.
     */
    private function register(ServeAll $command, array $processes): void
    {
        (new \ReflectionProperty(ServeAll::class, 'crawlers'))->setValue($command, $processes);
    }

    /**
     * Drive the actual watchdog tick the command runs and assert it kills a
     * process that has exceeded its own timeout, while leaving a process
     * still inside its deadline alone — the kill is the deadline's doing,
     * not blind stopping. Pre-fix the tick method does not exist and this
     * test errors.
     */
    public function test_watchdog_tick_stops_a_process_past_its_timeout(): void
    {
        $probe = \sys_get_temp_dir().\DIRECTORY_SEPARATOR.\uniqid('serveall_sleep_', true).'.php';
        \file_put_contents($probe, "<?php\nsleep(60);\n");

        try {
            $dead = new Process([\PHP_BINARY, $probe], base_path());
            $dead->setTimeout(0.2);
            $dead->start();

            $alive = new Process([\PHP_BINARY, $probe], base_path());
            $alive->setTimeout(120);
            $alive->start();

            $command = new ServeAll;
            $this->register($command, [$dead, $alive]);

            \usleep(400000); // now well past $dead's 0.2s deadline

            $command->stopExpiredCrawlers();

            $this->assertFalse(
                $dead->isRunning(),
                'setTimeout(300) is never evaluated for start()ed-but-unpolled processes: checkTimeout() is only called inside run()/wait()/waitUntil polling loops (Process.php 417/478/484/522/712), and ServeAll polls none of the crawlers, so the timeout is dead configuration and the intended 300s budget silently becomes "whole dev session".'
            );

            $this->assertTrue(
                $alive->isRunning(),
                'watchdog stopped a process still inside its timeout — the tick must compare elapsed against the deadline, not kill blindly'
            );

            $alive->stop(3);
            $dead->stop(3);
        } finally {
            @\unlink($probe);
        }
    }

    /**
     * The tick must not disturb finished processes — crucially it must not
     * reap-and-lose their exit status or treat them as timed out.
     */
    public function test_watchdog_never_stops_finished_processes(): void
    {
        $probe = \sys_get_temp_dir().\DIRECTORY_SEPARATOR.\uniqid('serveall_done_', true).'.php';
        \file_put_contents($probe, "<?php\nexit(0);\n");

        try {
            $done = new Process([\PHP_BINARY, $probe], base_path());
            $done->setTimeout(0.2);
            $done->start();

            while ($done->isRunning()) {
                \usleep(1000);
            }
            // Past the deadline AND already exited: isRunning() must gate.
            \usleep(300000);

            $command = new ServeAll;
            $this->register($command, [$done]);

            $command->stopExpiredCrawlers();

            $this->assertFalse($done->isRunning());
            $this->assertSame(
                0,
                $done->getExitCode(),
                'tick disturbed a finished process — exit status must survive the watchdog sweep'
            );
        } finally {
            @\unlink($probe);
        }
    }

    /**
     * Wiring pin: handle() must run the tick WHILE the server blocks (on
     * the server output callback path, which fires on every output line),
     * not only after $server->run() returns — and must keep the crawler
     * processes reachable at that point. Also pins the best-effort signal
     * handler (pcntl-guarded: absent on Windows dev boxes) so SIGTERM to
     * ServeAll no longer orphans the crawlers.
     */
    public function test_watchdog_tick_is_wired_into_the_blocking_server_path(): void
    {
        $source = \file_get_contents(base_path('app/Console/Commands/ServeAll.php'));

        $this->assertMatchesRegularExpression(
            '/stopExpiredCrawlers\(\)/',
            $source,
            'ServeAll::handle never evaluates the crawler timeouts: start()d Symfony processes only get checkTimeout() from their own run()/wait() polling loop, so setTimeout(300) is dead configuration and a hung crawl persists for the whole dev session'
        );

        // The tick must sit on the server output callback path (fires on
        // every output line) — pin that it is wired inside run()'s
        // callback, not only after it returns.
        $this->assertMatchesRegularExpression(
            '/\$server->run\(function[^}]*stopExpiredCrawlers/s',
            $source,
            'watchdog tick is not wired into the server output callback — if it only runs after $server->run() returns, a quiet-forever server never ticks and the crawlers still hang for the session'
        );

        $this->assertMatchesRegularExpression(
            '/function_exists\(.pcntl_async_signals.\)/',
            $source,
            'no pcntl-guarded signal handler: ServeAll killed by SIGTERM/SIGINT still leaves both crawler processes orphaned and running'
        );
    }
}
