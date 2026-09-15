<?php

namespace Tests\Feature;

use App\Models\WantedPerson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Issue #305 (r14/collation-digest): wanted_people has no unique index, and
 * CrawlWantedList's #293 four-field updateOrCreate lookup is evaluated by
 * MySQL under the table collation utf8mb4_unicode_ci — which folds
 * accent-distinct Vietnamese to EQUAL primary weights: 'Nguyễn Văn An' =
 * 'Nguyễn Văn Ân' compares TRUE (probed live on this repo's MySQL container;
 * both unicode_ci and 0900_ai_ci return 1). Two distinct fugitives sharing
 * birth_year + address + decision but differing by one diacritic therefore
 * MERGE on production MySQL: the second crawl silently UPDATEs the first —
 * one person vanishes from /wanted-list and the dashboard tile, and the
 * surviving row's crime describes the wrong person. SQLite (the CI default,
 * byte comparisons) keeps two rows, so 13 rounds of green CI never caught
 * it. The #294 fix for exactly this class (news.link) was a PHP-computed
 * byte-exact digest; same doctrine here: source_key = sha1 over the
 * JSON-encoded field tuple, computed in PHP, UNIQUE-indexed, and the SOLE
 * updateOrCreate key.
 */
class WantedListCollationDigestTest extends TestCase
{
    use RefreshDatabase;

