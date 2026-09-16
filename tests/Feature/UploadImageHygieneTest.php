<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Support\UploadedImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Issue #323: the axis audit's two CONFIRMED upload findings — (1) EXIF
 * (incl. phone GPS) survived upload -> public disk -> re-serving to every
 * visitor including guests on experience pages, and (2) byte-only max:2048
 * with no dimension cap, so a <2MB 20-megapixel image bomb planted once was
 * fully decoded by every subsequent page visitor's browser.
 *
 * The fixes: UploadedImage::store() GD re-encodes every upload (GD never
 * emits APP1/EXIF — stripping is a byproduct of reconstruction), and the
 * three rules gained dimensions:max_width=4096,max_height=4096 (NAMED form
 * only; positional is a vendor-parsed no-op). These tests prove:
 * - the marker: forged `"Exif\0\0"` inside a real JPEG is gone from the
 *   stored file on all three HTTP legs (alert store, alert update, avatar)
 *   while the file still decodes at the original dimensions;
 * - a 5000x4000 upload is rejected by the rule BEFORE any write (disk
 *   stays empty — the pre-fix behavior was store + serve);
 * - PNG alpha survives (the re-encode must not be a flattening);
 * - a stacked multi-GIF byte stream collapses to the FIRST frame only —
 *   the documented animation tradeoff, asserted by pixel color;
 * - a file GD cannot decode falls back to a verbatim store (byte-identical
 *   copy, no 500) — the honest pre-fix residual;
 * - the #281 false contract is untouched: UploadFalseReturnTest (which
 *   doubles putFileAs to false) still passes through the helper unchanged.
 *
 * Byte-scan assertions (not exif_read_data) per the probe finding that CI
 * does not load the optional exif extension (setup-php lists gd only).
 */
class UploadImageHygieneTest extends TestCase
{
    use RefreshDatabase;

    /** Real JPEG carrying a forged APP1/Exif segment (what a phone writes). */
    private function jpegWithExifMarker(int $w, int $h): string
    {
        $im = imagecreatetruecolor($w, $h);
        $red = imagecolorallocate($im, 200, 30, 30);
        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $red);
        ob_start();
        imagejpeg($im, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($im);

        // Splice an APP1 segment right after SOI (FFD8): payload is the
        // canonical "Exif\0\0" header; contents are junk — the scan asserts
        // the segment is GONE, and re-alignment/validity is proven by GD
        // decoding the result at full size.
        $payload = "Exif\0\0".str_repeat("\x00", 32);
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    /**
     * PNG with a half-transparent red square — corner alpha is the exact
     * value the re-encode must preserve.
     */
    private function pngWithAlpha(): string
    {
        $im = imagecreatetruecolor(32, 32);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $red = imagecolorallocatealpha($im, 255, 0, 0, 60);
        imagefilledrectangle($im, 0, 0, 31, 31, $red);
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /**
     * Two stacked valid single-frame GIFs: file A blue, file B red. A real
     * multi-frame GIF is one logical stream; this is the same property GD
     * resolves — imagecreatefromstring reads the first image and stops. If
     * the stored decode yields blue, only frame A survived.
     */
    private function stackedGif(): string
    {
        $gifs = '';

        foreach ([[0, 0, 255], [255, 0, 0]] as $rgb) {
            $im = imagecreatetruecolor(16, 16);
            $c = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
            imagefilledrectangle($im, 0, 0, 15, 15, $c);
            ob_start();
            imagegif($im);
            $gifs .= (string) ob_get_clean();
            imagedestroy($im);
        }

        return $gifs;
    }

    /** Legal PNG header declaring 64x64, IDAT truncated -> GD can't decode. */
    private function undecodablePng(): string
    {
        $im = imagecreatetruecolor(64, 64);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return substr($png, 0, 50);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function alertPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Ke gian tranh tien',
            'description' => 'Mo ta canh bao',
            'confirmCheckbox' => '1',
        ], $overrides);
    }

