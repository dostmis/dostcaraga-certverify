<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PsgcController extends Controller
{
    private const CACHE_TTL = 604800; // 7 days
    private const TIMEOUT = 15;
    private const BASE_URL = 'https://psgc.rootscratch.com';

    private function fetchFromApi(string $path): array
    {
        $url = self::BASE_URL . $path;

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->retry(2, 500)
                ->get($url);

            if ($response->failed()) {
                Log::warning('PSGC Rootscratch API request failed.', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return [];
            }

            $data = $response->json();
            if (!is_array($data)) {
                Log::warning('PSGC Rootscratch API returned non-array.', ['url' => $url]);
                return [];
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('PSGC Rootscratch API request exception.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Load the static PSGC JSON file and normalize it into a queryable structure.
     * Returns: ['province_name_lower' => ['city_name_lower' => ['barangay_name_lower']]]
     */
    private function loadStaticPsgc(): array
    {
        $path = resource_path('data/psgc.json');
        if (!file_exists($path)) {
            return [];
        }

        $raw = json_decode(file_get_contents($path), true);
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $regionName => $regionData) {
            if (!is_array($regionData)) {
                continue;
            }
            foreach ($regionData as $provName => $provData) {
                if ($provName === 'population' || !is_array($provData)) {
                    continue;
                }
                $cities = [];
                foreach ($provData as $cityName => $cityData) {
                    if ($cityName === 'population' || $cityName === 'class' || $cityName === 'cityClass' || !is_array($cityData)) {
                        continue;
                    }
                    $barangays = [];
                    foreach ($cityData as $brgyName => $brgyData) {
                        if (is_array($brgyData) && isset($brgyData['population'])) {
                            $barangays[] = $brgyName;
                        }
                    }
                    // Handle flat/leaf entries (e.g., NCR where barangays are
                    // directly under cities with only {population: N} structure).
                    if (count($barangays) === 0 && isset($cityData['population'])) {
                        $barangays[] = $cityName;
                    }
                    if (count($barangays) > 0) {
                        $cities[strtolower($cityName)] = [
                            'name' => $cityName,
                            'barangays' => $barangays,
                        ];
                    }
                }
                $result[strtolower($provName)] = [
                    'name' => $provName,
                    'cities' => $cities,
                ];
            }
        }

        return $result;
    }

    /**
     * Fallback: get provinces for a region from the static PSGC JSON.
     * Used when the Rootscratch API returns no provinces (e.g., NCR).
     */
    private function getProvincesFromStaticByRegion(string $regionName): array
    {
        $path = resource_path('data/psgc.json');
        if (!file_exists($path)) {
            return [];
        }

        $raw = json_decode(file_get_contents($path), true);
        if (!is_array($raw)) {
            return [];
        }

        // Case-insensitive region lookup
        $regionKey = null;
        foreach ($raw as $key => $data) {
            if (strcasecmp($key, $regionName) === 0) {
                $regionKey = $key;
                break;
            }
        }

        if ($regionKey === null || !isset($raw[$regionKey]) || !is_array($raw[$regionKey])) {
            return [];
        }

        $result = [];
        foreach ($raw[$regionKey] as $provName => $provData) {
            if ($provName === 'population' || !is_array($provData)) {
                continue;
            }
            $result[] = [
                'name' => $provName,
                'psgc_code' => '',
            ];
        }

        usort($result, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $result;
    }

    public function regions(): JsonResponse
    {
        $data = Cache::remember('psgc_regions', self::CACHE_TTL, function () {
            $raw = $this->fetchFromApi('/region');
            $result = [];
            foreach ($raw as $item) {
                if (!empty($item['name'])) {
                    $result[] = [
                        'name' => $item['name'],
                        'psgc_code' => $item['psgc_id'] ?? '',
                    ];
                }
            }
            usort($result, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
            return $result;
        });

        return response()->json($data);
    }

    public function provinces(Request $request): JsonResponse
    {
        $regCode = trim((string) $request->query('reg_code', ''));
        if ($regCode === '') {
            return response()->json(['error' => 'reg_code is required'], 422);
        }

        $regPrefix = substr($regCode, 0, 2);

        $cacheKey = 'psgc_provinces_' . $regPrefix;

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($regPrefix, $regCode) {
            $raw = $this->fetchFromApi('/province');
            $result = [];
            foreach ($raw as $item) {
                if (empty($item['name']) || empty($item['psgc_id'])) {
                    continue;
                }
                if (substr($item['psgc_id'], 0, 2) !== $regPrefix) {
                    continue;
                }
                $result[] = [
                    'name' => trim($item['name']),
                    'psgc_code' => $item['psgc_id'],
                ];
            }

            // Fallback: if the API returned no provinces (e.g., NCR has no
            // traditional provinces — its cities are directly under the region),
            // extract province-level entries from the static PSGC JSON.
            if (count($result) === 0) {
                // Look up the region name from the regions list
                $regionName = null;
                $regions = Cache::get('psgc_regions');
                if (!$regions) {
                    // Regions cache not yet populated; fetch fresh
                    $regionsRaw = $this->fetchFromApi('/region');
                    $regions = [];
                    foreach ($regionsRaw as $item) {
                        if (!empty($item['name'])) {
                            $regions[] = [
                                'name' => $item['name'],
                                'psgc_code' => $item['psgc_id'] ?? '',
                            ];
                        }
                    }
                }
                foreach ($regions as $region) {
                    if (($region['psgc_code'] ?? '') === $regCode) {
                        $regionName = $region['name'];
                        break;
                    }
                }
                if ($regionName) {
                    $result = $this->getProvincesFromStaticByRegion($regionName);
                }
            }

            usort($result, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
            return $result;
        });

        return response()->json($data);
    }

    /**
     * Get municipalities/cities for a province by name.
     * Uses the static JSON file which is complete and reliable.
     */
    public function municipalities(Request $request): JsonResponse
    {
        $provinceName = trim((string) $request->query('province_name', ''));
        if ($provinceName === '') {
            return response()->json(['error' => 'province_name is required'], 422);
        }

        $cacheKey = 'psgc_municipalities_' . md5(strtolower($provinceName));

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($provinceName) {
            $static = $this->loadStaticPsgc();
            $key = strtolower($provinceName);

            if (!isset($static[$key])) {
                // Try fuzzy matching (e.g. "Agusan del Norte" vs "Agusan Del Norte")
                foreach ($static as $provKey => $provData) {
                    if (strcasecmp($provKey, $provinceName) === 0 ||
                        strcasecmp($provData['name'], $provinceName) === 0) {
                        $key = $provKey;
                        break;
                    }
                }
            }

            if (!isset($static[$key])) {
                return [];
            }

            $result = [];
            foreach ($static[$key]['cities'] as $cityData) {
                $result[] = [
                    'name' => $cityData['name'],
                    'psgc_code' => '',
                ];
            }

            usort($result, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
            return $result;
        });

        return response()->json($data);
    }

    /**
     * Get barangays for a city/municipality by name.
     * Uses the static JSON file which is complete and reliable.
     */
    public function barangays(Request $request): JsonResponse
    {
        $cityName = trim((string) $request->query('city_name', ''));
        $provinceName = trim((string) $request->query('province_name', ''));

        if ($cityName === '' || $provinceName === '') {
            return response()->json(['error' => 'city_name and province_name are required'], 422);
        }

        $cacheKey = 'psgc_barangays_' . md5(strtolower($provinceName) . '|' . strtolower($cityName));

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($cityName, $provinceName) {
            $static = $this->loadStaticPsgc();
            $provKey = strtolower($provinceName);

            // Find province
            if (!isset($static[$provKey])) {
                foreach ($static as $pk => $pd) {
                    if (strcasecmp($pd['name'], $provinceName) === 0) {
                        $provKey = $pk;
                        break;
                    }
                }
            }

            if (!isset($static[$provKey])) {
                return [];
            }

            // Find city
            $cityKey = strtolower($cityName);
            if (!isset($static[$provKey]['cities'][$cityKey])) {
                foreach ($static[$provKey]['cities'] as $ck => $cd) {
                    if (strcasecmp($cd['name'], $cityName) === 0) {
                        $cityKey = $ck;
                        break;
                    }
                }
            }

            if (!isset($static[$provKey]['cities'][$cityKey])) {
                return [];
            }

            $result = [];
            foreach ($static[$provKey]['cities'][$cityKey]['barangays'] as $brgyName) {
                $result[] = [
                    'name' => $brgyName,
                    'psgc_code' => '',
                ];
            }

            usort($result, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
            return $result;
        });

        return response()->json($data);
    }
}
