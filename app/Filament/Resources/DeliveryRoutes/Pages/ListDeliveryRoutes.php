<?php

namespace App\Filament\Resources\DeliveryRoutes\Pages;

use App\Enums\DriverStatus;
use App\Enums\RouteStatus;
use App\Enums\VehicleStatus;
use App\Filament\Resources\DeliveryRoutes\DeliveryRouteResource;
use App\Models\Branch;
use App\Models\DeliveryRoute;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\BranchContext;
use App\Services\DeliverySourceService;
use App\Services\RouteGenerationService;
use App\Services\RouteControlTowerService;
use App\Services\RouteControlTowerActionService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

class ListDeliveryRoutes extends ListRecords
{
    protected static string $resource = DeliveryRouteResource::class;

    protected string $view =
        'filament.resources.delivery-routes.pages.list-delivery-routes';

    public ?string $mapDate = null;
    public int $mapRevision = 0;

    public ?int $manualRerouteStopId = null;
    public ?string $manualRerouteStoreName = null;
    public ?int $manualRerouteSourceRouteId = null;
    public array $manualRerouteTargets = [];
    public ?string $manualRerouteError = null;

    public function mount(): void
    {
        parent::mount();

        $dates = app(RouteControlTowerService::class)->availableDates();
        $this->mapDate = array_key_first($dates) ?: today()->toDateString();
    }

    public function updatedMapDate(): void
    {
        $this->mapRevision++;
    }

