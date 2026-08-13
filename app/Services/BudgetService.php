<?php

namespace App\Services;

use App\Models\Budget;
use App\Repositories\Contracts\BudgetRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de presupuestos.
 *
 * Decisiones clave:
 *  - El TOTAL nunca se acepta del cliente: se recalcula a partir de las lineas
 *    (precio_unitario * cantidad), para que sea siempre consistente.
 *  - Cada linea es un SNAPSHOT: si referencia un tratamiento del catalogo se
 *    copia su nombre y precio actuales; si es diferencial, usa nombre/precio
 *    libres. Editar luego el catalogo no altera presupuestos ya emitidos.
 *  - Paciente y tratamientos referenciados deben pertenecer al tenant activo
 *    (se validan via repositorios, ya filtrados por TenantScope).
 */
class BudgetService
{
    public function __construct(
        private readonly BudgetRepositoryInterface $budgets,
        private readonly PatientRepositoryInterface $patients,
        private readonly TreatmentRepositoryInterface $treatments,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->budgets->paginate($perPage, ['patient', 'items']);
    }

    public function find(int $id): Budget
    {
        return $this->budgets->find($id, ['patient', 'items.treatment'])
            ?? throw (new ModelNotFoundException)->setModel(Budget::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Budget
    {
        $this->assertPatientExists($data['patient_id']);

        $items = $this->buildItems($data['items']);
        $total = $this->sumTotal($items);

        $header = [
            'patient_id' => $data['patient_id'],
            'estado' => $data['estado'] ?? 'borrador',
            'notas' => $data['notas'] ?? null,
            'total' => $total,
        ];

        return $this->budgets->create($header, $items)->load(['patient', 'items.treatment']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Budget
    {
        $budget = $this->find($id);

        $header = [];
        if (array_key_exists('estado', $data)) {
            $header['estado'] = $data['estado'];
        }
        if (array_key_exists('notas', $data)) {
            $header['notas'] = $data['notas'];
        }

        // Las lineas solo se reemplazan si vienen; si vienen, se recalcula total.
        $items = null;
        if (array_key_exists('items', $data)) {
            $items = $this->buildItems($data['items']);
            $header['total'] = $this->sumTotal($items);
        }

        return $this->budgets->update($budget, $header, $items)
            ->load(['patient', 'items.treatment']);
    }

    public function delete(int $id): void
    {
        $this->budgets->delete($this->find($id));
    }

    /**
     * Normaliza las lineas de entrada a filas listas para persistir, resolviendo
     * el snapshot de nombre/precio y validando los tratamientos del tenant.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $rawItems): array
    {
        $treatmentIds = array_values(array_filter(array_map(
            static fn (array $item) => isset($item['treatment_id']) ? (int) $item['treatment_id'] : null,
            $rawItems,
        )));

        $treatments = $this->treatments->findManyByIds($treatmentIds)->keyBy('id');

        // Todo id referenciado debe existir dentro del tenant.
        foreach ($treatmentIds as $tid) {
            if (! $treatments->has($tid)) {
                throw ValidationException::withMessages([
                    'items' => ["El tratamiento {$tid} no pertenece a la clinica."],
                ]);
            }
        }

        $items = [];

        foreach ($rawItems as $index => $item) {
            $cantidad = (int) ($item['cantidad'] ?? 1);
            $treatmentId = isset($item['treatment_id']) ? (int) $item['treatment_id'] : null;

            if ($treatmentId !== null) {
                $treatment = $treatments->get($treatmentId);
                $nombre = $item['nombre'] ?? $treatment->nombre;
                $precio = array_key_exists('precio_unitario', $item)
                    ? (float) $item['precio_unitario']
                    : (float) $treatment->precio;
            } else {
                // Linea diferencial / no listada: nombre y precio libres.
                if (empty($item['nombre']) || ! array_key_exists('precio_unitario', $item)) {
                    throw ValidationException::withMessages([
                        "items.$index" => ['Una linea sin tratamiento requiere nombre y precio_unitario.'],
                    ]);
                }
                $nombre = $item['nombre'];
                $precio = (float) $item['precio_unitario'];
            }

            $items[] = [
                'treatment_id' => $treatmentId,
                'nombre' => $nombre,
                'precio_unitario' => $precio,
                'cantidad' => $cantidad,
                'subtotal' => round($precio * $cantidad, 2),
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function sumTotal(array $items): float
    {
        return round(array_sum(array_column($items, 'subtotal')), 2);
    }

    private function assertPatientExists(int $patientId): void
    {
        if ($this->patients->find($patientId) === null) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente indicado no pertenece a la clinica.'],
            ]);
        }
    }
}
