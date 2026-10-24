<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Indonesian labels for the public catalogue. Keys mirror
 * DatasetCurationController::TOPIC_CATEGORIES and ::LICENSES.
 */
class CatalogueLabels
{
    /** ISO 19115 MD_TopicCategoryCode. */
    public const TOPICS = [
        'biota' => 'Biota',
        'boundaries' => 'Batas wilayah',
        'climatologyMeteorologyAtmosphere' => 'Klimatologi, meteorologi, atmosfer',
        'economy' => 'Ekonomi',
        'elevation' => 'Elevasi',
        'environment' => 'Lingkungan',
        'farming' => 'Pertanian',
        'geoscientificInformation' => 'Informasi geosains',
        'health' => 'Kesehatan',
        'imageryBaseMapsEarthCover' => 'Citra, peta dasar, tutupan lahan',
        'inlandWaters' => 'Perairan darat',
        'intelligenceMilitary' => 'Intelijen dan militer',
        'location' => 'Lokasi',
        'oceans' => 'Kelautan',
        'planningCadastre' => 'Perencanaan dan kadaster',
        'society' => 'Sosial kemasyarakatan',
        'structure' => 'Struktur dan bangunan',
        'transportation' => 'Transportasi',
        'utilitiesCommunication' => 'Utilitas dan komunikasi',
    ];

    public const LICENSES = [
        'CC-BY-4.0' => 'CC BY 4.0',
        'CC-BY-SA-4.0' => 'CC BY-SA 4.0',
        'CC0-1.0' => 'CC0 1.0',
        'restricted' => 'Terbatas (restricted)',
        'other' => 'Lainnya',
    ];

    public const FORMATS = [
        'shapefile' => 'Shapefile',
        'geotiff' => 'GeoTIFF',
    ];

    public static function topic(?string $code): ?string
    {
        return $code === null ? null : (self::TOPICS[$code] ?? $code);
    }

    public static function license(?string $code, ?string $other = null): ?string
    {
        if ($code === 'other') {
            return $other ?: self::LICENSES['other'];
        }

        return $code === null ? null : (self::LICENSES[$code] ?? $code);
    }

    /** "Source organization (year). Title. License." — empty parts are skipped. */
    public static function attribution(array $dataset): string
    {
        $year = $dataset['published_at'] ? Carbon::parse($dataset['published_at'])->year : null;
        $organization = $dataset['source_organization'] ?: null;

        $parts = $organization
            ? [$organization.($year ? " ({$year})" : ''), $dataset['title']]
            : [$dataset['title'].($year ? " ({$year})" : '')];

        $license = self::license($dataset['license'], $dataset['license_other']);

        if ($license) {
            $parts[] = $license;
        }

        return implode('. ', array_map(fn (string $part) => rtrim($part, '. '), $parts)).'.';
    }

    /** Everything the client-side catalogue needs to label filters and cards. */
    public static function forClient(): array
    {
        return [
            'topics' => self::TOPICS,
            'licenses' => self::LICENSES,
            'formats' => self::FORMATS,
        ];
    }
}