    public function test_forged_exif_marker_on_an_alert_upload_is_gone_from_the_stored_file(): void
    {
        Storage::fake('public');
        $user = $this->verifiedUser();

        $bytes = $this->jpegWithExifMarker(64, 48);
        $this->assertStringContainsString("Exif\0\0", $bytes, 'the fixture must carry the marker');

        $this->actingAs($user)
            ->post('/alerts', $this->alertPayload([
                'image' => UploadedFile::fake()->createWithContent('evidence.jpg', $bytes),
            ]))
            ->assertSessionHasNoErrors();

        $path = DB::table('alerts')->value('image');
        $this->assertIsString($path);
        $stored = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString("Exif\0\0", $stored, 'stored bytes must be EXIF-free');
        // Pre-fix the stored file WAS $bytes; prove reconstruction, not
        // truncation: it must still decode at the original dimensions.
        $this->assertSame([64, 48], [
            (int) getimagesizefromstring($stored)[0],
            (int) getimagesizefromstring($stored)[1],
        ]);
    }

    public function test_the_update_site_replaces_the_image_and_strips_too(): void
    {
        Storage::fake('public');
        $user = $this->verifiedUser();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'A',
            'description' => 'd',
            'status' => 'approved',
            'image' => 'alerts/original.png',
        ]);

        $this->actingAs($user)
            ->put('/alerts/'.$alert->id, [
                'title' => 'A',
                'description' => 'd',
                'confirmCheckbox' => '1',
                'image' => UploadedFile::fake()->createWithContent(
                    'edit.jpg', $this->jpegWithExifMarker(64, 48)
                ),
            ])
            ->assertSessionHasNoErrors();

        $path = DB::table('alerts')->where('id', $alert->id)->value('image');
        $stored = Storage::disk('public')->get($path);
        $this->assertStringNotContainsString("Exif\0\0", $stored);
    }

    public function test_the_avatar_leg_strips_the_marker_too(): void
    {
        Storage::fake('public');
        $user = $this->verifiedUser();

        // The form posts no avatar input; this leg is a crafted multipart
        // request (same shape the audit noted) — auth'd + validated route.
        $this->actingAs($user)
            ->post('/experiences', [
                'title' => 'E',
                'content' => 'c',
                'name' => 'N',
                'avatar' => UploadedFile::fake()->createWithContent(
                    'face.jpg', $this->jpegWithExifMarker(64, 48)
                ),
            ])
            ->assertSessionHasNoErrors();

        $avatar = DB::table('experiences')->value('avatar');
        $this->assertIsString($avatar);
        $stored = Storage::disk('public')->get($avatar);
        $this->assertStringNotContainsString("Exif\0\0", $stored);
        $this->assertGreaterThanOrEqual(1, DB::table('experiences')->count());
    }

    public function test_a_decompression_bomb_sized_upload_never_reaches_the_disk(): void
    {
        Storage::fake('public');
        $user = $this->verifiedUser();

        // 5000x4000 PNG from the fake factory is far under 2048 kB (uniform
        // color compresses) — pre-fix this sailed through max:2048 and was
        // served to every browser visiting /alerts. The dimensions rule must
        // reject it BEFORE the controller stores anything.
        $this->actingAs($user)
            ->post('/alerts', $this->alertPayload([
                'image' => UploadedFile::fake()->image('bomb.png', 5000, 4000),
            ]))
            ->assertSessionHasErrors('image');

        $this->assertSame(0, DB::table('alerts')->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_dimension_rule_is_enforced_in_its_named_form_only(): void
    {
        // Guards the doctrine this unit discovered: positional
        // dimensions:,,,,4096,4096 parses to junk keys and passes a 5000-wide
        // image silently. Assert the rule strings in the shipped controllers
        // use the named max_width=/max_height= form.
        $sources = [
            app_path('Http/Controllers/AlertController.php'),
            app_path('Http/Controllers/ExperienceController.php'),
        ];

        foreach ($sources as $source) {
            $this->assertMatchesRegularExpression(
                '/dimensions:max_width=4096,max_height=4096/',
                (string) file_get_contents($source),
                $source.' must use the named dimensions parameters'
            );
        }
    }

    public function test_png_transparency_survives_the_reencode(): void
    {
        Storage::fake('public');
        $user = $this->verifiedUser();

        $this->actingAs($user)
            ->post('/alerts', $this->alertPayload([
                'image' => UploadedFile::fake()->createWithContent('logo.png', $this->pngWithAlpha()),
            ]))
            ->assertSessionHasNoErrors();

        $path = DB::table('alerts')->value('image');
        $stored = Storage::disk('public')->get($path);

        $im = imagecreatefromstring($stored);
        $this->assertNotFalse($im);
        $color = imagecolorat($im, 0, 0);
        // Alpha 60 in, alpha 60 out: the re-encode must preserve, not
        // flatten, the transparency channel (GD stores it as 0-127).
        $this->assertSame(60, $color >> 24, 'PNG alpha channel must survive');
        imagedestroy($im);
    }

    public function test_a_stacked_multiframe_gif_collapses_to_the_first_frame(): void
    {
        Storage::fake('public');
        $user = $this->verifiedUser();

        $this->actingAs($user)
            ->post('/alerts', $this->alertPayload([
                'image' => UploadedFile::fake()->createWithContent('anim.gif', $this->stackedGif()),
            ]))
            ->assertSessionHasNoErrors();

        $path = DB::table('alerts')->value('image');
        $stored = Storage::disk('public')->get($path);

        // Single valid GIF at the first frame's size, first frame's color.
        $im = @imagecreatefromstring($stored);
        $this->assertNotFalse($im, 'stored GIF must remain a decodable image');
        $this->assertSame([16, 16], [
            (int) getimagesizefromstring($stored)[0],
            (int) getimagesizefromstring($stored)[1],
        ]);
        $rgb = imagecolorsforindex($im, (int) imagecolorat($im, 0, 0));
        // GD's GIF encoder quantizes 0,0,255 to the palette (0,0,252) — the
        // discriminator is blue-dominant (frame one) vs red-dominant
        // (frame two), not the exact byte.
        $this->assertGreaterThan($rgb['red'] + $rgb['green'], $rgb['blue'], 'only frame one (blue) survives the decode');
        imagedestroy($im);
    }

    public function test_an_image_gd_cannot_decode_falls_back_to_a_verbatim_store(): void
    {
        // The documented residual: getimagesize reads the header (so the
        // rules pass) but GD decode fails. Pre-fix behavior (verbatim bytes)
        // must hold — never a 500, never a mangled rewrite.
        Storage::fake('public');
        $user = $this->verifiedUser();

        $bytes = $this->undecodablePng();

        $this->actingAs($user)
            ->post('/alerts', $this->alertPayload([
                'image' => UploadedFile::fake()->createWithContent('broken.png', $bytes),
            ]))
            ->assertSessionHasNoErrors();

        $path = DB::table('alerts')->value('image');
        $this->assertSame($bytes, Storage::disk('public')->get($path));
    }

    public function test_the_helper_itself_preserves_the_string_or_false_contract(): void
    {
        // The helper must return the stored path on success, exactly as
        // UploadedFile::store would; UploadFalseReturnTest pins the false
        // branch through the same putFileAs (the #281 disk double).
        Storage::fake('public');

        $file = UploadedFile::fake()->image('plain.png');
        $path = UploadedImage::store($file, 'alerts', 'public');

        $this->assertIsString($path);
        $this->assertMatchesRegularExpression('#^alerts/[A-Za-z0-9]{40}\.(png|jpe?g|gif)#', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }
}
