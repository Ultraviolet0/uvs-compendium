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
 */
final class ImageProcessor
{
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
    ) {
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
        if ($width * $height > $this->maxSourcePixels || $width > 12000 || $height > 12000) {
            throw new ValidationException(['image' => 'That image has too many pixels. Resize it below ' . number_format($this->maxSourcePixels / 1_000_000) . ' megapixels.']);
        }
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
        };
        if (!$source instanceof GdImage) {
            throw new ValidationException(['image' => 'The image could not be decoded.']);
        }
        if ($mime === 'image/jpeg') {
            $source = self::applyOrientation($source, $path);
        }
        $canvas = $purpose === 'avatar'
            ? self::squareCrop($source, $maxDimension)
            : self::fit($source, $maxDimension);
        imagedestroy($source);

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

    private static function applyOrientation(GdImage $image, string $path): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
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
