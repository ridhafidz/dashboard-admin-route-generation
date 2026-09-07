<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Services\BranchContext;
use Livewire\Component;

class BranchSwitcher extends Component
{
    public string $search = '';

    public ?int $selectedBranchId = null;

    public function mount(BranchContext $branchContext): void
    {
        $branch = $branchContext->ensureDefault();

        $this->selectedBranchId = $branch?->id;
    }

    public function selectBranch(
        int $branchId,
        BranchContext $branchContext
    ): void {
        $branchContext->set($branchId);

        $this->selectedBranchId = $branchId;

        $this->js('window.location.reload()');
    }

    public function getBranchesProperty()
    {
        return Branch::query()
            ->where('status', 'active')
            ->when(
                filled($this->search),
                function ($query) {
                    $search = '%' . trim($this->search) . '%';

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', $search)
                            ->orWhere('cab_id', 'like', $search)
                            ->orWhere('init_cab', 'like', $search)
                            ->orWhere('region_id', 'like', $search);
                    });
                }
            )
            ->orderBy('name')
            ->limit(20)
            ->get([
                'id',
                'name',
                'cab_id',
                'init_cab',
                'region_id',
            ]);
    }

    public function getActiveBranchProperty(): ?Branch
    {
        return app(BranchContext::class)->get();
    }

    public function render()
    {
        return view('livewire.branch-switcher');
    }
}