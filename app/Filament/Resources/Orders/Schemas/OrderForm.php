<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Services\BranchContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                                        $query->where(
                                            'branch_id',
                                            $branchId
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

    protected static function isOrderLocked(
        ?Order $record
    ): bool {
        return false;
    }
}