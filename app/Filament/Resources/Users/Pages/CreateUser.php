<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\DriverStatus;
use App\Filament\Concerns\RedirectsToIndex;
use App\Filament\Resources\Users\UserResource;
use App\Models\Driver;
use App\Services\BranchContext;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = UserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function afterCreate(): void
    {
        $user = $this->record;

        // Hanya buat profil Driver jika role user adalah Driver.
        if (! $user->hasRole('driver')) {
            return;
        }

        $branchId = app(BranchContext::class)->getId();

        if (! $branchId) {
            throw new \RuntimeException(
                'Tidak ada cabang aktif. Pilih cabang terlebih dahulu.'
            );
        }

        Driver::firstOrCreate(
            [
                'user_id' => $user->id,
            ],
            [
                'branch_id' => $branchId,
                'name' => $user->name,
                'status' => DriverStatus::Inactive,
            ]
        );
    }
}