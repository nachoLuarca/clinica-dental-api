<?php

namespace App\Repositories\Contracts;

use App\Models\Budget;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BudgetRepositoryInterface
{
    /**
     * @param  array<int, string>  $with
     * @return LengthAwarePaginator<int, Budget>
     */
    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $with
     */
    public function find(int $id, array $with = []): ?Budget;

    /**
     * Crea el presupuesto y reemplaza sus lineas dentro de una transaccion.
     *
     * @param  array<string, mixed>  $data   Cabecera (patient_id, estado, notas, total).
     * @param  array<int, array<string, mixed>>  $items  Lineas ya calculadas.
     */
    public function create(array $data, array $items): Budget;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>|null  $items  Si es null, no se tocan las lineas.
     */
    public function update(Budget $budget, array $data, ?array $items = null): Budget;

    public function delete(Budget $budget): void;
}