    private function mysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('The unicode_ci accent fold needs MySQL (see class doc).');
        }
    }

    private function tableHtml(string $rowsHtml): string
    {
        return '<table>
                <tr><td>STT</td><td>Họ tên</td><td>Năm sinh</td><td>Địa chỉ</td><td>Cha/Mẹ</td><td>Tội danh</td><td>Quyết định</td><td>Cơ quan</td></tr>
                '.$rowsHtml.'
            </table>';
    }

    private function fakeTable(string $rowsHtml): void
    {
        Http::fake([
            'truyna.bocongan.gov.vn/*' => Http::response($this->tableHtml($rowsHtml), 200),
        ]);
    }

    public function test_diacritic_distinct_fugitives_do_not_merge_on_mysql(): void
    {
        $this->mysql();

        // 'An' and 'Ân' are byte-distinct but unicode_ci-EQUAL. Same birth
        // year, address and decision — the exact shape #293's four-field key
        // was introduced to separate, silently undone by the collation.
        $this->fakeTable('
            <tr><td>1</td><td>Nguyễn Văn An</td><td>1990</td><td>Hà Nội</td><td>Văn A</td><td>Lừa đảo</td><td>QD-91</td><td>C03</td></tr>
            <tr><td>2</td><td>Nguyễn Văn Ân</td><td>1990</td><td>Hà Nội</td><td>Văn B</td><td>Trộm cắp</td><td>QD-91</td><td>PC03</td></tr>
        ');

        $this->artisan('crawl:wanted-list')->assertSuccessful();

        $this->assertSame(
            2,
            WantedPerson::count(),
            "unicode_ci folded 'An'='Ân' in the updateOrCreate WHERE, so the second crawl row UPDATEd the first: a fugitive silently vanished from the dataset"
        );
        $this->assertSame(
            'Lừa đảo',
            WantedPerson::where('crime', '!=', 'Trộm cắp')->value('crime'),
            'the first person\'s crime was overwritten by the second crawl row'
        );
    }

    public function test_source_key_is_the_php_digest_and_recrawl_updates_in_place(): void
    {
        // Engine-agnostic wiring pin. The digest is computed in PHP over the
        // byte values, so its exact form is part of the contract: changing
        // the encoding must be an intentional, backfill-aware decision.
        // One fake with a sequence: a SECOND Http::fake call merges after
        // the first, and the dispatch loop keeps the FIRST callback that
        // answers the URL, so two fakeTable() calls in one test would feed
        // the same old HTML to both crawls.
        Http::fake([
            'truyna.bocongan.gov.vn/*' => Http::sequence()
                ->push($this->tableHtml('<tr><td>1</td><td>Nguyễn Văn An</td><td>1990</td><td>Hà Nội</td><td>Văn A</td><td>Lừa đảo</td><td>QD-91</td><td>C03</td></tr>'), 200)
                ->push($this->tableHtml('<tr><td>1</td><td>Nguyễn Văn An</td><td>1990</td><td>Hà Nội</td><td>Văn AA</td><td>Lừa đảo</td><td>QD-91</td><td>C03</td></tr>'), 200),
        ]);
        $this->artisan('crawl:wanted-list')->assertSuccessful();

        $person = WantedPerson::sole();
        $this->assertSame(
            sha1(json_encode(['Nguyễn Văn An', '1990', 'Hà Nội', 'QD-91'])),
            $person->source_key,
            'source_key must be the byte-exact sha1 digest of the four lookup fields as stored'
        );

        // Second run over HTTP, same key, changed parents -> one row, updated
        // (recrawl idempotency preserved).
        $this->artisan('crawl:wanted-list')->assertSuccessful();

        $this->assertSame(1, WantedPerson::count());
        $this->assertSame('Văn AA', WantedPerson::sole()->parents);
    }

    public function test_digest_is_computed_over_the_truncated_values(): void
    {
        // #106 truncates cells to 255 chars before storage. The digest MUST
        // be derived from what is stored, or a re-crawl with a differently
        // long over-length cell would miss the row and insert a duplicate.
        $long = str_repeat('á', 300);
        $this->fakeTable('
            <tr><td>1</td><td>'.$long.'</td><td>1990</td><td>Hà Nội</td><td>Văn A</td><td>Lừa đảo</td><td>QD-91</td><td>C03</td></tr>
        ');
        $this->artisan('crawl:wanted-list')->assertSuccessful();

        $person = WantedPerson::sole();
        $this->assertSame(
            sha1(json_encode([mb_substr($long, 0, 255), '1990', 'Hà Nội', 'QD-91'])),
            $person->source_key,
            'digest must key off the stored (truncated) values, not the raw DOM text'
        );
    }

    public function test_source_key_is_unique_at_the_database_level(): void
    {
        $unique = collect(Schema::getIndexes('wanted_people'))
            ->first(fn ($i) => $i['columns'] === ['source_key']);

        $this->assertNotNull($unique, 'no index on source_key at all');
        $this->assertTrue(
            (bool) $unique['unique'],
            'source_key is indexed but not UNIQUE: the digest does not constrain anything'
        );
    }

    public function test_existing_rows_are_backfilled_with_distinct_digests(): void
    {
        $this->mysql();

        // Simulate pre-migration rows (created before this fix shipped): two
        // records whose key fields differ only by a diacritic — exactly the
        // byte-distinct, unicode_ci-equal state a hand-seeded or imported
        // table can hold. The backfill must give them DISTINCT digests; the
        // UNIQUE index must accept both; and a later crawl of the second
        // person must not overwrite the first.
        DB::table('wanted_people')->insert([
            ['name' => 'Lê Văn Bình', 'birth_year' => '1988', 'address' => 'Hải Phòng', 'decision' => 'QD-10', 'crime' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Lê Văn BÌnh', 'birth_year' => '1988', 'address' => 'Hải Phòng', 'decision' => 'QD-10', 'crime' => 'B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $filled = WantedPerson::backfillMissingSourceKeys();
        $this->assertSame(2, $filled);

        $keys = WantedPerson::pluck('source_key')->all();
        $this->assertCount(2, array_unique($keys), 'backfill collapsed distinct rows: digest not byte-exact');

        // The decisive production-simulation half: crawl 'BÌnh' again — it
        // must update ONLY the B row (via its digest), leaving A untouched.
        $this->fakeTable('
            <tr><td>1</td><td>Lê Văn BÌnh</td><td>1988</td><td>Hải Phòng</td><td>Văn X</td><td>Cờ bạc</td><td>QD-10</td><td>C03</td></tr>
        ');
        $this->artisan('crawl:wanted-list')->assertSuccessful();

        $this->assertSame(2, WantedPerson::count());
        // Byte-exact row selectors: a plain where('name', ...) would itself
        // fold under unicode_ci and match both rows — the assertion must not
        // inherit the very bug it is catching.
        $aRow = DB::table('wanted_people')->whereRaw('name collate utf8mb4_bin = ?', ['Lê Văn Bình'])->sole();
        $bRow = DB::table('wanted_people')->whereRaw('name collate utf8mb4_bin = ?', ['Lê Văn BÌnh'])->sole();
        $this->assertSame('A', $aRow->crime, 'the untouched first person was clobbered — lookup still went through the collated fields');
        $this->assertSame('Cờ bạc', $bRow->crime, 'the second person did not update its own row');
    }
}
