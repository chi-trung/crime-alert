<?php

namespace Tests\Unit;

use App\Support\UploadedImage;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class UploadedImageBufferTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_failed_encoder_closes_its_output_buffer(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 2, 2);
        // Isolate the namespaced encoder double from every other test.
        eval('namespace App\\Support; function imagepng($image) { echo "partial image"; return false; }');

        $process = new \ReflectionMethod(UploadedImage::class, 'process');
        $level = ob_get_level();
        ob_start();
        echo 'caller output';

        try {
            $result = $process->invoke(null, $file);
            $this->assertNull($result);
            $this->assertSame($level + 1, ob_get_level(), 'The failed encoder leaked its output buffer.');
            $this->assertSame('caller output', ob_get_contents());
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
