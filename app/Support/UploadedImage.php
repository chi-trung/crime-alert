<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Issue #323: privacy-preserving image storage for the three upload sites
 * (AlertController::store/update alerts/, ExperienceController::store
 * avatars/).
 *
 * The axis audit confirmed two defects in the raw store() path:
 *
 * 1. EXIF (incl. GPS) survived upload -> public disk -> re-serving. Phone
 *    camera photos carry GPS coordinates; `->store()` is a verbatim byte
 *    copy (putFileAs -> flysystem writeStream), and the files are readable
 *    at unguessable-but-public URLs (/storage/alerts/<hash>.jpg — experience
 *    show pages render for GUESTS, routes/web.php:162). The app itself
 *    treats coordinates as sensitive (#31 validates lat/lng explicitly), yet
 *    the image path leaked them to every visitor.
 * 2. No dimension cap: `max:2048` bounds BYTES only, so a valid <2MB
 *    8000x6000 PNG or multi-frame GIF — a classic client decompression bomb
 *    — rendered (fully decoded) for every visitor of alerts/index, the
 *    dashboard, and public experience pages.
 *
 * The fix is a GD re-encode: `imagecreatefromstring` decodes to raw pixels
 * and the encoder writes a NEW file from those pixels; GD emits no APP1/EXIF
 * segment and no metadata at all, so stripping is a byproduct of the same
 * operation that also collapses animated GIFs to their first frame (a
 * deliberate tradeoff: these are evidence photos and avatars, not stickers,
 * and the alternative — keeping animation — means keeping metadata). The
 * dimension threat is closed at the VALIDATOR instead (dimensions:
 * max_width=4096,max_height=4096 — the NAMED parameter form is mandatory:
 * vendor ValidatesAttributes::parseNamedParameters keys each item on '=',
 * so the positional form 'dimensions:,,,,4096,4096' is a silent no-op;
 * probed both ways), so a huge upload is rejected before any decode ever
 * runs — the helper never has to survive the bomb case, and neither the
 * disk nor a browser does.
 *
 * Contract: return exactly what `UploadedFile::store($dir, $disk)` returns —
 * the stored path string, or false on write failure — so every guard built
 * around that contract (the #281 false-branch form error, the #267/#310
 * throw-sweep, the #255/#310 stale write guard, the #55 fake-able disk via
 * Storage::fake) keeps working from the controllers unchanged. Mechanically
 * that means the re-encoded bytes are handed BACK to store(): written to a
 * temp file, wrapped in a test-mode UploadedFile, stored through the
 * identical code path the controllers used pre-fix. Consequences: the
 * 'string|false' false branch is still produced by the SAME putFileAs the
 * #281 test double overrides, and the unique-name layout is store()'s own
 * (random-40 + extension guessed by flysystem from the RE-ENCODED bytes, so
 * a PNG-in-.jpg lie lands honestly as .png exactly like the raw store()
 * named it pre-fix). Zero disk logic is duplicated here.
 *
 * Honest residuals, documented per doctrine:
 * - A file that PASSES Laravel's getimagesize()-based validation but fails
 *   GD decode/encode (deep corruption, encoder failure) — or a format
 *   outside the gif/png/jpeg whitelist reaching this helper at all — falls
 *   back to a verbatim store: the pre-fix behavior, warts (and any
 *   metadata) included. Never a 500.
 * - Animated GIFs keep only frame one.
 * - The exif extension is optional (CI's setup-php list omits it —
 *   workflows/*.yml), so orientation compensation applies only when it is
 *   loaded. Orientation is the one piece of metadata READ before it is
 *   destroyed: phone cameras store portrait photos rotated inside the JPEG
 *   with an orientation tag telling viewers to turn them back; stripping
 *   the tag without compensating would leave every such photo sideways.
 */
class UploadedImage
{
    /** JPEG re-encode quality — visually lossless for evidence photos. */
    private const JPEG_QUALITY = 85;

    /**
     * Re-encode and store the upload; falls back to the raw store on any
     * processing failure. Same return as UploadedFile::store().
     */
    public static function store(UploadedFile $file, string $directory, string $disk = 'public'): string|false
    {
        $bytes = self::process($file);

        if ($bytes === null) {
            return $file->store($directory, $disk);
        }

        $tmp = @tempnam(sys_get_temp_dir(), 'caimg_');

        if (! is_string($tmp) || @file_put_contents($tmp, $bytes) === false) {
            if (is_string($tmp)) {
                @unlink($tmp);
            }

            return $file->store($directory, $disk);
        }

        try {
            // 5th constructor arg $test=true: skips the is_uploaded_file()
            // move-validation inside isValid() so store() proceeds (probed —
            // without it every write died as 'tải lên thất bại').
            $clean = new UploadedFile($tmp, $file->getClientOriginalName(), null, UPLOAD_ERR_OK, true);

            return $clean->store($directory, $disk);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Decode -> strip (via re-encode) -> return fresh bytes, or null when
     * the image cannot be processed safely (caller stores verbatim).
     */
    private static function process(UploadedFile $file): ?string
    {
        try {
            $bytes = $file->get();
        } catch (\Throwable) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            return null;
        }

        // Only the three formats the mimes: whitelist allows get re-encoded;
        // anything else that somehow reached here is stored verbatim (the
        // caller's fallback), never silently converted to another type.
        $type = (int) $info[2];

        if (! in_array($type, [IMAGETYPE_GIF, IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return null;
        }

        $im = @imagecreatefromstring($bytes);

        if ($im === false) {
            return null;
        }

        $oriented = self::applyOrientation($im, self::orientation($bytes));

        if ($oriented !== $im) {
            imagedestroy($im);
            $im = $oriented;
        }

        imagesavealpha($im, true); // PNG/GIF transparency; ignored by JPEG

        ob_start();

        $ok = match ($type) {
            IMAGETYPE_GIF => @imagegif($im),
            IMAGETYPE_PNG => @imagepng($im),
            IMAGETYPE_JPEG => @imagejpeg($im, null, self::JPEG_QUALITY),
        };

        $encoded = $ok === false ? false : (string) ob_get_clean();

        imagedestroy($im);

        return ($encoded === false || $encoded === '') ? null : $encoded;
    }

    /** EXIF Orientation for a byte string, or 1 when unavailable/unknown. */
    private static function orientation(string $bytes): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes), 'IFD0');

        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return in_array($orientation, [2, 3, 4, 5, 6, 7, 8], true) ? $orientation : 1;
    }

    /** Rotate/flip so the pixels match what a browser would have displayed. */
    private static function applyOrientation(\GdImage $im, int $orientation): \GdImage
    {
        return match ($orientation) {
            2 => self::flipped($im, IMG_FLIP_HORIZONTAL),
            3 => self::rotated($im, 180),
            4 => self::flipped($im, IMG_FLIP_VERTICAL),
            // 6/8 are quarter turns; imagerotate(-90) == 90 deg CLOCKWISE
            // (probed), which is what tag 6 ("rotate 90 CW to display") asks
            // for. 5/7 combine a quarter turn with a horizontal flip.
            5 => self::flipped(self::rotated($im, -90), IMG_FLIP_HORIZONTAL),
            6 => self::rotated($im, -90),
            7 => self::flipped(self::rotated($im, 90), IMG_FLIP_HORIZONTAL),
            8 => self::rotated($im, 90),
            default => $im,
        };
    }

    private static function rotated(\GdImage $im, float $degrees): \GdImage
    {
        return imagerotate($im, $degrees, 0) ?? $im;
    }

    private static function flipped(\GdImage $im, int $mode): \GdImage
    {
        imageflip($im, $mode);

        return $im;
    }
}
