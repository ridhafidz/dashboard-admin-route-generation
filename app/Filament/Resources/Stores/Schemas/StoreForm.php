<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Services\AreaAssignmentService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('code')->required()->unique(ignoreRecord: true),
            Textarea::make('address')->required()->columnSpanFull(),

            TextInput::make('latitude')
                ->numeric()
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshAreaPreview($get, $set)),

            TextInput::make('longitude')
                ->numeric()
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshAreaPreview($get, $set)),

            Placeholder::make('area_preview')
                ->label('Area (otomatis)')
                ->content(fn (Get $get) => $get('area_preview_text') ?? 'Isi latitude & longitude dulu'),

            Hidden::make('area_preview_text'),

            TimePicker::make('opening_time')
                ->label('Jam Buka')
                ->seconds(false),

            TimePicker::make('closing_time')
                ->label('Jam Tutup')
                ->seconds(false),

        ]);
    }

    protected static function refreshAreaPreview(Get $get, Set $set): void
    {
        $lat = $get('latitude');
        $lng = $get('longitude');

        if (!$lat || !$lng) {
            return;
        }

        $preview = app(AreaAssignmentService::class)->preview((float) $lat, (float) $lng);

        if (!$preview['branch']) {
            $set('area_preview_text', 'Tidak ditemukan cabang dengan koordinat valid');
            return;
        }

        $status = $preview['is_new'] ? 'area baru akan dibuat' : 'area sudah ada';
        $set('area_preview_text', "{$preview['area_code']} ({$status}) — Cabang {$preview['branch']->name}");
    }
}