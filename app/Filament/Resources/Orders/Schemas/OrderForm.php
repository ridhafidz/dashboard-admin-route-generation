<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\BoxType;
use App\Enums\OrderStatus;
use App\Enums\PackageStatus;
use App\Enums\ProductStatus;
use App\Enums\SalesUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\BranchContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrderForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema->components([
            Section::make('Informasi Sales Order')
                ->description(
                    'Pilih toko terlebih dahulu. '
                    . 'Alamat dan koordinat akan diambil '
                    . 'otomatis dari master toko.'
                )
                ->schema([
                    Select::make('store_id')
                        ->label('Nama Toko')
                        ->relationship(
                            name: 'store',
                            titleAttribute: 'name',
                            modifyQueryUsing:
                                function (
                                    Builder $query
                                ): Builder {
                                    $branchId = app(
                                        BranchContext::class
                                    )->getId();

                                    if ($branchId) {
                                        $query->whereHas(
                                            'area',
                                            fn (
                                                Builder $query
                                            ): Builder =>
                                                $query->where(
                                                    'branch_id',
                                                    $branchId
                                                )
                                        );
                                    }

                                    return $query
                                        ->orderBy('name');
                                }
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (
                                Store $record
                            ): string =>
                                "{$record->code} - "
                                . $record->name
                        )
                        ->searchable([
                            'code',
                            'name',
                            'address',
                        ])
                        ->preload()
                        ->live()
                        ->afterStateUpdated(
                            fn (
                                mixed $state,
                                Set $set
                            ): mixed =>
                                self::fillStoreSnapshot(
                                    $state,
                                    $set
                                )
                        )
                        ->required(),

                    TextInput::make('order_number')
                        ->label('Nomor Sales Order')
                        ->required()
                        ->maxLength(255)
                        ->unique(
                            ignoreRecord: true
                        ),

                    DatePicker::make('order_date')
                        ->label('Tanggal Order')
                        ->default(today())
                        ->required(),

                    DatePicker::make(
                        'scheduled_date'
                    )
                        ->label('Tanggal Pengiriman')
                        ->default(today())
                        ->required(),

                    Select::make('status')
                        ->label('Status Order')
                        ->options(
                            collect(
                                OrderStatus::cases()
                            )->mapWithKeys(
                                fn (
                                    OrderStatus $case
                                ) => [
                                    $case->value =>
                                        $case->getLabel(),
                                ]
                            )
                        )
                        ->default(
                            OrderStatus::Pending->value
                        )
                        ->required(),

                    Textarea::make(
                        'delivery_address'
                    )
                        ->label('Alamat Pengiriman')
                        ->rows(3)
                        ->readOnly()
                        ->columnSpanFull(),

                    TextInput::make(
                        'delivery_latitude'
                    )
                        ->label('Latitude')
                        ->readOnly(),

                    TextInput::make(
                        'delivery_longitude'
                    )
                        ->label('Longitude')
                        ->readOnly(),

                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->disabled(
                    fn (
                        ?Order $record
                    ): bool =>
                        self::isOrderLocked(
                            $record
                        )
                ),

            Section::make(
                'Detail Produk Sales Order'
            )
                ->description(
                    'Satu baris mewakili satu produk. '
                    . 'Berat, volume, tipe box, dan '
                    . 'total harga dihitung otomatis.'
                )
                ->schema([
                    Repeater::make('packages')
                        ->label('Daftar Produk')
                        ->relationship()
                        ->schema([
                            Select::make(
                                'product_id'
                            )
                                ->label('Produk')
                                ->relationship(
                                    name: 'product',
                                    titleAttribute:
                                        'name',
                                    modifyQueryUsing:
                                        fn (
                                            Builder $query
                                        ): Builder =>
                                            $query
                                                ->where(
                                                    'status',
                                                    ProductStatus
                                                        ::Active
                                                        ->value
                                                )
                                                ->orderBy(
                                                    'name'
                                                )
                                )
                                ->getOptionLabelFromRecordUsing(
                                    fn (
                                        Product $record
                                    ): string =>
                                        "{$record->code} - "
                                        . $record->name
                                )
                                ->searchable([
                                    'code',
                                    'name',
                                ])
                                ->preload()
                                ->live()
                                ->afterStateUpdated(
                                    function (
                                        Get $get,
                                        Set $set
                                    ): void {
                                        self::refreshProductLine(
                                            $get,
                                            $set,
                                            replacePrice: true
                                        );
                                    }
                                )
                                ->columnSpan(5)
                                ->required(),

                            Select::make('uom')
                                ->label('Satuan')
                                ->options(
                                    collect(
                                        SalesUnit::cases()
                                    )->mapWithKeys(
                                        fn (
                                            SalesUnit $case
                                        ) => [
                                            $case->value =>
                                                $case
                                                    ->getLabel(),
                                        ]
                                    )
                                )
                                ->default(
                                    SalesUnit::Pcs->value
                                )
                                ->live()
                                ->afterStateUpdated(
                                    function (
                                        Get $get,
                                        Set $set
                                    ): void {
                                        self::refreshProductLine(
                                            $get,
                                            $set,
                                            replacePrice: true
                                        );
                                    }
                                )
                                ->columnSpan(3)
                                ->required(),

                            TextInput::make('quantity')
                                ->label('Jumlah')
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(
                                    function (
                                        Get $get,
                                        Set $set
                                    ): void {
                                        self::refreshProductLine(
                                            $get,
                                            $set
                                        );
                                    }
                                )
                                ->columnSpan(2)
                                ->required(),

                            Select::make('box_type')
                                ->label('Tipe Box')
                                ->options(
                                    collect(
                                        BoxType::cases()
                                    )->mapWithKeys(
                                        fn (
                                            BoxType $case
                                        ) => [
                                            $case->value =>
                                                $case
                                                    ->getLabel(),
                                        ]
                                    )
                                )
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),

                            /*
                             * Digunakan untuk label Repeater.
                             * Nilai sebenarnya tetap ditetapkan
                             * ulang oleh Package model.
                             */
                            Hidden::make('item')
                                ->dehydrated(false),

                            TextInput::make(
                                'unit_weight_kg'
                            )
                                ->label(
                                    'Berat per Satuan'
                                )
                                ->suffix('kg')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(3),

                            TextInput::make('weight_kg')
                                ->label('Total Berat')
                                ->suffix('kg')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(3),

                            TextInput::make(
                                'unit_volume_m3'
                            )
                                ->label(
                                    'Volume per Satuan'
                                )
                                ->suffix('m³')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(3),

                            TextInput::make('volume_m3')
                                ->label('Total Volume')
                                ->suffix('m³')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(3),

                            TextInput::make(
                                'unit_price'
                            )
                                ->label(
                                    'Harga per Satuan'
                                )
                                ->numeric()
                                ->minValue(0)
                                ->prefix('Rp')
                                ->nullable()
                                ->live(onBlur: true)
                                ->afterStateUpdated(
                                    function (
                                        Get $get,
                                        Set $set
                                    ): void {
                                        self::refreshLineTotals(
                                            $get,
                                            $set
                                        );
                                    }
                                )
                                ->columnSpan(4),

                            TextInput::make(
                                'total_price'
                            )
                                ->label('Total Harga')
                                ->prefix('Rp')
                                ->readOnly()
                                ->dehydrated(false)
                                ->columnSpan(4),

                            Select::make('status')
                                ->label('Status Item')
                                ->options(
                                    collect(
                                        PackageStatus::cases()
                                    )->mapWithKeys(
                                        fn (
                                            PackageStatus $case
                                        ) => [
                                            $case->value =>
                                                $case
                                                    ->getLabel(),
                                        ]
                                    )
                                )
                                ->default(
                                    PackageStatus::Pending->value
                                )
                                ->required()
                                ->columnSpan(4),
                        ])
                        ->columns(12)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel(
                            'Tambah Produk'
                        )
                        ->itemLabel(
                            fn (
                                array $state
                            ): string =>
                                filled(
                                    $state['item'] ?? null
                                )
                                    ? (string)
                                        $state['item']
                                    : 'Produk belum dipilih'
                        )
                        ->collapsible()
                        ->cloneable()
                        ->disabled(
                            fn (
                                ?Order $record
                            ): bool =>
                                self::isOrderLocked(
                                    $record
                                )
                        )
                        ->columnSpanFull(),

                    Placeholder::make(
                        'sales_order_summary'
                    )
                        ->label(
                            'Ringkasan Sales Order'
                        )
                        ->content(
                            function (
                                Get $get
                            ): string {
                                $lines = collect(
                                    $get('packages') ?? []
                                );

                                $lineCount =
                                    $lines->count();

                                $totalQuantity =
                                    $lines->sum(
                                        fn (
                                            array $line
                                        ): int =>
                                            (int) (
                                                $line[
                                                    'quantity'
                                                ] ?? 0
                                            )
                                    );

                                $totalWeight =
                                    $lines->sum(
                                        fn (
                                            array $line
                                        ): float =>
                                            (float) (
                                                $line[
                                                    'weight_kg'
                                                ] ?? 0
                                            )
                                    );

                                $totalVolume =
                                    $lines->sum(
                                        fn (
                                            array $line
                                        ): float =>
                                            (float) (
                                                $line[
                                                    'volume_m3'
                                                ] ?? 0
                                            )
                                    );

                                $totalPrice =
                                    $lines->sum(
                                        fn (
                                            array $line
                                        ): float =>
                                            (float) (
                                                $line[
                                                    'total_price'
                                                ] ?? 0
                                            )
                                    );

                                return sprintf(
                                    '%d produk | '
                                    . '%d kuantitas | '
                                    . '%.4f kg | '
                                    . '%.6f m³ | '
                                    . 'Rp %s',
                                    $lineCount,
                                    $totalQuantity,
                                    $totalWeight,
                                    $totalVolume,
                                    number_format(
                                        $totalPrice,
                                        2,
                                        ',',
                                        '.'
                                    )
                                );
                            }
                        )
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected static function fillStoreSnapshot(
        mixed $storeId,
        Set $set
    ): mixed {
        if (blank($storeId)) {
            $set(
                'delivery_address',
                null
            );

            $set(
                'delivery_latitude',
                null
            );

            $set(
                'delivery_longitude',
                null
            );

            return null;
        }

        $store = Store::query()->find(
            $storeId
        );

        if (! $store) {
            $set(
                'delivery_address',
                null
            );

            $set(
                'delivery_latitude',
                null
            );

            $set(
                'delivery_longitude',
                null
            );

            return null;
        }

        $set(
            'delivery_address',
            $store->address
        );

        $set(
            'delivery_latitude',
            $store->latitude
        );

        $set(
            'delivery_longitude',
            $store->longitude
        );

        return $storeId;
    }

    protected static function refreshProductLine(
        Get $get,
        Set $set,
        bool $replacePrice = false
    ): void {
        $productId = $get(
            'product_id'
        );

        if (blank($productId)) {
            self::clearProductLine(
                $set
            );

            return;
        }

        $product = Product::query()->find(
            $productId
        );

        if (! $product) {
            self::clearProductLine(
                $set
            );

            return;
        }

        $uom = SalesUnit::tryFrom(
            (string) $get('uom')
        ) ?? SalesUnit::Pcs;

        $isCarton =
            $uom === SalesUnit::Carton;

        $unitWeight = (float) (
            $isCarton
                ? $product->carton_weight_kg
                : $product->unit_weight_kg
        );

        $unitVolume = (float) (
            $isCarton
                ? $product->carton_volume_m3
                : $product->unit_volume_m3
        );

        $defaultPrice = $isCarton
            ? $product->carton_price
            : $product->unit_price;

        $set(
            'item',
            $product->name
        );

        $set(
            'box_type',
            $product->box_type->value
        );

        $set(
            'unit_weight_kg',
            self::decimal(
                $unitWeight,
                4
            )
        );

        $set(
            'unit_volume_m3',
            self::decimal(
                $unitVolume,
                8
            )
        );

        if (
            $replacePrice
            || blank(
                $get('unit_price')
            )
        ) {
            $set(
                'unit_price',
                $defaultPrice !== null
                    ? self::decimal(
                        (float) $defaultPrice,
                        2
                    )
                    : null
            );
        }

        self::refreshLineTotals(
            $get,
            $set
        );
    }

    protected static function refreshLineTotals(
        Get $get,
        Set $set
    ): void {
        $quantity = max(
            0,
            (int) (
                $get('quantity') ?? 0
            )
        );

        $unitWeight = (float) (
            $get('unit_weight_kg') ?? 0
        );

        $unitVolume = (float) (
            $get('unit_volume_m3') ?? 0
        );

        $unitPrice = $get(
            'unit_price'
        );

        $set(
            'weight_kg',
            self::decimal(
                $quantity * $unitWeight,
                4
            )
        );

        $set(
            'volume_m3',
            self::decimal(
                $quantity * $unitVolume,
                6
            )
        );

        $set(
            'total_price',
            filled($unitPrice)
                ? self::decimal(
                    $quantity
                    * (float) $unitPrice,
                    2
                )
                : null
        );
    }

    protected static function clearProductLine(
        Set $set
    ): void {
        foreach ([
            'item',
            'box_type',
            'unit_weight_kg',
            'unit_volume_m3',
            'weight_kg',
            'volume_m3',
            'unit_price',
            'total_price',
        ] as $field) {
            $set(
                $field,
                null
            );
        }
    }

    protected static function decimal(
        float $value,
        int $precision
    ): string {
        return number_format(
            $value,
            $precision,
            '.',
            ''
        );
    }

    protected static function isOrderLocked(
        ?Order $record
    ): bool {
        if (! $record) {
            return false;
        }

        /*
         * Order dikunci ketika salah satu item sudah masuk route.
         */
        return $record
            ->packages()
            ->whereIn(
                'status',
                [
                    PackageStatus::Assigned->value,
                    PackageStatus::Delivered->value,
                ]
            )
            ->exists();
    }
}