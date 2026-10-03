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
}
