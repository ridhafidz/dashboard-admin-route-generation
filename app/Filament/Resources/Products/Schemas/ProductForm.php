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
                        ->required(),

                    TextInput::make('units_per_carton')
                        ->label('Isi PCS per Karton/PAX')
                        ->integer()
                        ->minValue(1)
                        ->default(1)
                        ->required(),

                    TextInput::make('units_per_pack')
                        ->label('Isi PCS per Pack')
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                ])
                ->columns(2),

            Section::make('Volume PCS dan Karton')
                ->description(
                    'Volume PCS (Product) menggunakan satuan meter kubik (cm³). '
                    . 'Volume Karton (Box) menggunakan satuan meter kubik (cm³). '
                    . 'Gunakan volume aktual karton: panjang × lebar × tinggi dalam meter.'
                )
                ->schema([
                    TextInput::make('carton_length_cm')
                        ->label('Panjang Karton (cm)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->suffix('cm'),

                    TextInput::make('carton_width_cm')
                        ->label('Lebar Karton (cm)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->suffix('cm'),

                    TextInput::make('carton_height_cm')
                        ->label('Tinggi Karton (cm)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->suffix('cm'),
                ])
                ->columns(2),

            Section::make('Volume PCS dan Karton')
                ->description(
                    'Volume PCS (Product) menggunakan satuan meter kubik (cm³). '
                    . 'Volume Karton (Box) menggunakan satuan meter kubik (cm³). '
                    . 'Gunakan volume aktual karton: panjang × lebar × tinggi dalam meter.'
                )
                ->schema([
                    TextInput::make('unit_volume_cm3')
                        ->label('Volume per PCS')
                        ->numeric()
                        ->step(0.00000001)
                        ->minValue(0.00000001)
                        ->suffix('cm³')
                        ->helperText(
                            'Volume Karton / Jumlah PCS Product.'
                        )
                        ->required(),

                    TextInput::make('carton_volume_cm3')
                        ->label('Volume per Karton/PAX')
                        ->numeric()
                        ->step(0.000001)
                        ->minValue(0.000001)
                        ->suffix('cm³')
                        ->helperText(
                            'Gunakan volume aktual karton: '
                            . 'panjang × lebar × tinggi dalam centimeter.'
                        )
                        ->required(),
                ])
                ->columns(2),
        ]);
    }
}