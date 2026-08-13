<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BudgetStoreRequest;
use App\Http\Requests\Staff\BudgetUpdateRequest;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de presupuestos (guard 'staff').
 */
class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginate((int) $request->integer('per_page', 15))
        );
    }

    public function store(BudgetStoreRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validated())], 201);
    }

    public function show(int $budget): JsonResponse
    {
        return response()->json(['data' => $this->service->find($budget)]);
    }

    public function update(BudgetUpdateRequest $request, int $budget): JsonResponse
    {
        return response()->json(['data' => $this->service->update($budget, $request->validated())]);
    }

    public function destroy(int $budget): JsonResponse
    {
        $this->service->delete($budget);

        return response()->json(null, 204);
    }
}
