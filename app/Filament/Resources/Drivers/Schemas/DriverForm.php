<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Enums\DriverStatus;
use App\Models\User;
use App\Services\BranchContext;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | BRANCH
                |--------------------------------------------------------------------------
                |
                | Driver otomatis masuk ke cabang
                | yang sedang aktif di admin.
                |
                */

                Hidden::make('branch_id')
                    ->default(
                        fn (): ?int =>
                            app(BranchContext::class)
                                ->getId()
                    )
                    ->dehydrated()
                    ->required(),


                /*
                |--------------------------------------------------------------------------
                | AKUN LOGIN
                |--------------------------------------------------------------------------
                |
                | Yang ditampilkan = email.
                |
                | Yang disimpan = users.id -> drivers.user_id
                |
                | Tidak ada auto-fill nama / phone.
                |
                */

                Select::make('user_id')
                    ->label('Akun Login')

                    ->options(
                        fn (): array =>
                            User::query()
                                ->role('driver')
                                ->orderBy('email')
                                ->pluck(
                                    'email',
                                    'id'
                                )
                                ->all()
                    )

                    ->searchable()
                    ->preload()

                    /*
                     * Satu akun hanya boleh
                     * mempunyai satu Driver.
                     */
                    ->unique(
                        table: 'drivers',
                        column: 'user_id',
                        ignoreRecord: true
                    )

                    ->validationMessages([
                        'unique' =>
                            'Email akun ini sudah terdaftar sebagai Driver.',
                    ])

                    ->required(),


                /*
                |--------------------------------------------------------------------------
                | NAME
                |--------------------------------------------------------------------------
                |
                | Input manual oleh admin.
                |
                */

                TextInput::make('name')
                    ->label('Name')
                    ->maxLength(255)
                    ->required(),


                /*
                |--------------------------------------------------------------------------
                | PHONE
                |--------------------------------------------------------------------------
                */

                TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(20),


                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                Select::make('status')
                    ->label('Status')
                    ->options(
                        collect(
                            DriverStatus::cases()
                        )
                            ->mapWithKeys(
                                fn ($case) => [
                                    $case->value =>
                                        $case->getLabel(),
                                ]
                            )
                    )
                    ->required(),
            ]);
    }
}