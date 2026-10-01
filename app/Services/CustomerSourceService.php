<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CustomerSourceService
 *
 * Satu pintu untuk membaca master customer dari source database.
 * Source saat ini masih tabel hasil import lokal: cust_sda.
 */
class CustomerSourceService
{
    /*
    |--------------------------------------------------------------------------
    | PREP INTEGRASI SISTEM UTAMA - DATABASE SOURCE
    |--------------------------------------------------------------------------
    |
    | SEKARANG:
    |   connection = default Laravel
    |   table      = cust_sda
    |
    | NANTI:
    |   connection = main_system
    |   table      = nama tabel customer di database utama
    |
    | Credential database JANGAN ditaruh di file ini.
    | Simpan di .env dan config/database.php.
    |
    */
    private const DB_CONNECTION = null;
    private const DB_TABLE = 'cust_sda';

    /*
    |--------------------------------------------------------------------------
    | PREP INTEGRASI SISTEM UTAMA - MAPPING KOLOM
    |--------------------------------------------------------------------------
    |
    | Hanya field di bawah yang dibutuhkan aplikasi route optimization.
    | Jika nama kolom database utama berubah, mapping cukup diubah di sini.
    |
    */
    public const COL_CUSTOMER_ID = 'customerID';
    public const COL_CUSTOMER_NAME = 'customerName';
    public const COL_ADDRESS = 'addressLine1';
    public const COL_CITY = 'city';
    public const COL_PHONE = 'phone';
    public const COL_RECEIVER_NAME = 'taxName';
    public const COL_LATITUDE = 'latitude';
    public const COL_LONGITUDE = 'longitude';
    public const COL_AREA = 'area';
    public const COL_SUB_AREA = 'subArea';
    public const COL_BRANCH_CODE = 'cabID';

    /**
     * Whitelist field customer yang boleh dibaca aplikasi.
     *
     * cabID tetap ikut sebagai field teknis untuk filter/source mapping,
     * tetapi tidak wajib ditampilkan ke driver.
     */
    public static function sourceColumns(bool $includeBranchCode = true): array
    {
        $columns = [
            self::COL_CUSTOMER_ID,
            self::COL_CUSTOMER_NAME,
            self::COL_ADDRESS,
            self::COL_CITY,
            self::COL_PHONE,
            self::COL_RECEIVER_NAME,
            self::COL_LATITUDE,
            self::COL_LONGITUDE,
            self::COL_AREA,
            self::COL_SUB_AREA,
        ];

        if ($includeBranchCode) {
            $columns[] = self::COL_BRANCH_CODE;
        }

        return $columns;
    }

    /**
     * Query source customer. Tidak memakai SELECT *.
     */
    public function query(): Builder
    {
        return $this->getBaseQuery()
            ->select(self::sourceColumns());
    }

    /**
     * Mapping cabang lokal ke cabID source.
     *
     * PREP INTEGRASI SISTEM UTAMA:
     * Branch.cab_id dipakai juga oleh DeliverySourceService supaya Stores dan
     * Generate Route membaca kode cabang source yang sama.
     *
     * Jika sistem utama nanti membutuhkan mapping berbeda, pusatkan perubahan
     * pada master Branch / service resolver, jangan membuat if/else per cabang.
     */
    public function resolveSourceBranchCode(Branch $branch): ?string
    {
        $cabId = trim((string) ($branch->cab_id ?? ''));

        return $cabId !== '' ? $cabId : null;
    }

    public function paginate(
        ?string $branchCode = null,
        ?string $search = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = $this->query();

        $this->applyBranchFilter($query, $branchCode);
        $this->applySearch($query, $search);

        return $query
            ->orderBy(self::COL_CUSTOMER_NAME)
            ->paginate($perPage);
    }

    public function findById(string $customerId): ?object
    {
        return $this->query()
            ->where(self::COL_CUSTOMER_ID, $customerId)
            ->first();
    }

    /**
     * Untuk dropdown yang masih membutuhkan pasangan customerID => customerName.
     */
    public function forSelect(?string $branchCode = null): Collection
    {
        $query = $this->getBaseQuery()
            ->select([
                self::COL_CUSTOMER_ID,
                self::COL_CUSTOMER_NAME,
            ]);

        $this->applyBranchFilter($query, $branchCode);

        return $query
            ->orderBy(self::COL_CUSTOMER_NAME)
            ->get()
            ->pluck(self::COL_CUSTOMER_NAME, self::COL_CUSTOMER_ID);
    }

    public function countByBranch(string $branchCode): int
    {
        return (int) $this->getBaseQuery()
            ->where(self::COL_BRANCH_CODE, $branchCode)
            ->count();
    }

    private function getBaseQuery(): Builder
    {
        /*
        |---------------------------------------------------------------------
        | PREP INTEGRASI SISTEM UTAMA
        |---------------------------------------------------------------------
        |
        | Saat database utama siap, isi DB_CONNECTION = 'main_system'.
        | Tidak perlu mengubah ListStores / StoresTable.
        |
        */
        if (self::DB_CONNECTION !== null) {
            return DB::connection(self::DB_CONNECTION)
                ->table(self::DB_TABLE);
        }

        return DB::table(self::DB_TABLE);
    }

    private function applyBranchFilter(
        Builder $query,
        ?string $branchCode,
    ): void {
        if ($branchCode !== null && $branchCode !== '') {
            $query->where(self::COL_BRANCH_CODE, $branchCode);
        }
    }

    private function applySearch(
        Builder $query,
        ?string $search,
    ): void {
        if ($search === null || trim($search) === '') {
            return;
        }

        $like = '%' . trim($search) . '%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where(self::COL_CUSTOMER_ID, 'like', $like)
                ->orWhere(self::COL_CUSTOMER_NAME, 'like', $like)
                ->orWhere(self::COL_ADDRESS, 'like', $like)
                ->orWhere(self::COL_CITY, 'like', $like)
                ->orWhere(self::COL_PHONE, 'like', $like)
                ->orWhere(self::COL_RECEIVER_NAME, 'like', $like)
                ->orWhere(self::COL_AREA, 'like', $like)
                ->orWhere(self::COL_SUB_AREA, 'like', $like);
        });
    }
}
