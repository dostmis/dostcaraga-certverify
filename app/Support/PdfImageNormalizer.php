<?php

namespace App\Support;

use Illuminate\Support\Str;

class PdfImageNormalizer
{
    public static function prepareForFpdf(string $sourceAbs): string
    {
        $imageInfo = @getimagesize($sourceAbs);
        if (!$imageInfo) {
            return $sourceAbs;
        }

        $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
        if ($mime !== 'image/png') {
            return $sourceAbs;
        }

        $sourceImage = @imagecreatefrompng($sourceAbs);
        if (!$sourceImage) {
            return $sourceAbs;
        }

        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $flattenedImage = imagecreatetruecolor($width, $height);
        if (!$flattenedImage) {
            imagedestroy($sourceImage);
            return $sourceAbs;
        }

        $white = imagecolorallocate($flattenedImage, 255, 255, 255);
        imagefilledrectangle($flattenedImage, 0, 0, $width, $height, $white);
        imagealphablending($flattenedImage, true);
        imagecopy($flattenedImage, $sourceImage, 0, 0, 0, 0, $width, $height);

        $tmpDir = storage_path('app/tmp');
        @mkdir($tmpDir, 0777, true);
        $normalizedAbs = $tmpDir . '/fpdf-image-' . Str::uuid() . '.png';

        $saved = @imagepng($flattenedImage, $normalizedAbs);

        imagedestroy($flattenedImage);
        imagedestroy($sourceImage);

        if (!$saved || !is_file($normalizedAbs)) {
            return $sourceAbs;
        }

        return $normalizedAbs;
    }
}
