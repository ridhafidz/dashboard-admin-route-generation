<div class="relative">
    <x-filament::dropdown
        placement="bottom-end"
        width="sm"
        max-height="420px"
    >
        <x-slot name="trigger">
            <x-filament::button
                color="gray"
                outlined
                icon="heroicon-m-building-office-2"
                icon-position="before"
            >
                <span class="hidden sm:inline">
                    {{ $this->activeBranch?->init_cab ?? 'Pilih Cabang' }}
                    -
                    {{ $this->activeBranch?->name ?? 'Pilih Cabang' }}
                </span>

                <span class="sm:hidden">
                    {{ $this->activeBranch?->init_cab ?? 'Cabang' }}
                </span>
            </x-filament::button>
        </x-slot>

        <div class="w-full p-2">
            {{-- Search --}}
            <div class="mb-2">
                <x-filament::input.wrapper
                    prefix-icon="heroicon-m-magnifying-glass"
                >
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari cabang..."
                    />
                </x-filament::input.wrapper>
            </div>

            {{-- Branch list --}}
            <x-filament::dropdown.list>
                @forelse ($this->branches as $branch)

                    <x-filament::dropdown.list.item
                        type="button"
                        wire:click.prevent="selectBranch({{ $branch->id }})"
                        icon="heroicon-m-building-office-2"
                        :icon-color="$selectedBranchId === $branch->id ? 'warning' : 'gray'"
                    >
                        <div class="flex w-full items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium">
                                    {{ $branch->init_cab }} - {{ $branch->name }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $branch->cab_id }}
                                </div>
                            </div>

                            @if ($selectedBranchId === $branch->id)
                                <x-filament::icon
                                    icon="heroicon-m-check"
                                    class="h-5 w-5 text-primary-600 dark:text-primary-400"
                                />
                            @endif
                        </div>
                    </x-filament::dropdown.list.item>

                @empty

                    <div class="px-3 py-4 text-center text-sm text-gray-500">
                        Cabang tidak ditemukan.
                    </div>

                @endforelse
            </x-filament::dropdown.list>
        </div>
    </x-filament::dropdown>
</div>