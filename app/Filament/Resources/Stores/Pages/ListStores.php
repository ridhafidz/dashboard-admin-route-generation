<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Models\Branch;
use App\Models\CustomerSource;
use App\Services\BranchContext;
use App\Services\CustomerSourceService;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    /**
     * Customer master berasal dari database source sehingga halaman ini READ ONLY.
     * Tidak ada Create Store manual dari UI.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | TABLE QUERY - CUSTOMER SOURCE
    |--------------------------------------------------------------------------
    |
    | Hanya mengambil field yang diperlukan:
    | customerID, customerName, addressLine1, city, phone, taxName,
    | latitude, longitude, area, subArea (+ cabID sebagai field teknis).
    |
    | Tidak memakai SELECT *.
    |
    */
    protected function getTableQuery(): ?Builder
    {
        $query = CustomerSource::query()
            ->select(CustomerSourceService::sourceColumns());

        $branchId = app(BranchContext::class)->getId();

        if (! $branchId) {
            return $query;
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch) {
            return $query->whereRaw('1 = 0');
        }

        $branchCode = app(CustomerSourceService::class)
            ->resolveSourceBranchCode($branch);

        /*
        |---------------------------------------------------------------------
        | PREP INTEGRASI SISTEM UTAMA - MAPPING CABANG
        |---------------------------------------------------------------------
        |
        | Stores memakai Branch.cab_id, sama seperti DeliverySourceService.
        | Jangan memakai init_cab secara terpisah di halaman ini.
        |
        | Jika mapping cabang source berubah nanti, ubah resolver terpusat,
        | bukan query Filament ini.
        |
        */
        if ($branchCode === null) {
            // Ada branch aktif tetapi belum mempunyai mapping CAB source.
            // Tampilkan 0 customer agar tidak bocor menampilkan cabang lain.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            CustomerSourceService::COL_BRANCH_CODE,
            $branchCode
        );
    }
}
