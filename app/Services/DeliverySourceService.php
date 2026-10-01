<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliverySourceService
{
    protected const SO_TABLE = 'so_sda';
    protected const CUSTOMER_TABLE = 'cust_sda';
    protected const DEFAULT_SERVICE_DURATION_MINUTES = 15;

    /*
    |------------------------------------------------------------------
    | PREP INTEGRASI SISTEM UTAMA
    |------------------------------------------------------------------
    |
    | Default saat ini membaca so_sda + cust_sda dari connection Laravel
    | yang aktif. Ketika database utama sudah terhubung, cukup set connection
    | source melalui config, tanpa mengubah logika grouping/optimizer.
    |
    | Contoh config/services.php:
    |
    | 'delivery_source' => [
    |     'connection' => env('DELIVERY_SOURCE_DB_CONNECTION'),
    |     'so_table' => env('DELIVERY_SOURCE_SO_TABLE', 'so_sda'),
    |     'customer_table' => env('DELIVERY_SOURCE_CUSTOMER_TABLE', 'cust_sda'),
    |     'order_date_column' => env('DELIVERY_SOURCE_ORDER_DATE_COLUMN', 'visit_date'),
    | ],
    |
    | Credential database tetap didefinisikan di config/database.php + .env.
    | Jangan simpan host/username/password di service ini.
    |
    */

    /**
     * Ambil dan normalisasi data delivery dari tabel operasional.
     *
     * Sumber:
     * - so_sda   : Sales Order / item / checkout coordinate / tanggal SO masuk
     * - cust_sda : Customer master / nama / alamat / fallback coordinate
     *
     * Satu demand = satu customer pada satu tanggal SO masuk (source_date).
     * Route date tidak dihitung di service ini; RouteGenerationService yang
     * menetapkan route_date = source_date + 2 hari.
     */
    public function build(
        Branch $branch,
        string $sourceDate
    ): array {
        $cabId = $this->resolveSourceCabId($branch);

        if ($cabId === '') {
            throw new RuntimeException(
                'Branch belum memiliki CAB ID untuk membaca sumber SO.'
            );
        }

        $rows = $this->querySourceRows(
            cabId: $cabId,
            sourceDate: $sourceDate,
        );

        if ($rows->isEmpty()) {
            return [
                'source_date' => $sourceDate,
                'cab_id' => $cabId,
                'row_count' => 0,
                'order_count' => 0,
                'customer_count' => 0,
                'demands' => collect(),
                'fingerprint' => hash('sha256', '[]'),
            ];
        }

        $demands = $rows
            ->groupBy(
                fn ($row): string =>
                    trim((string) $row->customerID)
            )
            ->map(
                fn (Collection $customerRows, string $customerId): array =>
                    $this->buildCustomerDemand(
                        branch: $branch,
                        sourceDate: $sourceDate,
                        customerId: $customerId,
                        rows: $customerRows,
                    )
            )
            ->values();

        $fingerprint = $this->fingerprint($demands);

        return [
            'source_date' => $sourceDate,
            'cab_id' => $cabId,
            'row_count' => $rows->count(),
            'order_count' => $rows
                ->pluck('so_number')
                ->filter()
                ->unique()
                ->count(),
            'customer_count' => $demands->count(),
            'demands' => $demands,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Preview ringan untuk UI Generate Route.
     *
     * Menggunakan query source yang sama dengan build(), sehingga filter cabang,
     * tanggal SO, status EFFECTIVECALL, connection, dan mapping database utama
     * tidak terduplikasi di halaman Filament.
     */
    public function preview(
        Branch $branch,
        string $sourceDate
    ): array {
        $cabId = $this->resolveSourceCabId($branch);

        if ($cabId === '') {
            return [
                'source_date' => $sourceDate,
                'cab_id' => '',
                'row_count' => 0,
                'order_count' => 0,
                'customer_count' => 0,
            ];
        }

        $rows = $this->querySourceRows(
            cabId: $cabId,
            sourceDate: $sourceDate,
        );

        return [
            'source_date' => $sourceDate,
            'cab_id' => $cabId,
            'row_count' => $rows->count(),
            'order_count' => $rows
                ->pluck('so_number')
                ->filter()
                ->unique()
                ->count(),
            'customer_count' => $rows
                ->pluck('customerID')
                ->filter()
                ->unique()
                ->count(),
        ];
    }

    /**
     * Mirror customer source ke tabel stores agar DeliveryStop lama tetap
     * memiliki store_id tanpa perlu CRUD manual lagi.
     *
     * Store bukan source of truth. Source of truth tetap cust_sda.
     */
    public function syncStores(
        Collection $demands
    ): Collection {
        return $demands
            ->mapWithKeys(
                function (array $demand): array {
                    $customerId =
                        trim((string) ($demand['customer_id'] ?? ''));

                    if ($customerId === '') {
                        throw new RuntimeException(
                            'Customer ID kosong saat sinkronisasi Store.'
                        );
                    }

                    $store = Store::query()
                        ->updateOrCreate(
                            [
                                'code' => $customerId,
                            ],
                            [
                                'name' =>
                                    (string) ($demand['customer_name'] ?? $customerId),

                                'address' =>
                                    (string) ($demand['address'] ?? '-'),

                                'latitude' =>
                                    (float) $demand['latitude'],

                                'longitude' =>
                                    (float) $demand['longitude'],
                            ]
                        );

                    return [
                        $customerId => $store,
                    ];
                }
            );
    }

    /**
     * Fingerprint snapshot source untuk mendeteksi perubahan data ketika
     * Python sedang menghitung route.
     */
    public function fingerprint(
        Collection|array $demands
    ): string {
        $normalized = collect($demands)
            ->map(
                function (array $demand): array {
                    return [
                        'id' => (string) ($demand['id'] ?? ''),
                        'customer_id' => (string) ($demand['customer_id'] ?? ''),
                        'customer_name' => (string) ($demand['customer_name'] ?? ''),
                        'address' => (string) ($demand['address'] ?? ''),
                        'latitude' => round((float) ($demand['latitude'] ?? 0), 7),
                        'longitude' => round((float) ($demand['longitude'] ?? 0), 7),
                        'so_numbers' => collect($demand['so_numbers'] ?? [])
                            ->map(fn ($value): string => (string) $value)
                            ->sort()
                            ->values()
                            ->all(),
                        'items' => collect($demand['items'] ?? [])
                            ->map(
                                fn (array $item): array => [
                                    'so_number' => (string) ($item['so_number'] ?? ''),
                                    'product_id' => (string) ($item['product_id'] ?? ''),
                                    'product_name' => (string) ($item['product_name'] ?? ''),
                                    'qty_cartons' => (float) ($item['qty_cartons'] ?? 0),
                                    'qty_packs' => (float) ($item['qty_packs'] ?? 0),
                                    'qty_pcs' => (float) ($item['qty_pcs'] ?? 0),
                                ]
                            )
                            ->sortBy(
                                fn (array $item): string =>
                                    implode('|', [
                                        $item['so_number'],
                                        $item['product_id'],
                                        $item['product_name'],
                                    ])
                            )
                            ->values()
                            ->all(),
                    ];
                }
            )
            ->sortBy('id')
            ->values()
            ->all();

        return hash(
            'sha256',
            json_encode(
                $normalized,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
            ) ?: '[]'
        );
    }

    protected function querySourceRows(
        string $cabId,
        string $sourceDate
    ): Collection {
        $db = $this->sourceDatabase();
        $soTable = $this->sourceSoTable();
        $customerTable = $this->sourceCustomerTable();
        $orderDateColumn = $this->sourceOrderDateColumn();

        /*
         * cust_sda bisa saja terisi ulang. Jika terdapat duplikasi customer,
         * pilih row dengan ID terbesar agar JOIN tidak menggandakan item SO.
         *
         * PREP INTEGRASI SISTEM UTAMA:
         * Semua query source memakai connection yang dikembalikan sourceDatabase().
         * Saat pindah DB, business logic di bawah tidak perlu diubah.
         */
        $latestCustomerIds = $db->table(
            $customerTable . ' as c_pick'
        )
            ->selectRaw(
                'MAX(c_pick.id) AS id, c_pick.customerID, c_pick.cabID'
            )
            ->groupBy(
                'c_pick.customerID',
                'c_pick.cabID'
            );

        return $db->table(
            $soTable . ' as so'
        )
            ->leftJoinSub(
                $latestCustomerIds,
                'customer_pick',
                function ($join): void {
                    $join
                        ->on(
                            'customer_pick.customerID',
                            '=',
                            'so.customerID'
                        )
                        ->on(
                            'customer_pick.cabID',
                            '=',
                            'so.cabID'
                        );
                }
            )
            ->leftJoin(
                $customerTable . ' as cust',
                'cust.id',
                '=',
                'customer_pick.id'
            )
            ->where(
                'so.cabID',
                $cabId
            )
            ->whereDate(
                'so.' . $orderDateColumn,
                $sourceDate
            )
            ->where(
                'so.status',
                'EFFECTIVECALL'
            )
            ->whereNotNull(
                'so.so_number'
            )
            ->where(
                'so.so_number',
                '!=',
                ''
            )
            ->select([
                'so.visit_key',
                'so.customerID',
                'so.salesmanID',
                'so.cabID',
                DB::raw('so.' . $orderDateColumn . ' as visit_date'),
                'so.so_number',
                'so.vendor_name',
                'so.product_id',
                'so.product_name',
                'so.qty_cartons',
                'so.qty_packs',
                'so.qty_pcs',
                'so.latitude_check_out',
                'so.longitude_check_out',

                'cust.customerName',
                'cust.addressLine1',
                'cust.addressLine2',
                'cust.addressLine3',
                'cust.city',
                'cust.latitude as customer_latitude',
                'cust.longitude as customer_longitude',
            ])
            ->orderBy('so.customerID')
            ->orderBy('so.visit_key')
            ->orderBy('so.so_number')
            ->orderBy('so.product_id')
            ->get();
    }

    protected function buildCustomerDemand(
        Branch $branch,
        string $sourceDate,
        string $customerId,
        Collection $rows
    ): array {
        if ($customerId === '') {
            throw new RuntimeException(
                'Ditemukan Sales Order dengan customerID kosong.'
            );
        }

        $customerName = $rows
            ->pluck('customerName')
            ->map(fn ($value): string => trim((string) $value))
            ->first(fn (string $value): bool => $value !== '')
            ?: $customerId;

        $address = $this->buildAddress($rows);

        [$latitude, $longitude] =
            $this->resolveCoordinates(
                customerId: $customerId,
                rows: $rows,
            );

        $items = $rows
            ->groupBy(
                fn ($row): string =>
                    implode('|', [
                        (string) $row->so_number,
                        (string) $row->product_id,
                        (string) $row->product_name,
                    ])
            )
            ->map(
                function (Collection $itemRows): array {
                    $first = $itemRows->first();

                    return [
                        'so_number' => (string) $first->so_number,
                        'product_id' => (string) ($first->product_id ?? ''),
                        'product_name' => (string) ($first->product_name ?? ''),
                        'qty_cartons' => $this->sumNumeric(
                            $itemRows,
                            'qty_cartons'
                        ),
                        'qty_packs' => $this->sumNumeric(
                            $itemRows,
                            'qty_packs'
                        ),
                        'qty_pcs' => $this->sumNumeric(
                            $itemRows,
                            'qty_pcs'
                        ),
                    ];
                }
            )
            ->values()
            ->all();

        $cabId = $this->resolveSourceCabId($branch);

        $demandId = implode(
            '::',
            [
                $cabId,
                $sourceDate,
                $customerId,
            ]
        );

        return [
            'id' => $demandId,

            'customer_id' => $customerId,
            'customer_name' => $customerName,

            /*
             * Alias sementara agar UI / kode lama yang masih memakai istilah
             * Store tidak langsung rusak. Python baru tidak bergantung padanya.
             */
            'store_id' => $customerId,
            'store_name' => $customerName,

            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,

            'service_duration_minutes' =>
                self::DEFAULT_SERVICE_DURATION_MINUTES,

            'so_numbers' => $rows
                ->pluck('so_number')
                ->filter()
                ->map(fn ($value): string => (string) $value)
                ->unique()
                ->values()
                ->all(),

            'items' => $items,
        ];
    }

    /**
     * PREP INTEGRASI SISTEM UTAMA - CONNECTION SOURCE.
     *
     * Jika services.delivery_source.connection kosong, gunakan default connection
     * Laravel seperti kondisi development saat ini.
     */
    protected function sourceDatabase()
    {
        $connection = config('services.delivery_source.connection');

        if (
            is_string($connection)
            && trim($connection) !== ''
        ) {
            return DB::connection(trim($connection));
        }

        return DB::connection();
    }

    protected function sourceSoTable(): string
    {
        $table = config(
            'services.delivery_source.so_table',
            self::SO_TABLE
        );

        $table = trim((string) $table);

        return $table !== '' ? $table : self::SO_TABLE;
    }

    protected function sourceCustomerTable(): string
    {
        $table = config(
            'services.delivery_source.customer_table',
            self::CUSTOMER_TABLE
        );

        $table = trim((string) $table);

        return $table !== '' ? $table : self::CUSTOMER_TABLE;
    }

    protected function sourceOrderDateColumn(): string
    {
        $column = config(
            'services.delivery_source.order_date_column',
            'visit_date'
        );

        $column = trim((string) $column);

        if ($column === '') {
            return 'visit_date';
        }

        /*
         * Nama column berasal dari config aplikasi, bukan input user.
         * Tetap batasi format agar tidak membuka raw identifier sembarangan.
         */
        if (! preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            throw new RuntimeException(
                'Konfigurasi kolom tanggal Sales Order tidak valid.'
            );
        }

        return $column;
    }

    /**
     * PREP INTEGRASI SISTEM UTAMA - MAPPING CABANG.
     *
     * Untuk sekarang source memakai Branch.cab_id. Jika sistem utama memakai
     * kode lain, ganti implementasi method ini ke master mapping resmi.
     */
    protected function resolveSourceCabId(Branch $branch): string
    {
        return trim((string) ($branch->cab_id ?? ''));
    }

    protected function resolveCoordinates(
        string $customerId,
        Collection $rows
    ): array {
        /*
         * Prioritas 1: checkout salesman dari SO.
         * Ambil pasangan valid terakhir pada snapshot hari tersebut.
         */
        $checkoutRow = $rows
            ->reverse()
            ->first(
                fn ($row): bool =>
                    $this->isValidCoordinatePair(
                        $row->latitude_check_out,
                        $row->longitude_check_out
                    )
            );

        if ($checkoutRow) {
            return [
                (float) $checkoutRow->latitude_check_out,
                (float) $checkoutRow->longitude_check_out,
            ];
        }

        /*
         * Prioritas 2: master customer.
         */
        $customerRow = $rows
            ->first(
                fn ($row): bool =>
                    $this->isValidCoordinatePair(
                        $row->customer_latitude,
                        $row->customer_longitude
                    )
            );

        if ($customerRow) {
            return [
                (float) $customerRow->customer_latitude,
                (float) $customerRow->customer_longitude,
            ];
        }

        throw new RuntimeException(
            "Customer {$customerId} tidak memiliki koordinat checkout "
            . 'maupun koordinat master customer yang valid.'
        );
    }

    protected function buildAddress(
        Collection $rows
    ): string {
        $row = $rows->first();

        if (! $row) {
            return '-';
        }

        $parts = collect([
            $row->addressLine1 ?? null,
            $row->addressLine2 ?? null,
            $row->addressLine3 ?? null,
            $row->city ?? null,
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        return $parts->isEmpty()
            ? '-'
            : $parts->implode(', ');
    }

    protected function isValidCoordinatePair(
        mixed $latitude,
        mixed $longitude
    ): bool {
        if (
            ! is_numeric($latitude)
            || ! is_numeric($longitude)
        ) {
            return false;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        return $lat >= -90
            && $lat <= 90
            && $lng >= -180
            && $lng <= 180
            && ! (
                abs($lat) < 0.0000001
                && abs($lng) < 0.0000001
            );
    }

    protected function sumNumeric(
        Collection $rows,
        string $column
    ): float {
        return round(
            (float) $rows->sum(
                fn ($row): float =>
                    is_numeric($row->{$column} ?? null)
                        ? (float) $row->{$column}
                        : 0.0
            ),
            4
        );
    }
}
