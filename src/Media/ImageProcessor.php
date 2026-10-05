<?php

declare(strict_types=1);

namespace Uvs\Media;

use GdImage;
use Uvs\Support\ValidationException;

/**
 * Treats every upload as untrusted: the real content type is sniffed, pixel
 * dimensions are capped before decoding, the image is fully decoded and drawn
 * onto a fresh canvas, and only that canvas is re-encoded. Metadata (EXIF, ICC,
 * comments) and any appended or polyglot payload cannot survive this process.
 *
 * Decoding is the expensive step on shared hosting: GD holds every source pixel
 * as a 4-byte truecolour value, and the PNG and WebP decoders briefly keep a
 * second full-size buffer while doing so. The pixel ceiling is therefore the
 * lower of the configured maximum and what the remaining PHP memory budget can
 * hold, so an upload is refused politely instead of exhausting memory. EXIF
 * rotation is applied to the small resized canvas, never to the full source.
 */
final class ImageProcessor
{
    /** Absolute edge limit, whatever the pixel budget. */
    public const MAX_EDGE = 8192;

    /** Conservative peak bytes per source pixel while decoding (pixel data plus decoder buffers and row overhead). */
    private const BYTES_PER_PIXEL = [
        'image/jpeg' => 5,
        'image/png' => 9,
        'image/webp' => 9,
        'image/gif' => 6,
    ];

    /** Memory kept free for the framework, the request, and the encoder. */
    private const HEADROOM = 16 * 1024 * 1024;

