<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Issue #309: a post-commit unlink ledger for the account-teardown sweep.
 *
 * ProfileController::destroy() wraps the whole teardown in one DB::transaction
 * (#266), but Alert::deleting / Experience::deleting unlinked their files
 * INSIDE it: a throw in a late sweep rolled every row back over files that
 * were already gone, resurrecting an account whose posts pointed at deleted
 * images — the "honest residual" #266 documented. Hooks still cannot defer
 * to DB::afterCommit: under the test wrapper (and any caller transaction)
 * the callback rides the level-0 record and is DISCARDED at teardown
 * (DatabaseTransactionsManager::rollback only runs rollback callbacks), so a
 * framework-callback deferral silently never unlinks in CI. The deferral is
 * therefore explicit and caller-owned: destroy() arms the ledger before its
 * transaction, the hooks capture paths instead of unlinking WHILE ARMED, and
 * destroy() drains (executes) only after the commit returns — or discards
 * the whole list when the transaction throws, because rolled-back rows keep
 * their files. Every other caller (admin destroy, update() swaps) leaves the
 * ledger unarmed, so capture() unlinks immediately there and the #289
 * current-read semantics are untouched everywhere: arming changes WHEN the
 * unlink runs, never WHICH path it takes.
 */
class DeferredFileUnlinks
{
    /** @var list<string>|null armed gate: array = collect paths, null = unlink now */
    protected static ?array $pending = null;

    public static function arm(): void
    {
        static::$pending = [];
    }

    public static function armed(): bool
    {
        return static::$pending !== null;
    }

    /**
     * Free a public-disk path: through the armed ledger when a caller
     * transaction needs it deferred to commit time, immediately otherwise.
     */
    public static function capture(string $path): void
    {
        if (static::$pending === null) {
            Storage::disk('public')->delete($path);

            return;
        }

        static::$pending[] = $path;
    }

    /** Execute every captured unlink (called only after a real commit) and disarm. */
    public static function drain(): void
    {
        $paths = static::$pending ?? [];
        static::$pending = null;

        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /** Throw away captured unlinks (the caller's transaction rolled back) and disarm. */
    public static function discard(): void
    {
        static::$pending = null;
    }
}
