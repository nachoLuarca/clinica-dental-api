<?php

namespace App\Repositories;

use App\Models\Budget;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BudgetRepository implements BudgetRepositoryInterface
{
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        return Budget::query()
            ->with($with)
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id, array $with = []): ?Budget
    {
        return Budget::query()->with($with)->find($id);
    }

    public function create(array $data, array $items): Budget
    {
        return DB::transaction(function () use ($data, $items) {
            $budget = Budget::query()->create($data);
            $this->replaceItems($budget, $items);

            return $budget->refresh();
        });
    }

    public function update(Budget $budget, array $data, ?array $items = null): Budget
    {
        return DB::transaction(function () use ($budget, $data, $items) {
            $budget->update($data);

            if ($items !== null) {
                $this->replaceItems($budget, $items);
            }

            return $budget->refresh();
        });
    }

    public function delete(Budget $budget): void
    {
        // budget_items cae por cascade a nivel de FK, pero borramos explicito
        // por claridad y para no depender solo del motor.
        $budget->items()->delete();
        $budget->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceItems(Budget $budget, array $items): void
    {
        $budget->items()->delete();

        foreach ($items as $item) {
            $budget->items()->create($item);
        }
    }
}