    public const ACCEPTED = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
        'image/gif' => IMAGETYPE_GIF,
    ];

    public function __construct(
        private readonly int $maxSourceBytes,
        private readonly int $maxProcessedBytes,
        private readonly int $maxSourcePixels,
        private readonly ?int $memoryLimit = null,
        private readonly ?int $memoryInUse = null,
    ) {
    }

    /**
     * The largest source image, in pixels, that can be decoded safely for the
     * given type and output size: the configured ceiling, lowered further when
     * the PHP memory limit could not hold the decoded image.
     */
    public function pixelLimit(string $mime, int $maxDimension): int
    {
        $limit = min($this->maxSourcePixels, self::MAX_EDGE * self::MAX_EDGE);
        $memory = $this->memoryLimit ?? self::parseBytes((string) ini_get('memory_limit'));
        if ($memory <= 0) {
            return $limit; // -1: no PHP memory limit; the configured ceiling applies.
        }
        $inUse = $this->memoryInUse ?? memory_get_usage(true);
        // The resized canvas and its rotated copy, the encoder output, and general headroom.
        $reserved = 2 * $maxDimension * $maxDimension * 4 + 2 * $this->maxProcessedBytes + self::HEADROOM;
        $available = $memory - $inUse - $reserved;
        $perPixel = self::BYTES_PER_PIXEL[$mime] ?? max(self::BYTES_PER_PIXEL);
        return max(0, min($limit, intdiv(max(0, $available), $perPixel)));
    }

    /** Parses a php.ini byte value such as "128M", "1G", or "-1". */
    public static function parseBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * @param 'guide'|'avatar' $purpose
     * @return array{data: string, mime: string, extension: string, width: int, height: int}
     * @throws ValidationException
     */
    public function process(string $path, string $purpose, int $maxDimension): array
    {
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            throw new ValidationException(['image' => 'The uploaded file was empty.']);
        }
        if ($size > $this->maxSourceBytes) {
            throw new ValidationException(['image' => 'That image is too large. The limit is ' . self::megabytes($this->maxSourceBytes) . '.']);
        }
        $head = (string) file_get_contents($path, false, null, 0, 1024);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if ($mime === 'image/svg+xml' || stripos($head, '<svg') !== false || stripos($head, '<?xml') !== false) {
            throw new ValidationException(['image' => 'SVG images are not accepted. Upload a JPEG, PNG, WebP, or GIF.']);
        }
        if (!isset(self::ACCEPTED[$mime])) {
            throw new ValidationException(['image' => 'That file is not a supported image. Upload a JPEG, PNG, WebP, or GIF.']);
        }
        $info = @getimagesize($path);
        if ($info === false || $info[2] !== self::ACCEPTED[$mime] || $info[0] < 1 || $info[1] < 1) {
            throw new ValidationException(['image' => 'The image could not be read. It may be damaged or mislabeled.']);
        }
        [$width, $height] = $info;
        $pixelLimit = $this->pixelLimit($mime, $maxDimension);
        if ($width * $height > $pixelLimit || $width > self::MAX_EDGE || $height > self::MAX_EDGE) {
            $megapixels = rtrim(rtrim(number_format($pixelLimit / 1_000_000, 1), '0'), '.');
            throw new ValidationException(['image' => 'That image has too many pixels. Resize it to at most ' . $megapixels
                . ' megapixels and ' . number_format(self::MAX_EDGE) . ' pixels on its longest side.']);
        }
        // Read before decoding so the rotation can be applied to the small canvas.
        $orientation = $mime === 'image/jpeg' ? self::orientation($path) : 1;
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
        };
        if (!$source instanceof GdImage) {
            throw new ValidationException(['image' => 'The image could not be decoded.']);
        }
        // Fitting and centre-cropping are symmetric under quarter turns, so
        // rotating afterwards gives the same result at a fraction of the memory.
        $canvas = $purpose === 'avatar'
            ? self::squareCrop($source, $maxDimension)
            : self::fit($source, $maxDimension);
        imagedestroy($source);
        $canvas = self::applyOrientation($canvas, $orientation);

        $encoded = $this->encode($canvas);
        $result = [
            'data' => $encoded['data'],
            'mime' => $encoded['mime'],
            'extension' => $encoded['extension'],
            'width' => imagesx($canvas),
            'height' => imagesy($canvas),
        ];
        imagedestroy($canvas);
        return $result;
    }

    /**
     * @return array{data: string, mime: string, extension: string}
     */
    private function encode(GdImage $image): array
    {
        $webp = function_exists('imagewebp');
        foreach ([82, 72, 60, 48] as $quality) {
            ob_start();
            if ($webp) {
                imagewebp($image, null, $quality);
            } else {
                imagejpeg($image, null, $quality);
            }
            $data = (string) ob_get_clean();
            if ($data !== '' && strlen($data) <= $this->maxProcessedBytes) {
                return $webp
                    ? ['data' => $data, 'mime' => 'image/webp', 'extension' => 'webp']
                    : ['data' => $data, 'mime' => 'image/jpeg', 'extension' => 'jpg'];
            }
        }
        throw new ValidationException(['image' => 'That image is still too large after compression. Try a smaller or simpler image.']);
    }

    private static function blankCanvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, (int) $transparent);
        imagealphablending($canvas, true);
        if (!function_exists('imagewebp')) {
            // JPEG has no alpha channel; flatten onto the site's dark surface.
            imagefilledrectangle($canvas, 0, 0, $width, $height, (int) imagecolorallocate($canvas, 17, 17, 17));
        }
        return $canvas;
    }

    private static function fit(GdImage $source, int $max): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $max / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = self::blankCanvas($targetWidth, $targetHeight);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagealphablending($canvas, false);
        return $canvas;
    }

    private static function squareCrop(GdImage $source, int $size): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = min($size, $side);
        $canvas = self::blankCanvas($target, $target);
        imagecopyresampled($canvas, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $target, $target, $side, $side);
        imagealphablending($canvas, false);
        return $canvas;
    }

    private static function orientation(string $path): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($path);
        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    private static function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };
        if ($rotated instanceof GdImage) {
            imagedestroy($image);
            return $rotated;
        }
        return $image;
    }

    public static function megabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1), '0'), '.') . ' MB';
    }
}
