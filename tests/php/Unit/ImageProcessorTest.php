<?php

declare(strict_types=1);

namespace Uvs\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uvs\Media\ImageProcessor;
use Uvs\Support\ValidationException;

final class ImageProcessorTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'uvs-img-');
        file_put_contents($path, $contents);
        $this->files[] = $path;
        return $path;
    }

    private function png(int $width, int $height, bool $alpha = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        if ($alpha) {
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 200, 100, 0, 80));
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 120, 60, 20));
        }
        ob_start();
        imagepng($image);
        return (string) ob_get_clean();
    }

    private function jpegWithMetadata(): string
    {
        $image = imagecreatetruecolor(320, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 160));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        // Insert an APP1/EXIF-style segment carrying a private marker right after SOI.
        $payload = "Exif\0\0GPS-SECRET-LOCATION camera-serial-12345";
        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
    }

    private function processor(int $maxSource = 5_000_000): ImageProcessor
    {
        return new ImageProcessor($maxSource, 2_000_000, 40_000_000);
    }

    public function testValidImageIsReencodedAndResized(): void
    {
        $result = $this->processor()->process($this->file($this->png(3000, 1500)), 'guide', 1600);
        self::assertSame(1600, $result['width']);
        self::assertSame(800, $result['height']);
        self::assertContains($result['mime'], ['image/webp', 'image/jpeg']);
        self::assertNotFalse(imagecreatefromstring($result['data']));
    }

    public function testAvatarsAreSquareCropped(): void
    {
        $result = $this->processor()->process($this->file($this->png(900, 400, true)), 'avatar', 256);
        self::assertSame(256, $result['width']);
        self::assertSame(256, $result['height']);
    }

    public function testMetadataIsStripped(): void
    {
        $source = $this->jpegWithMetadata();
        self::assertStringContainsString('GPS-SECRET', $source);
        $result = $this->processor()->process($this->file($source), 'guide', 1600);
        self::assertStringNotContainsString('GPS-SECRET', $result['data']);
        self::assertStringNotContainsString('camera-serial', $result['data']);
    }

    public function testPolyglotPayloadDoesNotSurvive(): void
    {
        $result = $this->processor()->process($this->file($this->png(64, 64) . '<?php system($_GET["c"]); ?><script>alert(1)</script>'), 'guide', 1600);
        self::assertStringNotContainsString('<?php', $result['data']);
        self::assertStringNotContainsString('<script', $result['data']);
    }

    public function testSvgIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SVG');
        $this->processor()->process($this->file('<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10"/></svg>'), 'guide', 1600);
    }

    public function testNonImageIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->processor()->process($this->file("<?php echo 'not an image';"), 'guide', 1600);
    }

    public function testTruncatedImageIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->processor()->process($this->file(substr($this->png(400, 400), 0, 60)), 'guide', 1600);
    }

    public function testOversizedSourceIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('too large');
        $this->processor(1000)->process($this->file($this->png(500, 500)), 'guide', 1600);
    }

    public function testDecompressionBombDimensionsAreRejectedBeforeDecoding(): void
    {
        // A PNG header that claims 30000x30000 pixels.
        $ihdr = pack('NNCCCCC', 30000, 30000, 8, 2, 0, 0, 0);
        $chunk = pack('N', strlen($ihdr)) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
        $png = "\x89PNG\r\n\x1a\n" . $chunk . pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('pixels');
        $this->processor()->process($this->file($png), 'guide', 1600);
    }

    public function testConfiguredLowerCeilingIsEnforced(): void
    {
        $processor = new ImageProcessor(5_000_000, 2_000_000, 100_000, -1);
        self::assertSame(100_000, $processor->pixelLimit('image/png', 1600));
        $processor->process($this->file($this->png(300, 300)), 'guide', 1600); // 90 000 pixels: accepted
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('0.1 megapixels');
        $processor->process($this->file($this->png(400, 300)), 'guide', 1600); // 120 000 pixels: refused
    }

    public function testEdgeLimitAppliesEvenWithinThePixelBudget(): void
    {
        // 9000 x 2 pixels is tiny in area but longer than any accepted edge.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('8,192 pixels');
        (new ImageProcessor(5_000_000, 2_000_000, 40_000_000, -1))->process($this->file($this->png(9000, 2)), 'guide', 1600);
    }

    public function testPixelLimitIsDerivedFromTheMemoryBudget(): void
    {
        $mb = 1024 * 1024;
        // 128 MB limit, 8 MB in use; reserved = 2 x 1600^2 x 4 + 2 x 2 MB + 16 MB.
        $processor = new ImageProcessor(10 * $mb, 2 * $mb, 100_000_000, 128 * $mb, 8 * $mb);
        $available = 128 * $mb - 8 * $mb - (2 * 1600 * 1600 * 4 + 4 * $mb + 16 * $mb);
        self::assertSame(intdiv($available, 5), $processor->pixelLimit('image/jpeg', 1600));
        self::assertSame(intdiv($available, 9), $processor->pixelLimit('image/png', 1600));
        self::assertSame(intdiv($available, 9), $processor->pixelLimit('image/webp', 1600));
        self::assertLessThan($processor->pixelLimit('image/jpeg', 1600), $processor->pixelLimit('image/png', 1600));
        // A typical 12 MP phone photo still fits under 128 MB; a 20 MP PNG does not.
        self::assertGreaterThan(4032 * 3024, $processor->pixelLimit('image/jpeg', 1600));
        self::assertLessThan(20_000_000, $processor->pixelLimit('image/png', 1600));
        // The configured ceiling still wins when memory is plentiful, and the edge cap bounds an unlimited host.
        self::assertSame(16_000_000, (new ImageProcessor(1, 1, 16_000_000, 4096 * $mb, 0))->pixelLimit('image/png', 1600));
        self::assertSame(8192 * 8192, (new ImageProcessor(1, 1, PHP_INT_MAX, -1))->pixelLimit('image/png', 1600));
        // An exhausted budget refuses everything rather than going negative.
        self::assertSame(0, (new ImageProcessor(1, 1, 16_000_000, 32 * $mb, 30 * $mb))->pixelLimit('image/jpeg', 1600));
    }

    public function testLowMemoryHostRefusesBeforeDecoding(): void
    {
        $mb = 1024 * 1024;
        // A 64 MB host with 20 MB already in use cannot decode a 2000 x 2000 PNG safely.
        $processor = new ImageProcessor(10 * $mb, 2 * $mb, 16_000_000, 64 * $mb, 20 * $mb);
        self::assertLessThan(2000 * 2000, $processor->pixelLimit('image/png', 1600));
        $path = $this->file($this->png(2000, 2000));
        $before = memory_get_peak_usage();
        try {
            $processor->process($path, 'guide', 1600);
            self::fail('The image should have been refused.');
        } catch (ValidationException $error) {
            self::assertStringContainsString('too many pixels', $error->errors['image']);
        }
        self::assertLessThan($before + 4 * $mb, memory_get_peak_usage(), 'refused before decoding');
    }

    public function testMemoryLimitStringsAreParsed(): void
    {
        self::assertSame(-1, ImageProcessor::parseBytes('-1'));
        self::assertSame(128 * 1024 * 1024, ImageProcessor::parseBytes('128M'));
        self::assertSame(1024 * 1024 * 1024, ImageProcessor::parseBytes('1G'));
        self::assertSame(512 * 1024, ImageProcessor::parseBytes('512k'));
        self::assertSame(134217728, ImageProcessor::parseBytes('134217728'));
    }

    public function testRotatedJpegIsOrientedAfterResizing(): void
    {
        if (!function_exists('exif_read_data')) {
            self::markTestSkipped('exif extension not available');
        }
        $image = imagecreatetruecolor(2000, 1000);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 120, 200));
        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();
        // Minimal big-endian EXIF APP1 segment with Orientation = 6 (rotate 90 degrees clockwise).
        $tiff = 'MM' . pack('n', 42) . pack('N', 8) . pack('n', 1) . pack('nnNnn', 0x0112, 3, 1, 6, 0) . pack('N', 0);
        $app1 = "Exif\0\0" . $tiff;
        $jpeg = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
        $result = $this->processor()->process($this->file($jpeg), 'guide', 1600);
        self::assertSame(800, $result['width']);
        self::assertSame(1600, $result['height']);
    }
}