    /*
    |--------------------------------------------------------------------------
    | TABLE QUERY
    |--------------------------------------------------------------------------
    */
    protected function getTableQuery(): ?Builder
    {
        $query = DeliveryRoute::query()
            ->from('vw_delivery_route_summary as delivery_routes');

        $branchId = app(BranchContext::class)->getId();

        if ($branchId) {
            $query->where('delivery_routes.branch_id', $branchId);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateRoute')
                ->label('Generate Route')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->modalHeading('Generate Route dari Sales Order')
                ->modalDescription(
                    'Pilih tanggal Sales Order masuk. Route otomatis dijadwalkan H+2 '
                    . 'dari tanggal tersebut. Contoh: SO masuk 7 September -> route 9 September.'
                )
                ->modalSubmitActionLabel('Generate Route')
                ->modalCancelActionLabel('Batal')
                ->modalWidth('2xl')
                ->form([
                    Select::make('branch_id')
                        ->label('Cabang')
                        ->options(
                            Branch::query()
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(
                                    fn (Branch $branch) => [
                                        $branch->id => "{$branch->init_cab} - {$branch->name}",
                                    ]
                                )
                        )
                        ->default(app(BranchContext::class)->getId())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),

                    DatePicker::make('source_date')
                        ->label('Tanggal SO Masuk')
                        ->helperText('Menggunakan kolom so_sda.visit_date.')
                        ->default(today()->toDateString())
                        ->displayFormat('d-m-Y')
                        ->native(false)
                        ->live()
                        ->required(),

                    Section::make('Preview')
                        ->description(
                            'Preview Sales Order pada tanggal yang dipilih dan resource '
                            . 'untuk jadwal pengiriman H+2.'
                        )
                        ->schema([
                            TextEntry::make('route_date_preview')
                                ->label('Jadwal Route')
                                ->state(function (Get $get) {
                                    $sourceDate = $get('source_date');

                                    if (! $sourceDate) {
                                        return '-';
                                    }

                                    return $this->calculateRouteDate($sourceDate)
                                        ->format('d-m-Y');
                                })
                                ->badge()
                                ->color('gray'),

                            TextEntry::make('so_count')
                                ->label('Total SO')
                                ->state(function (Get $get) {
                                    $preview = $this->getRoutePreview(
                                        (int) ($get('branch_id') ?? 0),
                                        $get('source_date')
                                    );

                                    return $preview['sales_orders'];
                                })
                                ->badge()
                                ->color('warning'),

                            TextEntry::make('customer_count')
                                ->label('Total Customer')
                                ->state(function (Get $get) {
                                    $preview = $this->getRoutePreview(
                                        (int) ($get('branch_id') ?? 0),
                                        $get('source_date')
                                    );

                                    return $preview['customers'];
                                })
                                ->badge()
                                ->color('success'),

                            TextEntry::make('driver_count')
                                ->label('Driver Ready')
                                ->state(function (Get $get) {
                                    $preview = $this->getRoutePreview(
                                        (int) ($get('branch_id') ?? 0),
                                        $get('source_date')
                                    );

                                    return $preview['drivers'];
                                })
                                ->badge()
                                ->color('info'),

                            TextEntry::make('vehicle_count')
                                ->label('Vehicle Active')
                                ->state(function (Get $get) {
                                    $preview = $this->getRoutePreview(
                                        (int) ($get('branch_id') ?? 0),
                                        $get('source_date')
                                    );

                                    return $preview['vehicles'];
                                })
                                ->badge()
                                ->color('primary'),

                            TextEntry::make('estimated_generate_time')
                                ->label('Est. Generate Time')
                                ->state(function (Get $get) {
                                    $preview = $this->getRoutePreview(
                                        (int) ($get('branch_id') ?? 0),
                                        $get('source_date')
                                    );

                                    return $preview['estimated_generate_time'];
                                })
                                ->badge()
                                ->color('warning'),
                        ])
                        ->columns(5),
                ])
                ->action(function (array $data): void {
                    try {
                        $branchId = (int) ($data['branch_id'] ?? 0);
                        $sourceDate = (string) ($data['source_date'] ?? '');
                        $routeDate = $this->calculateRouteDate($sourceDate)
                            ->toDateString();

                        $preview = $this->getRoutePreview(
                            $branchId,
                            $sourceDate
                        );

                        /*
                        |------------------------------------------------------------------
                        | PREP INTEGRASI SISTEM UTAMA - GENERATE SERVICE
                        |------------------------------------------------------------------
                        |
                        | source_date = tanggal SO masuk (so_sda.visit_date)
                        | route_date  = source_date + 2 hari
                        |
                        | Saat database utama tersambung, mapping field tanggal SO masuk
                        | sebaiknya dipusatkan di DeliverySourceService.
                        |
                        | RouteGenerationService tetap menghitung H+2 sendiri sebagai
                        | source of truth. route_date dari UI hanya dikirim sebagai guard
                        | dan akan ditolak jika tidak sama dengan hasil H+2 service.
                        |
                        */
                        $result = app(RouteGenerationService::class)
                            ->generateTodayRoutes([
                                'branch_id' => $branchId,
                                'source_date' => $sourceDate,
                                'route_date' => $routeDate,
                            ]);

                        $routeCount = (int) ($result['route_count'] ?? 0);
                        $demandCount = (int) (
                            $result['demand_count']
                            ?? $preview['customers']
                        );

                        $deferredDemands = collect(
                            $result['deferred_demands'] ?? []
                        );

                        $deferredCount = $deferredDemands->count();

                        if ($routeCount > 0 && $deferredCount === 0) {
                            Notification::make()
                                ->title('Generate Route Berhasil')
                                ->body(
                                    "SO masuk {$this->formatDate($sourceDate)}: "
                                    . "{$preview['sales_orders']} SO, "
                                    . "{$preview['customers']} customer. "
                                    . "Berhasil membuat {$routeCount} route untuk "
                                    . "jadwal {$this->formatDate($routeDate)}."
                                )
                                ->success()
                                ->send();

                        } elseif ($routeCount > 0 && $deferredCount > 0) {
                            $reasonGroups = $deferredDemands->groupBy(
                                fn (array $item) => $item['reason'] ?? 'unknown'
                            );

                            $messages = [];

                            foreach ($reasonGroups as $reason => $items) {
                                $count = $items->count();

                                $messages[] = match ($reason) {
                                    'insufficient_route_slots' =>
                                        "{$count} demand tertunda karena slot route dari Driver Ready/Vehicle Active tidak mencukupi.",

                                    'no_available_driver',
                                    'insufficient_driver' =>
                                        "{$count} demand tertunda karena Driver Ready tidak tersedia.",

                                    'no_available_vehicle',
                                    'insufficient_vehicle' =>
                                        "{$count} demand tertunda karena Vehicle Active tidak tersedia.",

                                    'invalid_coordinate',
                                    'missing_coordinate' =>
                                        "{$count} demand tertunda karena koordinat customer tidak valid atau tidak tersedia.",

                                    default =>
                                        "{$count} demand belum dapat dialokasikan ({$reason}).",
                                };
                            }

                            Notification::make()
                                ->title('Route Dibuat Sebagian')
                                ->body(
                                    "SO masuk {$this->formatDate($sourceDate)}: "
                                    . "{$preview['sales_orders']} SO, "
                                    . "{$preview['customers']} customer. "
                                    . "Berhasil membuat {$routeCount} route untuk "
                                    . "{$this->formatDate($routeDate)}. "
                                    . "{$deferredCount} delivery demand belum teralokasi. "
                                    . implode(' ', $messages)
                                )
                                ->warning()
                                ->persistent()
                                ->send();

                        } else {
                            if ($preview['sales_orders'] <= 0) {
                                $message =
                                    "Tidak ada Sales Order tanggal {$this->formatDate($sourceDate)} "
                                    . 'untuk cabang yang dipilih.';
                            } elseif (
                                $preview['drivers'] <= 0
                                || $preview['vehicles'] <= 0
                            ) {
                                $message =
                                    "Ditemukan {$preview['sales_orders']} SO dan "
                                    . "{$preview['customers']} customer. "
                                    . "Untuk route {$this->formatDate($routeDate)}, "
                                    . "Driver Ready: {$preview['drivers']}, "
                                    . "Vehicle Active: {$preview['vehicles']}.";
                            } else {
                                $message =
                                    "Ditemukan {$preview['sales_orders']} SO dan "
                                    . "{$preview['customers']} customer, tetapi optimizer "
                                    . 'belum menghasilkan route.';
                            }

                            Notification::make()
                                ->title('Route Belum Dapat Dibuat')
                                ->body($message)
                                ->danger()
                                ->persistent()
                                ->send();
                        }

                        $this->resetTable();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal Generate Route')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Preview source V3 berdasarkan tanggal SO masuk.
     *
     * Query SO/customer sengaja didelegasikan ke DeliverySourceService agar
     * halaman Filament tidak mengetahui detail connection/table database utama.
     */
    protected function getRoutePreview(
        int $branchId,
        ?string $sourceDate
    ): array {
        if ($branchId <= 0 || empty($sourceDate)) {
            return $this->emptyPreview();
        }

        $branch = Branch::query()->find($branchId);

        if (! $branch) {
            return $this->emptyPreview();
        }

        $routeDate = $this->calculateRouteDate($sourceDate)
            ->toDateString();

        /*
        |------------------------------------------------------------------
        | PREP INTEGRASI SISTEM UTAMA - SOURCE PREVIEW
        |------------------------------------------------------------------
        |
        | Jangan query so_sda langsung dari halaman ini. DeliverySourceService
        | menjadi satu pintu untuk connection, table, field tanggal, dan cabang.
        | Saat source pindah ke database utama, UI ini tidak perlu diubah.
        |
        */
        $sourcePreview = app(DeliverySourceService::class)
            ->preview(
                branch: $branch,
                sourceDate: $sourceDate,
            );

        /*
        |------------------------------------------------------------------
        | RESOURCE UNTUK ROUTE H+2
        |------------------------------------------------------------------
        | source_date / visit_date = 2026-09-07
        | route_date               = 2026-09-09
        |
        | Resource yang sudah terpakai dicek pada route_date, bukan source_date.
        */
        $usedDriverIds = DeliveryRoute::query()
            ->whereDate('route_date', $routeDate)
            ->where('status', '!=', RouteStatus::Cancelled->value)
            ->whereNotNull('driver_id')
            ->pluck('driver_id');

        $usedVehicleIds = DeliveryRoute::query()
            ->whereDate('route_date', $routeDate)
            ->where('status', '!=', RouteStatus::Cancelled->value)
            ->whereNotNull('vehicle_id')
            ->pluck('vehicle_id');

        $driverCount = Driver::query()
            ->where('branch_id', $branchId)
            ->where('status', DriverStatus::Ready->value)
            ->whereNotIn('id', $usedDriverIds)
            ->count();

        $vehicleCount = Vehicle::query()
            ->where('branch_id', $branchId)
            ->where('status', VehicleStatus::Active->value)
            ->whereNotIn('id', $usedVehicleIds)
            ->count();

        $customerCount = (int) ($sourcePreview['customer_count'] ?? 0);

        /*
        |------------------------------------------------------------------
        | ESTIMASI GENERATE - OSRM
        |------------------------------------------------------------------
        |
        | Google quota/EPM sudah tidak dipakai. Estimasi dibuat sederhana
        | berdasarkan jumlah customer, karena OSRM berjalan self-hosted.
        */
        $estimatedGenerateTime = match (true) {
            $customerCount <= 0 => '-',
            $customerCount <= 100 => '±10-30 detik',
            $customerCount <= 200 => '±30-60 detik',
            default => '±1-2 menit',
        };

        return [
            'sales_orders' => (int) ($sourcePreview['order_count'] ?? 0),
            'customers' => $customerCount,
            'drivers' => $driverCount,
            'vehicles' => $vehicleCount,
            'source_date' => $sourceDate,
            'route_date' => $routeDate,

            'estimated_generate_time' => $estimatedGenerateTime,
        ];
    }


    protected function getViewData(): array
    {
        $service = app(RouteControlTowerService::class);

        return [
            'controlTowerData'           => $service->build($this->mapDate),
            'routeDateOptions'           => $service->availableDates(),
            'googleMapsBrowserKey'       => config('services.google_maps.browser_key'),
            'googleMapsMapId'            => config('services.google_maps.map_id', 'DEMO_MAP_ID'),

            // Manual Reroute state — exposed to Blade as $variables
            'manualRerouteStopId'        => $this->manualRerouteStopId,
            'manualRerouteStoreName'     => $this->manualRerouteStoreName,
            'manualRerouteSourceRouteId' => $this->manualRerouteSourceRouteId,
            'manualRerouteTargets'       => $this->manualRerouteTargets,
            'manualRerouteError'         => $this->manualRerouteError,
        ];
    }

    public function takeoutStop(int $stopId): void
    {
        try {
            $result = app(RouteControlTowerActionService::class)->takeout($stopId);

            Notification::make()
                ->title('Stop berhasil di-Takeout')
                ->body(($result['store_name'] ?? 'Customer') . ' dikeluarkan dari route dan route asal sudah dioptimasi ulang.')
                ->success()
                ->send();

            $this->mapRevision++;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Takeout gagal')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function rescheduleStop(int $stopId, string $newRouteDate): void
    {
        try {
            $result = app(RouteControlTowerActionService::class)
                ->reschedule($stopId, $newRouteDate);

            Notification::make()
                ->title('Stop berhasil di-Reschedule')
                ->body(
                    ($result['store_name'] ?? 'Customer')
                    . ' dikeluarkan dari route saat ini dan masuk antrean tanggal '
                    . Carbon::parse($result['new_route_date'])->format('d-m-Y')
                    . '.'
                )
                ->success()
                ->send();

            $this->mapRevision++;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Reschedule gagal')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function loadManualRerouteTargets(int $stopId): void
    {
        $this->manualRerouteStopId = $stopId;
        $this->manualRerouteStoreName = null;
        $this->manualRerouteSourceRouteId = null;
        $this->manualRerouteTargets = [];
        $this->manualRerouteError = null;

        try {
            $result = app(RouteControlTowerActionService::class)
                ->manualRerouteTargets($stopId);

            $this->manualRerouteStoreName = $result['store_name'] ?? '-';
            $this->manualRerouteSourceRouteId = isset($result['source_route']['id'])
                ? (int) $result['source_route']['id']
                : null;
            $this->manualRerouteTargets = $result['targets'] ?? [];
        } catch (\Throwable $e) {
            $this->manualRerouteError = $e->getMessage();
        }
    }

    public function executeManualReroute(int $targetRouteId): void
    {
        if (! $this->manualRerouteStopId) {
            return;
        }

        try {
            $result = app(RouteControlTowerActionService::class)
                ->manualReroute($this->manualRerouteStopId, $targetRouteId);

            $sequence = $result['new_sequence'] ?? '-';
            $arrival  = $result['new_arrival_time'] ?? '-';

            Notification::make()
                ->title('Manual Rerouting berhasil')
                ->body(
                    ($result['store_name'] ?? 'Customer')
                    . ' dipindahkan ke Route #'
                    . $targetRouteId
                    . '. Sequence baru: '
                    . $sequence
                    . ', ETA: '
                    . $arrival
                    . '. Source dan target route sudah dioptimasi ulang.'
                )
                ->success()
                ->send();

            $this->manualRerouteStopId = null;
            $this->manualRerouteStoreName = null;
            $this->manualRerouteSourceRouteId = null;
            $this->manualRerouteTargets = [];
            $this->manualRerouteError = null;
            $this->mapRevision++;

            $this->dispatch('manual-reroute-finished');
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Manual Rerouting gagal')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Aturan bisnis jadwal delivery: H+2 dari tanggal SO masuk.
     *
     * Untuk sekarang +2 adalah hari kalender.
     * Jika nantinya Sabtu/Minggu/libur harus dilewati, ubah HANYA method ini
     * atau pindahkan ke service kalender operasional khusus.
     */
    protected function calculateRouteDate(string $sourceDate): Carbon
    {
        return Carbon::parse($sourceDate)
            ->startOfDay()
            ->addDays(2);
    }

    protected function formatDate(string $date): string
    {
        return Carbon::parse($date)->format('d-m-Y');
    }

    protected function emptyPreview(): array
    {
        return [
            'sales_orders' => 0,
            'customers' => 0,
            'drivers' => 0,
            'vehicles' => 0,
            'source_date' => null,
            'route_date' => null,
            'estimated_generate_time' => '-',
        ];
    }
}
