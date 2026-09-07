<?php

namespace App\Services;

use App\Models\Branch;

class BranchContext
{
    private const SESSION_KEY = 'active_branch_id';

    public function getId(): ?int
    {
        $id = session(self::SESSION_KEY);

        return $id !== null ? (int) $id : null;
    }

    public function get(): ?Branch
    {
        $id = $this->getId();

        if (!$id) {
            return null;
        }

        return Branch::query()->find($id);
    }

    public function set(int $branchId): void
    {
        $branchExists = Branch::query()
            ->whereKey($branchId)
            ->where('status', 'active')
            ->exists();

        if (!$branchExists) {
            throw new \InvalidArgumentException(
                'Cabang yang dipilih tidak tersedia atau tidak aktif.'
            );
        }

        session()->put(self::SESSION_KEY, $branchId);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function ensureDefault(): ?Branch
    {
        $current = $this->get();

        if ($current) {
            return $current;
        }

        $defaultBranch = Branch::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->first();

        if (!$defaultBranch) {
            return null;
        }

        $this->set($defaultBranch->id);

        return $defaultBranch;
    }
}