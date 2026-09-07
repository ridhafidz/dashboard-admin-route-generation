<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\BoxType;
use App\Enums\ProductStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Produk')
                ->description(
                    'Produk aktif dapat dipilih ketika admin membuat Sales Order.'
                )
                ->schema([
                    TextInput::make('code')
                        ->label('Kode Produk')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(
                            fn (?string $state): string =>
                                Str::upper(
                                    trim((string) $state)
                                )
                        ),

                    TextInput::make('name')
                        ->label('Nama Produk')
                        ->required()
                        ->maxLength(255),

                    Select::make('box_type')
                        ->label('Jenis / Tipe Box')
                        ->options(
                            collect(BoxType::cases())
                                ->mapWithKeys(
                                    fn (BoxType $case) => [
                                        $case->value =>
                                            $case->getLabel(),
                                    ]
                                )
                        )
                        ->helperText(
                            'Produk Dry hanya boleh dibawa box Dry. '
                            . 'Produk Cold Storage hanya boleh dibawa '
                            . 'box Cold Storage.'
                        )
                        ->required(),

                    TextInput::make('units_per_carton')
                        ->label('Isi PCS per Karton/PAX')
                        ->integer()
                        ->minValue(1)
                        ->default(1)
                        ->required(),

                    Select::make('status')
                        ->label('Status Produk')
                        ->options(
                            collect(ProductStatus::cases())
                                ->mapWithKeys(
                                    fn (ProductStatus $case) => [
                                        $case->value =>
                                            $case->getLabel(),
                                    ]
                                )
                        )
                        ->default(
                            ProductStatus::Active->value
                        )
                        ->required(),
                ])
                ->columns(2),

            Section::make('Berat dan Volume')
                ->description(
                    'Volume menggunakan satuan meter kubik (m³). '
                    . 'Berat dicatat sebagai informasi operasional, '
                    . 'sedangkan kapasitas kendaraan nantinya '
                    . 'ditentukan berdasarkan volume.'
                )
                ->schema([
                    TextInput::make('unit_weight_kg')
                        ->label('Berat per PCS')
                        ->numeric()
                        ->step(0.0001)
                        ->minValue(0.0001)
                        ->suffix('kg')
                        ->required(),

                    TextInput::make('carton_weight_kg')
                        ->label('Berat per Karton/PAX')
                        ->numeric()
                        ->step(0.0001)
                        ->minValue(0.0001)
                        ->suffix('kg')
                        ->helperText(
                            'Masukkan berat aktual karton '
                            . 'termasuk berat kemasannya.'
                        )
                        ->required(),

                    TextInput::make('unit_volume_m3')
                        ->label('Volume per PCS')
                        ->numeric()
                        ->step(0.00000001)
                        ->minValue(0.00000001)
                        ->suffix('m³')
                        ->required(),

                    TextInput::make('carton_volume_m3')
                        ->label('Volume per Karton/PAX')
                        ->numeric()
                        ->step(0.000001)
                        ->minValue(0.000001)
                        ->suffix('m³')
                        ->helperText(
                            'Gunakan volume aktual karton: '
                            . 'panjang × lebar × tinggi dalam meter.'
                        )
                        ->required(),
                ])
                ->columns(2),

            Section::make('Harga Opsional')
                ->description(
                    'Harga boleh dikosongkan. Harga masih dapat '
                    . 'disesuaikan pada masing-masing Sales Order.'
                )
                ->schema([
                    TextInput::make('unit_price')
                        ->label('Harga per PCS')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp')
                        ->nullable(),

                    TextInput::make('carton_price')
                        ->label('Harga per Karton/PAX')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp')
                        ->nullable(),
                ])
                ->columns(2),
        ]);
    }
}