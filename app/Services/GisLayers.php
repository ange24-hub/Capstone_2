<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Http\Request;

class GisLayers
{
    public const FILES = [
        'municipal-boundary' => 'Boundary_Municipal.geojson',
        'barangay-boundaries' => 'Boundary_Barangay.geojson',
        'building-points' => 'Household_Population_Bldg_Pts.geojson',
        'flood' => 'Susceptibility_Flood.geojson',
        'landslide' => 'Susceptibility_Landslide.geojson',
        'storm-surge' => 'Susceptibility_Storm_Surge.geojson',
    ];

    public function scope(Request $request): ?Barangay
    {
        $request->validate(['barangay_id' => ['nullable', 'integer', 'exists:barangays,id']]);
        if ($request->user()->hasRole(User::ROLE_BARANGAY)) {
            abort_unless($request->user()->barangay_id, 403, 'Your secretary account is not assigned to a barangay.');

            return null;
        }

        return $request->filled('barangay_id') ? Barangay::findOrFail($request->integer('barangay_id')) : null;
    }

    public function path(string $layer): string
    {
        abort_unless(isset(self::FILES[$layer]), 404);
        $path = resource_path('gis/'.self::FILES[$layer]);
        abort_unless(is_file($path), 404, 'Map layer is unavailable.');

        return $path;
    }

    public function read(string $layer, ?Barangay $scope = null): array
    {
        $data = json_decode(file_get_contents($this->path($layer)), true, 512, JSON_THROW_ON_ERROR);

        if (in_array($layer, ['barangay-boundaries', 'building-points'], true)) {
            $boundaries = $layer === 'barangay-boundaries' ? $data : $this->read('barangay-boundaries');
            $names = [];
            $geometries = [];
            $codes = [];
            foreach ($boundaries['features'] as $feature) {
                $code = (string) $feature['properties']['BARANGAY_C'];
                $name = $feature['properties']['Name'];
                $names[$code] = $name;
                $geometries[$code] = $feature['geometry'];
                if (! $scope || $this->normalize($name) === $this->normalize($scope->name)) {
                    $codes[] = $code;
                }
            }
            $data['features'] = array_values(array_filter($data['features'], fn ($feature) => in_array(
                (string) ($feature['properties']['BARANGAY_C'] ?? ''), $codes, true
            )));
            if ($layer === 'building-points') {
                foreach ($data['features'] as &$feature) {
                    $code = (string) $feature['properties']['BARANGAY_C'];
                    // Source building points are reference geometry, not registry households.
                    [$longitude, $latitude] = $feature['geometry']['coordinates'];
                    $feature['properties'] = [
                        'barangay' => $names[$code], 'barangay_code' => $code,
                        'within_barangay_boundary' => $this->containsGeometry($geometries[$code], $latitude, $longitude),
                    ];
                }
                unset($feature);
            }
        }

        return $data;
    }

    private function normalize(string $name): string
    {
        $name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', preg_replace('/\([^)]*\)/', '', $name)));

        return ['ponong' => 'punong', 'higosan' => 'higosoan'][$name] ?? $name;
    }

    public function contains(Barangay $barangay, float $latitude, float $longitude): bool
    {
        foreach ($this->read('barangay-boundaries', $barangay)['features'] as $feature) {
            if ($this->containsGeometry($feature['geometry'], $latitude, $longitude)) {
                return true;
            }
        }

        return false;
    }

    private function containsGeometry(array $geometry, float $latitude, float $longitude): bool
    {
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        foreach ($polygons as $rings) {
            if (! $this->insideRing($rings[0], $longitude, $latitude)) {
                continue;
            }
            foreach (array_slice($rings, 1) as $hole) {
                if ($this->insideRing($hole, $longitude, $latitude)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private function insideRing(array $ring, float $x, float $y): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            // Include an outer boundary point; the same check excludes hole edges.
            $cross = ($x - $xi) * ($yj - $yi) - ($y - $yi) * ($xj - $xi);
            $tolerance = 1e-10;
            if (abs($cross) <= $tolerance * max(abs($xj - $xi), abs($yj - $yi))
                && $x >= min($xi, $xj) - $tolerance && $x <= max($xi, $xj) + $tolerance
                && $y >= min($yi, $yj) - $tolerance && $y <= max($yi, $yj) + $tolerance) {
                return true;
            }
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
