<?php

namespace App\Domain\Media;

use Carbon\Carbon;
use Throwable;

class ExifExtractor
{
    /**
     * Extract safe field-capture hints (capture date & GPS coordinates) from an uploaded image file.
     *
     * @return array{captured_at: ?string, latitude: ?float, longitude: ?float, camera: ?string, orientation: ?int}
     */
    public function extract(string $absolutePath): array
    {
        $empty = [
            'captured_at' => null,
            'latitude' => null,
            'longitude' => null,
            'camera' => null,
            'orientation' => null,
        ];

        if (! function_exists('exif_read_data') || ! is_file($absolutePath)) {
            return $empty;
        }

        try {
            $exif = @exif_read_data($absolutePath, 'ANY_TAG', true);
            if (! is_array($exif)) {
                return $empty;
            }

            $capturedAt = null;
            $rawDate = $exif['EXIF']['DateTimeOriginal']
                ?? $exif['EXIF']['DateTimeDigitized']
                ?? $exif['IFD0']['DateTime']
                ?? null;

            if (is_string($rawDate) && trim($rawDate) !== '') {
                $normalized = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', trim($rawDate));
                try {
                    $capturedAt = Carbon::parse($normalized)->format('Y-m-d');
                } catch (Throwable) {
                    $capturedAt = null;
                }
            }

            $lat = null;
            $lng = null;
            if (isset($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef'], $exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef'])) {
                $lat = $this->dmsToDecimal($exif['GPS']['GPSLatitude'], (string) $exif['GPS']['GPSLatitudeRef']);
                $lng = $this->dmsToDecimal($exif['GPS']['GPSLongitude'], (string) $exif['GPS']['GPSLongitudeRef']);
            }

            $make = trim((string) ($exif['IFD0']['Make'] ?? ''));
            $model = trim((string) ($exif['IFD0']['Model'] ?? ''));
            $camera = trim("{$make} {$model}") ?: null;

            $orientation = isset($exif['IFD0']['Orientation']) ? (int) $exif['IFD0']['Orientation'] : null;

            return [
                'captured_at' => $capturedAt,
                'latitude' => $lat,
                'longitude' => $lng,
                'camera' => $camera,
                'orientation' => $orientation,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * @param  array<int, string|int|float>  $dms
     */
    public function dmsToDecimal(array $dms, string $ref): ?float
    {
        if (count($dms) < 3) {
            return null;
        }

        $deg = $this->rationalToFloat($dms[0]);
        $min = $this->rationalToFloat($dms[1]);
        $sec = $this->rationalToFloat($dms[2]);

        if ($deg === null || $min === null || $sec === null) {
            return null;
        }

        $decimal = $deg + ($min / 60.0) + ($sec / 3600.0);
        if (in_array(strtoupper(trim($ref)), ['S', 'W'], true)) {
            $decimal *= -1;
        }

        return round($decimal, 6);
    }

    private function rationalToFloat(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = array_pad(explode('/', $value, 2), 2, '1');
            $denVal = (float) $den;

            return $denVal != 0.0 ? ((float) $num / $denVal) : null;
        }

        return null;
    }
}
