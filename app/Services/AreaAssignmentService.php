<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Branch;
use App\Models\Store;

class AreaAssignmentService
{
    protected const CENTROID_RADIUS_KM = 5.0;
    protected const MAX_MEMBER_DISTANCE_KM = 6.0;

    protected function haversineKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a =
            sin($dLat / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        return $earthRadius * (
            2 * atan2(
                sqrt($a),
                sqrt(1 - $a)
            )
        );
    }

    /**
     * Ambil branch yang sedang aktif dari Branch Switcher.
     */
    protected function getActiveBranch(): ?Branch
    {
        return app(BranchContext::class)->get();
    }

    /**
     * Cari Area terdekat di dalam branch aktif.
     */
    protected function findNearbyArea(
        Branch $branch,
        float $lat,
        float $lng,
        ?int $excludeStoreId = null
    ): ?Area {

        $nearestArea = null;
        $nearestDistance = null;


        $areas =
            Area::query()
            ->where(
                'branch_id',
                $branch->id
            )
            ->get();


        foreach ($areas as $area) {

            /*
            |--------------------------------------------------------------------------
            | ANGGOTA AREA
            |--------------------------------------------------------------------------
            */

            $members =
                Store::query()
                    ->where(
                        'area_id',
                        $area->id
                    )
                    ->when(
                        $excludeStoreId !== null,
                        fn($query) =>
                        $query->where(
                            'id',
                            '!=',
                            $excludeStoreId
                        )
                    )
                    ->whereNotNull(
                        'latitude'
                    )
                    ->whereNotNull(
                        'longitude'
                    )
                    ->get([
                        'id',
                        'latitude',
                        'longitude',
                    ]);


            /*
             * Area kosong tidak dijadikan kandidat.
             */
            if ($members->isEmpty()) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | CENTROID AREA
            |--------------------------------------------------------------------------
            */

            $avgLatitude =
                (float) $members->avg(
                    'latitude'
                );


            $avgLongitude =
                (float) $members->avg(
                    'longitude'
                );


            $centroidDistance =
                $this->haversineKm(
                    $lat,
                    $lng,
                    $avgLatitude,
                    $avgLongitude
                );


            /*
             * Store terlalu jauh dari pusat Area.
             */
            if (
                $centroidDistance
                >
                self::CENTROID_RADIUS_KM
            ) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | JARAK KE SETIAP ANGGOTA
            |--------------------------------------------------------------------------
            */

            $fitsAllMembers =
                true;


            foreach ($members as $member) {

                $memberDistance =
                    $this->haversineKm(
                        $lat,
                        $lng,
                        (float) $member->latitude,
                        (float) $member->longitude
                    );


                if (
                    $memberDistance
                    >
                    self::MAX_MEMBER_DISTANCE_KM
                ) {

                    $fitsAllMembers =
                        false;

                    break;
                }
            }


            if (! $fitsAllMembers) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | PILIH AREA TERDEKAT
            |--------------------------------------------------------------------------
            */

            if (
                $nearestDistance === null
                ||
                $centroidDistance
                <
                $nearestDistance
            ) {

                $nearestArea =
                    $area;

                $nearestDistance =
                    $centroidDistance;
            }
        }


        return $nearestArea;
    }

    /**
     * Generate kode Area berikutnya untuk branch tertentu.
     */
    protected function nextAreaCode(Branch $branch): string
    {
        $prefix = $branch->init_cab;

        $lastArea = Area::query()
            ->where('branch_id', $branch->id)
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->first();

        if (! $lastArea) {
            return $prefix . '001';
        }

        $lastNumber = (int) substr(
            $lastArea->code,
            strlen($prefix)
        );

        return $prefix . str_pad(
            $lastNumber + 1,
            3,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Read-only.
     * Digunakan untuk preview pada StoreForm.
     */
    public function preview(
        float $lat,
        float $lng
    ): array {
        $branch = $this->getActiveBranch();

        if (! $branch) {
            return [
                'branch' => null,
                'area_code' => null,
                'is_new' => false,
            ];
        }

        $area = $this->findNearbyArea(
            $branch,
            $lat,
            $lng
        );

        if ($area) {
            return [
                'branch' => $branch,
                'area_code' => $area->code,
                'is_new' => false,
            ];
        }

        return [
            'branch' => $branch,
            'area_code' => $this->nextAreaCode($branch),
            'is_new' => true,
        ];
    }

    /**
     * Cari Area yang sesuai atau buat Area baru
     * di branch aktif.
     */
    public function resolve(
        float $lat,
        float $lng
    ): Area {
        $branch = $this->getActiveBranch();

        if (! $branch) {
            throw new \Exception(
                'Tidak ada cabang aktif. Silakan pilih cabang terlebih dahulu.'
            );
        }

        $area = $this->findNearbyArea(
            $branch,
            $lat,
            $lng
        );

        if ($area) {
            return $area;
        }

        return Area::create([
            'branch_id' => $branch->id,
            'code' => $this->nextAreaCode($branch),
        ]);
    }
}