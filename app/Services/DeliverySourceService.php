<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStopAction;
use App\Models\Store;
use App\Models\Product;
use App\Enums\RouteStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliverySourceService
{
    protected const SO_TABLE = 'so_sda';
    protected const CUSTOMER_TABLE = 'cust_sda';
    protected const DEFAULT_SERVICE_DURATION_MINUTES = 15;
    protected const DEFAULT_BACKLOG_DAYS = 7;

    public function __construct(
        protected CubicationService $cubication
    ) {
    }

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

        $dateRange =
            $this->resolveSourceDateRange(
                $sourceDate
            );

        $startDate =
            $dateRange['start_date'];

        $endDate =
            $dateRange['end_date'];

        $rows = $this->querySourceRows(
            cabId: $cabId,
            startDate: $startDate,
            endDate: $endDate,
        );

        $productCodes = $rows
            ->pluck('product_code')
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn('code', $productCodes)
            ->get()
            ->keyBy(
                fn (Product $product): string =>
                    trim((string) $product->code)
            );

/*
|--------------------------------------------------------------------------
| VALIDASI PRODUK TANPA MEMBATALKAN SELURUH GENERATE
|--------------------------------------------------------------------------
|
| Jika ada produk invalid, seluruh SO yang mengandung produk tersebut
| disisihkan sementara. SO lain yang valid tetap boleh masuk optimizer.
|
*/

        $invalidProductReasons = [];

        foreach ($productCodes as $productCode) {
            /** @var Product|null $product */
            $product = $products->get($productCode);

            if (! $product) {
                $invalidProductReasons[$productCode] = [
                    'reason' => 'MASTER_PRODUCT_NOT_FOUND',
                    'message' => "Master produk {$productCode} belum ditemukan.",
                ];

                continue;
            }

            try {
                $this->cubication->validateProduct($product);
            } catch (\Throwable $e) {
                $invalidProductReasons[$productCode] = [
                    'reason' => 'INVALID_PRODUCT_CUBICATION',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $invalidItems = collect();
        $invalidSoKeys = [];

/*
|--------------------------------------------------------------------------
| CEK PER BARIS SO
|--------------------------------------------------------------------------
*/

        foreach ($rows as $row) {
            $productCode = trim((string) $row->product_code);

            $customerId = trim((string) $row->customerID);
            $soNumber = trim((string) $row->so_number);

            $soKey = $customerId . '::' . $soNumber;

            /*
             * Produk tidak ditemukan / kubikasi master invalid.
             */
            if (isset($invalidProductReasons[$productCode])) {
                $reasonData = $invalidProductReasons[$productCode];

                $invalidSoKeys[$soKey] = true;

                $invalidItems->push([
                    'customer_id' => $customerId,
                    'customer_name' => trim(
                        (string) ($row->customerName ?? $customerId)
                    ),
                    'so_number' => $soNumber,
                    'source_date' => (string) $row->source_date,
                    'product_code' => $productCode,
                    'product_name' => trim(
                        (string) ($row->product_name ?? '')
                    ),
                    'reason' => $reasonData['reason'],
                    'message' => $reasonData['message'],
                ]);

                continue;
            }

            /** @var Product|null $product */
            $product = $products->get($productCode);

            if (! $product) {
                continue;
            }

            /*
             * Validasi qty pack/toples.
             */
            $qtyPacks = is_numeric($row->qty_packs ?? null)
                ? (float) $row->qty_packs
                : 0;

            if (
                $qtyPacks > 0
                &&
                (
                    ! $product->units_per_pack
                    ||
                    (int) $product->units_per_pack <= 0
                )
            ) {
                $invalidSoKeys[$soKey] = true;

                $invalidItems->push([
                    'customer_id' => $customerId,
                    'customer_name' => trim(
                        (string) ($row->customerName ?? $customerId)
                    ),
                    'so_number' => $soNumber,
                    'source_date' => (string) $row->source_date,
                    'product_code' => $productCode,
                    'product_name' => trim(
                        (string) ($row->product_name ?? $product->name)
                    ),
                    'reason' => 'UNITS_PER_PACK_MISSING',
                    'message' =>
                        "Produk {$productCode} memiliki qty pack/toples "
                        . 'tetapi units_per_pack belum diisi.',
                ]);
            }
        }

/*
|--------------------------------------------------------------------------
| BUANG SELURUH SO YANG PUNYA ITEM INVALID
|--------------------------------------------------------------------------
*/

        $rows = $rows
            ->reject(function ($row) use ($invalidSoKeys): bool {
                $customerId = trim((string) $row->customerID);
                $soNumber = trim((string) $row->so_number);

                $soKey = $customerId . '::' . $soNumber;

                return isset($invalidSoKeys[$soKey]);
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | PASANG MASTER PRODUCT HANYA KE ROW VALID
        |--------------------------------------------------------------------------
        */

        $rows = $rows
            ->map(function ($row) use ($products) {
                $productCode = trim(
                    (string) $row->product_code
                );

                /** @var Product|null $product */
                $product = $products->get($productCode);

                if (! $product) {
                    return null;
                }

                $row->master_product = $product;
                $row->box_type = (string) $product->box_type;

                return $row;
            })
            ->filter()
            ->values();

        if ($rows->isEmpty()) {
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
                'demands' => demands,

                'invalid_items' => $invalidItems
                    ->unique(function (array $item): string {
                        return implode('::', [
                            $item['customer_id'] ?? '',
                            $item['so_number'] ?? '',
                            $item['product_code'] ?? '',
                            $item['reason'] ?? '',
                        ]);
                    })
                    ->values(),

                'invalid_so_count' => count($invalidSoKeys),

                'fingerprint' =>$fingerprint,
            ];
        }

        $demands = $rows
            ->groupBy(
                function ($row): string {

                    $customerId =
                        trim(
                            (string) $row->customerID
                        );

                    $boxType =
                        trim(
                            (string) $row->box_type
                        );

                    return
                        $customerId
                        . '::'
                        . $boxType;
                }
            )
            ->map(
                function (
                    Collection $demandRows,
                    string $groupKey
                ) use (
                    $branch
                ): array {

                    $first =
                        $demandRows->first();

                    if (! $first) {
                        throw new RuntimeException(
                            'Demand Sales Order kosong.'
                        );
                    }

                    $customerId =
                        trim(
                            (string)
                            $first->customerID
                        );

                    $boxType =
                        trim(
                            (string)
                            $first->box_type
                        );

                    return
                        $this->buildCustomerDemand(
                            branch:
                                $branch,

                            customerId:
                                $customerId,

                            boxType:
                                $boxType,

                            rows:
                                $demandRows,
                        );
                }
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

        $dateRange =
            $this->resolveSourceDateRange(
                $sourceDate
            );

        $rows = $this->querySourceRows(
            cabId: $cabId,
            startDate: $dateRange['start_date'],
            endDate: $dateRange['end_date'],
        );
        $rows = $this->unallocatedRows($branch, $rows);

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
                        'id' =>
                            (string) ($demand['id'] ?? ''),

                        'customer_id' =>
                            (string) ($demand['customer_id'] ?? ''),

                        'customer_name' =>
                            (string) ($demand['customer_name'] ?? ''),

                        'box_type' =>
                            (string) ($demand['box_type'] ?? ''),

                        'demand_volume_cm3' =>
                            round(
                                (float) ($demand['demand_volume_cm3'] ?? 0),
                                4
                            ),

                        'address' =>
                            (string) ($demand['address'] ?? ''),

                        'latitude' =>
                            round(
                                (float) ($demand['latitude'] ?? 0),
                                7
                            ),

                        'longitude' =>
                            round(
                                (float) ($demand['longitude'] ?? 0),
                                7
                            ),

                        'so_numbers' =>
                            collect(
                                $demand['so_numbers'] ?? []
                            )
                                ->map(
                                    fn ($value): string =>
                                        (string) $value
                                )
                                ->sort()
                                ->values()
                                ->all(),

                        'items' =>
                            collect(
                                $demand['items'] ?? []
                            )
                                ->map(
                                    fn (array $item): array => [
                                        'so_number' =>
                                            (string) ($item['so_number'] ?? ''),

                                        'product_code' =>
                                            (string) ($item['product_code'] ?? ''),

                                        'product_name' =>
                                            (string) ($item['product_name'] ?? ''),

                                        'box_type' =>
                                            (string) ($item['box_type'] ?? ''),

                                        'qty_cartons' =>
                                            (float) ($item['qty_cartons'] ?? 0),

                                        'qty_packs' =>
                                            (float) ($item['qty_packs'] ?? 0),

                                        'qty_pcs' =>
                                            (float) ($item['qty_pcs'] ?? 0),

                                        'units_per_carton' =>
                                            (int) ($item['units_per_carton'] ?? 0),

                                        'units_per_pack' =>
                                            isset($item['units_per_pack'])
                                                ? (int) $item['units_per_pack']
                                                : null,

                                        'total_pcs' =>
                                            (int) ($item['total_pcs'] ?? 0),

                                        'carton_volume_cm3' =>
                                            round(
                                                (float) ($item['carton_volume_cm3'] ?? 0),
                                                4
                                            ),

                                        'unit_volume_cm3' =>
                                            round(
                                                (float) ($item['unit_volume_cm3'] ?? 0),
                                                6
                                            ),

                                        'item_volume_cm3' =>
                                            round(
                                                (float) ($item['item_volume_cm3'] ?? 0),
                                                4
                                            ),
                                    ]
                                )
                                ->sortBy(
                                    fn (array $item): string =>
                                        implode(
                                            '|',
                                            [
                                                $item['so_number'],
                                                $item['product_code'],
                                                $item['box_type'],
                                            ]
                                        )
                                )
                                ->values()
                                ->all(),
                    ];
                }
            )
            ->sortBy(
                fn (array $demand): string =>
                    implode(
                        '|',
                        [
                            $demand['customer_id'],
                            $demand['box_type'],
                            $demand['id'],
                        ]
                    )
            )
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

    /**
     * SO pada stop route aktif tetap teralokasi walau route di-reschedule
     * atau belum di-approve. Takeout menghapus stop; route Cancelled
     * mengembalikan SO ke daftar tersedia.
     *
     * $rows harus sudah memiliki kolom `source_date` (diisi oleh querySourceRows).
     * Method ini mendukung backlog multi-tanggal: route yang dicek adalah semua
     * route yang source_date-nya muncul dalam rows, bukan hanya satu tanggal.
     */
    protected function unallocatedRows(
        Branch $branch,
        Collection $rows
    ): Collection {
        if ($rows->isEmpty()) {
            return $rows;
        }

        // Kumpulkan semua source_date yang muncul di rows backlog.
        $sourceDates = $rows
            ->pluck('source_date')
            ->map(
                fn ($value): string =>
                    Carbon::parse(
                        (string) $value
                    )->toDateString()
            )
            ->filter()
            ->unique()
            ->values();

        if ($sourceDates->isEmpty()) {
            return collect();
        }

        $allocated = [];
        $reservedCustomers = [];

        // Ambil semua route aktif yang source_date-nya termasuk dalam backlog window.
        // Route legacy (source_date NULL) tidak perlu dicek lagi karena semua route
        // baru sudah wajib menyimpan source_date.
        $routes = DeliveryRoute::query()
            ->with(['deliveryStops.store'])
            ->where('branch_id', $branch->id)
            ->whereIn('source_date', $sourceDates->all())
            ->where('status', '!=', RouteStatus::Cancelled->value)
            ->get();

        foreach ($routes as $route) {
            foreach ($route->deliveryStops as $stop) {
                $customer = trim((string) ($stop->source_customer_code ?: $stop->store?->code));
                if ($customer === '') {
                    continue;
                }

                $boxType =
                    trim(
                        (string) (
                            $stop->box_type
                            ?? ''
                        )
                    );

                $numbers = $stop->source_so_numbers;
                if (is_string($numbers)) {
                    $numbers = json_decode($numbers, true);
                }

                // Stop lama tanpa daftar nomor SO: tahan customer agar tidak
                // membuat pengiriman duplikat dari data legacy.
                if (! is_array($numbers) || $numbers === [] || $boxType === '') {
                    $reservedCustomers[$customer] = true;
                    continue;
                }

                foreach ($numbers as $number) {
                    $number = trim((string) $number);
                    if ($number === '') {
                        continue;
                    }

                    $allocated[$customer][$boxType][$number] = true;
                }
            }
        }

        // Reschedule menghapus stop dan menyimpan antrean active pada action.
        // Karena action saat ini belum menyimpan nomor SO, tahan semua SO
        // customer tersebut pada seluruh tanggal yang ada dalam backlog window.
        $rescheduledCustomers = DeliveryStopAction::query()
            ->where('branch_id', $branch->id)
            ->whereIn('source_date', $sourceDates->all())
            ->where('action_type', 'reschedule')
            ->where('action_status', 'active')
            ->pluck('customer_code');

        foreach ($rescheduledCustomers as $customer) {
            $customer = trim((string) $customer);
            if ($customer !== '') {
                $reservedCustomers[$customer] = true;
            }
        }

        return $rows->reject(function ($row) use ($allocated, $reservedCustomers): bool {
            $customer = trim((string) $row->customerID);
            $number = trim((string) $row->so_number);
            $boxType = trim((string) ($row->box_type ?? ''));

            if (isset($reservedCustomers[$customer])) {
                return true;
            }

            return isset($allocated[$customer][$boxType][$number]);
        })->values();
    }

    protected function querySourceRows(
        string $cabId,
        string $startDate,
        string $endDate
    ): Collection {
        $db = $this->sourceDatabase();
        $soTable = $this->sourceSoTable();
        $customerTable = $this->sourceCustomerTable();
        $orderDateColumn = $this->sourceOrderDateColumn();
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
            ->whereBetween(
                'so.' . $orderDateColumn,
                [
                    $startDate,
                    $endDate,
                ]
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
                DB::raw(
                    'DATE(so.'
                    . $orderDateColumn
                    . ') as source_date'
                ),
                'so.so_number',
                'so.vendor_name',
                'so.product_id as product_code',
                'so.product_name as product_name',
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
        string $customerId,
        string $boxType,
        Collection $rows
    ): array {
        if ($customerId === '') {
            throw new RuntimeException(
                'Ditemukan Sales Order dengan customerID kosong.'
            );
        }

        if (
            ! in_array(
                $boxType,
                [
                    'dry',
                    'cold_storage',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                "Box type demand customer {$customerId} tidak valid."
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
                fn($row): string =>
                    implode('|', [
                        (string)$row->so_number,
                        (string)$row->product_code,
                    ])
            )
            ->map(
                function (Collection $itemRows): array {
                    $first = $itemRows->first();

                    if (!$first) {
                        throw new RuntimeException(
                            'Item Sales Order kosong.'
                        );
                    }

                    /** @var Product|null $product */
                    $product = $first->master_product ?? null;

                    if (!$product) {
                        throw new RuntimeException(
                            'Master produk tidak ditemukan saat membangun demand.'
                        );
                    }

                    $qtyCartons = (int) $this->sumNumeric(
                        $itemRows,
                        'qty_cartons'
                    );

                    $qtyPacks = (int) $this->sumNumeric(
                        $itemRows,
                        'qty_packs'
                    );

                    $qtyPcs = (int) $this->sumNumeric(
                        $itemRows,
                        'qty_pcs'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | VALIDASI PACK / TOPLES
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $qtyPacks > 0
                        &&
                        (
                            !$product->units_per_pack
                            ||
                            (int)$product->units_per_pack <= 0
                        )
                    ) {
                        throw new RuntimeException(
                            "Produk {$product->code} memiliki qty pack/toples "
                            . 'pada SO tetapi units_per_pack belum diisi.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | MASTER VOLUME
                    |--------------------------------------------------------------------------
                    |
                    | products.carton_volume_cm3 dan unit_volume_cm3
                    | sudah disimpan dalam cm3.
                    |
                    */

                    $cartonVolumeCm3 =
                        (float) $product->carton_volume_cm3;

                    $unitVolumeCm3 =
                        (float) $product->unit_volume_cm3;

                    /*
                    |--------------------------------------------------------------------------
                    | TOTAL PCS
                    |--------------------------------------------------------------------------
                    */

                    $totalPcs =
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

                    /*
                    |--------------------------------------------------------------------------
                    | TOTAL VOLUME ITEM
                    |--------------------------------------------------------------------------
                    */

                    $cartonVolumeTotalCm3 =
                        $qtyCartons
                        *
                        $cartonVolumeCm3;

                    $packVolumeTotalCm3 =
                        $qtyPacks
                        *
                        (int) ($product->units_per_pack ?? 0)
                        *
                        $unitVolumeCm3;

                    $pcsVolumeTotalCm3 =
                        $qtyPcs
                        *
                        $unitVolumeCm3;

                    $itemVolumeCm3 =
                        $cartonVolumeTotalCm3
                        +
                        $packVolumeTotalCm3
                        +
                        $pcsVolumeTotalCm3;

                    return [
                        'so_number' =>
                            (string) $first->so_number,

                        'product_code' =>
                            (string) $product->code,

                        'product_name' =>
                            (string) (
                                $first->product_name
                                ?? $product->name
                            ),

                        'box_type' =>
                            (string) $product->box_type,

                        'qty_cartons' =>
                            $qtyCartons,

                        'qty_packs' =>
                            $qtyPacks,

                        'qty_pcs' =>
                            $qtyPcs,

                        'units_per_carton' =>
                            (int) $product->units_per_carton,

                        'units_per_pack' =>
                            (
                                $product->units_per_pack !== null
                                && (int) $product->units_per_pack > 0
                            )
                                ? (int) $product->units_per_pack
                                : null,

                        'total_pcs' =>
                            $totalPcs,

                        'carton_volume_cm3' =>
                            round(
                                $cartonVolumeCm3,
                                4
                            ),

                        'unit_volume_cm3' =>
                            round(
                                $unitVolumeCm3,
                                6
                            ),

                        'item_volume_cm3' =>
                            round(
                                $itemVolumeCm3,
                                4
                            ),
                    ];
                }
            )
            ->values()
            ->all();

        $demandVolumeCm3 =
            collect($items)
                ->sum(
                    fn (array $item): float =>
                        (float)
                        $item['item_volume_cm3']
                );

        $cabId = $this->resolveSourceCabId($branch);

        $soNumbers = $rows
            ->pluck('so_number')
            ->filter()
            ->map(fn ($value): string => trim((string) $value))
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Demand ID mewakili membership SO-nya, bukan tanggal SO.
        // Dengan ini, backlog dari beberapa tanggal yang masuk ke satu
        // demand menghasilkan ID yang stabil selama kumpulan SO-nya sama.
        $demandId = implode(
            '::',
            [
                $cabId,
                $customerId,
                $boxType,
                substr(
                    hash(
                        'sha256',
                        implode('|', $soNumbers)
                    ),
                    0,
                    16
                ),
            ]
        );

        $sourceDates = $rows
            ->pluck('source_date')
            ->map(
                fn ($value): string =>
                    Carbon::parse(
                        (string) $value
                    )->toDateString()
            )
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

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
            'box_type' => $boxType,

            'demand_volume_cm3' =>
                round(
                    $demandVolumeCm3,
                    4
                ),

            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,

            'service_duration_minutes' =>
                self::DEFAULT_SERVICE_DURATION_MINUTES,

            // Tanggal asli tiap SO dalam demand ini (bisa lintas hari).
            'source_dates' => $sourceDates,

            'so_numbers' => $soNumbers,

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

    protected function resolveSourceDateRange(
        string $sourceDate
    ): array {
        $endDate = Carbon::parse(
            $sourceDate
        )
            ->startOfDay();

        $startDate = $endDate
            ->copy()
            ->subDays(
                self::DEFAULT_BACKLOG_DAYS
            );

        return [
            'start_date' =>
                $startDate->toDateString(),

            'end_date' =>
                $endDate->toDateString(),
        ];
    }
}
