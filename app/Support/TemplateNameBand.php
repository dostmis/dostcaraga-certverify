<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * A coarse map of where a certificate template already has artwork, so the
 * participant name can be laid out around it.
 *
 * An earlier version reduced this to a single "clear space above the baseline"
 * figure, measured across the full width of the name band. That misread any
 * template with decoration beside the name — the illustration on one side
 * counted as a ceiling and suppressed wrapping entirely, even though the space
 * the name would actually occupy was empty. The map is queried per line box
 * instead, so artwork only matters where the text would really land.
 */
class TemplateNameBand
{
    /** Cell size of the map, in mm. Fine enough for text, cheap to store. */
    private const CELL_MM = 1.0;

    /** Clearance kept around a line box when testing it against the artwork. */
    public const CLEARANCE_MM = 2.0;

    private const RASTER_DPI = 72;

    /** Luminance (0-255) below which a pixel counts as artwork. */
    private const INK_THRESHOLD = 125;

    /**
     * Share of a cell's pixels that must be inked before the cell counts as
     * occupied. Keeps page gradients and faint texture from reading as artwork.
     */
    private const CELL_INK_RATIO = 0.18;

    /**
     * Occupancy map for a template page: a list of row bitmaps, one character
     * per CELL_MM column, '1' where the artwork is. Empty when unmeasurable.
     *
     * @return array{cols: int, rows: int, grid: list<string>}|null
     */
    public static function inkMap(string $templatePath, float $pageWidthMm, float $pageHeightMm): ?array
    {
        if (!is_file($templatePath) || $pageWidthMm <= 0 || $pageHeightMm <= 0) {
            return null;
        }

        $key = 'template-ink-map:' . implode(':', [
            hash_file('xxh128', $templatePath) ?: basename($templatePath),
            round($pageWidthMm, 1),
            round($pageHeightMm, 1),
        ]);

        return Cache::rememberForever(
            $key,
            static fn () => self::build($templatePath, $pageWidthMm, $pageHeightMm)
        );
    }

    /**
     * True when the given rectangle (mm, top-left origin) is free of artwork.
     * An unmeasurable template reports every region clear, so layout falls back
     * to the nominal placement rather than being needlessly shrunk.
     *
     * @param  array{cols: int, rows: int, grid: list<string>}|null  $map
     */
    public static function regionIsClear(
        ?array $map,
        float $xMm,
        float $yMm,
        float $widthMm,
        float $heightMm
    ): bool {
        if ($map === null || $widthMm <= 0 || $heightMm <= 0) {
            return true;
        }

        $x0 = max(0, (int) floor(($xMm - self::CLEARANCE_MM) / self::CELL_MM));
        $x1 = min($map['cols'] - 1, (int) ceil(($xMm + $widthMm + self::CLEARANCE_MM) / self::CELL_MM));
        $y0 = max(0, (int) floor(($yMm - self::CLEARANCE_MM) / self::CELL_MM));
        $y1 = min($map['rows'] - 1, (int) ceil(($yMm + $heightMm + self::CLEARANCE_MM) / self::CELL_MM));

        for ($y = $y0; $y <= $y1; $y++) {
            $row = $map['grid'][$y] ?? null;
            if ($row === null) {
                continue;
            }
            for ($x = $x0; $x <= $x1; $x++) {
                if (($row[$x] ?? '0') === '1') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array{cols: int, rows: int, grid: list<string>}|null
     */
    private static function build(string $templatePath, float $pageWidthMm, float $pageHeightMm): ?array
    {
        if (!class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick();
            $image->setResolution(self::RASTER_DPI, self::RASTER_DPI);
            $image->readImage($templatePath . '[0]');
            $image->setImageColorspace(\Imagick::COLORSPACE_GRAY);

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            if ($width < 10 || $height < 10) {
                $image->clear();

                return null;
            }

            $pixels = $image->exportImagePixels(0, 0, $width, $height, 'I', \Imagick::PIXEL_CHAR);
            $image->clear();

            $cols = max(1, (int) ceil($pageWidthMm / self::CELL_MM));
            $rows = max(1, (int) ceil($pageHeightMm / self::CELL_MM));
            $pxPerCellX = $width / $cols;
            $pxPerCellY = $height / $rows;

            $grid = [];
            for ($cy = 0; $cy < $rows; $cy++) {
                $yStart = (int) floor($cy * $pxPerCellY);
                $yEnd = min($height, max($yStart + 1, (int) ceil(($cy + 1) * $pxPerCellY)));
                $row = str_repeat('0', $cols);

                for ($cx = 0; $cx < $cols; $cx++) {
                    $xStart = (int) floor($cx * $pxPerCellX);
                    $xEnd = min($width, max($xStart + 1, (int) ceil(($cx + 1) * $pxPerCellX)));

                    $total = 0;
                    $ink = 0;
                    for ($y = $yStart; $y < $yEnd; $y++) {
                        $base = $y * $width;
                        for ($x = $xStart; $x < $xEnd; $x++) {
                            $total++;
                            if ($pixels[$base + $x] < self::INK_THRESHOLD) {
                                $ink++;
                            }
                        }
                    }

                    if ($total > 0 && ($ink / $total) >= self::CELL_INK_RATIO) {
                        $row[$cx] = '1';
                    }
                }

                $grid[] = $row;
            }

            return ['cols' => $cols, 'rows' => $rows, 'grid' => $grid];
        } catch (\Throwable $e) {
            Log::warning('Could not map the template artwork; the name will use its nominal placement.', [
                'template' => basename($templatePath),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
