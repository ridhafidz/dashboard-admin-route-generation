<?php

namespace App\Services;

use App\Models\Product;
use App\Models\VehicleType;
use RuntimeException;

class CubicationService
{
    /*
    |--------------------------------------------------------------------------
    | CARTON VOLUME
    |--------------------------------------------------------------------------
    */

    public function cartonVolumeM3(
        ?Product $product
    ): float {
        if (! $product) {
            return 0;
        }

        if (
            ! $product->carton_length_cm
            || ! $product->carton_width_cm
            || ! $product->carton_height_cm
        ) {
            return 0;
        }

        return (
            (float) $product->carton_length_cm
            *
            (float) $product->carton_width_cm
            *
            (float) $product->carton_height_cm
        ) / 1_000_000;
    }


    /*
    |--------------------------------------------------------------------------
    | UNIT / PCS VOLUME
    |--------------------------------------------------------------------------
    */

    public function unitVolumeM3(
        ?Product $product
    ): float {
        if (! $product) {
            return 0;
        }

        $cartonVolume =
            $this->cartonVolumeM3(
                $product
            );

        if (
            ! $product->units_per_carton
            ||
            $product->units_per_carton <= 0
        ) {
            return 0;
        }

        return
            $cartonVolume
            /
            $product->units_per_carton;
    }


    /*
    |--------------------------------------------------------------------------
    | PACK VOLUME
    |--------------------------------------------------------------------------
    */

    public function packVolumeM3(
        ?Product $product
    ): float {
        if (! $product) {
            return 0;
        }

        if (
            ! $product->units_per_pack
            ||
            $product->units_per_pack <= 0
        ) {
            return 0;
        }

        return
            $this->unitVolumeM3(
                $product
            )
            *
            $product->units_per_pack;
    }


    /*
    |--------------------------------------------------------------------------
    | SO ITEM TOTAL PCS
    |--------------------------------------------------------------------------
    */

    public function totalPcs(
        Product $product,
        int $qtyCartons = 0,
        int $qtyPacks = 0,
        int $qtyPcs = 0
    ): int {
        return
            (
                $qtyCartons
                *
                (int) $product->units_per_carton
            )
            +
            (
                $qtyPacks
                *
                (int) ($product->units_per_pack ?? 0)
            )
            +
            $qtyPcs;
    }

    /*
    |--------------------------------------------------------------------------
    | SO ITEM TOTAL VOLUME
    |--------------------------------------------------------------------------
    */

    public function itemVolumeM3(
        Product $product,
        int $qtyCartons = 0,
        int $qtyPacks = 0,
        int $qtyPcs = 0
    ): float {
        $cartonVolume =
            $qtyCartons
            *
            (float) $product->carton_volume_m3;

        $packVolume = 0;

        if (
            $qtyPacks > 0
            &&
            ! empty($product->units_per_pack)
        ) {
            $packVolume =
                $qtyPacks
                *
                (int) $product->units_per_pack
                *
                (float) $product->unit_volume_m3;
        }

        $pcsVolume =
            $qtyPcs
            *
            (float) $product->unit_volume_m3;

    return
        $cartonVolume
        +
        $packVolume
        +
        $pcsVolume;
    }

    /*
    |--------------------------------------------------------------------------
    | VEHICLE BOX VOLUME
    |--------------------------------------------------------------------------
    */

    public function vehicleVolumeM3(
        ?VehicleType $vehicleType
    ): float {
        if (! $vehicleType) {
            return 0;
        }

        if (
            ! $vehicleType->length_cm
            ||
            ! $vehicleType->width_cm
            ||
            ! $vehicleType->height_cm
        ) {
            return 0;
        }

        return (
            (float) $vehicleType->length_cm
            *
            (float) $vehicleType->width_cm
            *
            (float) $vehicleType->height_cm
        ) / 1_000_000;
    }


    /*
    |--------------------------------------------------------------------------
    | VEHICLE USABLE VOLUME
    |--------------------------------------------------------------------------
    */

    public function vehicleUsableVolumeM3(
        ?VehicleType $vehicleType
    ): float {
        if (! $vehicleType) {
            return 0;
        }

        $utilization =
            (float)
            $vehicleType->packing_utilization;

        if ($utilization <= 0) {
            $utilization = 1;
        }

        return
            $this->vehicleVolumeM3(
                $vehicleType
            )
            *
            $utilization;
    }


    /*
    |--------------------------------------------------------------------------
    | MAX CARTON CAPACITY
    |--------------------------------------------------------------------------
    |
    | Rumus sesuai konsep operasional:
    |
    | INT(Box P / Carton P)
    | x
    | INT(Box L / Carton L)
    | x
    | INT(Box T / Carton T)
    |
    |--------------------------------------------------------------------------
    */

    public function maxCartonCapacity(
        ?VehicleType $vehicleType,
        ?Product $product
    ): int {
        if (! $vehicleType || ! $product) {
            return 0;
        }

        if (
            ! $vehicleType->length_cm
            ||
            ! $vehicleType->width_cm
            ||
            ! $vehicleType->height_cm
            ||
            ! $product->carton_length_cm
            ||
            ! $product->carton_width_cm
            ||
            ! $product->carton_height_cm
        ) {
            return 0;
        }

        $lengthCapacity =
            (int) floor(
                $vehicleType->length_cm
                /
                $product->carton_length_cm
            );

        $widthCapacity =
            (int) floor(
                $vehicleType->width_cm
                /
                $product->carton_width_cm
            );

        $heightCapacity =
            (int) floor(
                $vehicleType->height_cm
                /
                $product->carton_height_cm
            );

        return
            $lengthCapacity
            *
            $widthCapacity
            *
            $heightCapacity;
    }


    /*
    |--------------------------------------------------------------------------
    | BOX TYPE COMPATIBILITY
    |--------------------------------------------------------------------------
    */

    public function isCompatible(
        ?Product $product,
        ?VehicleType $vehicleType
    ): bool {
        if (! $product || ! $vehicleType) {
            return false;
        }

        return
            $product->box_type
            ===
            $vehicleType->box_type;
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT MASTER VALIDATION
    |--------------------------------------------------------------------------
    */

    public function validateProduct(
        Product $product
    ): void {
        if (
            ! in_array(
                $product->box_type,
                [
                    'dry',
                    'cold_storage',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                "Box type produk {$product->code} belum valid."
            );
        }

        if (
            ! $product->units_per_carton
            ||
            (int) $product->units_per_carton <= 0
        ) {
            throw new RuntimeException(
                "PCS per carton produk {$product->code} belum valid."
            );
        }

        if (
            ! is_numeric($product->carton_volume_cm3)
            ||
            (float) $product->carton_volume_cm3 <= 0
        ) {
            throw new RuntimeException(
                "Volume carton produk {$product->code} belum valid."
            );
        }

        if (
            ! is_numeric($product->unit_volume_cm3)
            ||
            (float) $product->unit_volume_cm3 <= 0
        ) {
            throw new RuntimeException(
                "Volume per PCS produk {$product->code} belum valid."
            );
        }
    }
}