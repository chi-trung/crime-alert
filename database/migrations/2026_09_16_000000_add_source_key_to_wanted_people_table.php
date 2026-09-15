<?php

use App\Models\WantedPerson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #305 (r14/collation-digest): CrawlWantedList's #293 four-field
 * updateOrCreate lookup is evaluated by MySQL under utf8mb4_unicode_ci,
 * which folds accent-distinct Vietnamese to equal primary weights —
 * 'Nguyễn Văn An' = 'Nguyễn Văn Ân' probes TRUE on this repo's MySQL
 * container, so two fugitives differing by one diacritic merge silently
 * on production while SQLite (byte-exact, the CI default) keeps them
 * apart. The #294 doctrine for exactly this class: a PHP-computed
 * byte-exact digest as the lookup key, immune to any column collation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wanted_people', function (Blueprint $table) {
            $table->string('source_key', 40)->nullable()->after('id');
        });

        // Backfill before constraining: the digest is over stored bytes, so
        // rows the collation considered equal get DISTINCT keys here and the
        // UNIQUE index builds clean on both engines. Rows left NULL (true
        // byte-duplicates of an already-keyed row) are tolerated by the
        // index — UNIQUE accepts multiple NULLs — and stay reachable only
        // for manual triage rather than crawler updates.
        WantedPerson::backfillMissingSourceKeys();

        Schema::table('wanted_people', function (Blueprint $table) {
            $table->unique('source_key');
        });
    }

    public function down(): void
    {
        Schema::table('wanted_people', function (Blueprint $table) {
            $table->dropUnique(['source_key']);
            $table->dropColumn('source_key');
        });
    }
};
