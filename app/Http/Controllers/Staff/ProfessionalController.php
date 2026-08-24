<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ProfessionalStoreRequest;
use App\Http\Requests\Staff\ProfessionalUpdateRequest;
use App\Services\ProfessionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de profesionales (guard 'staff'). El controller solo orquesta y delega
 * en ProfessionalService; nunca toca Eloquent directamente.
 *
 * Subida de foto: mismo criterio que TenantController -el front debe mandar
 * POST multipart con '_method=PATCH' para actualizar (Laravel no parsea PATCH
 * multipart nativo)-.
 */
class ProfessionalController extends Controller
{
    public function __construct(private readonly ProfessionalService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginate((int) $request->integer('per_page', 15))
        );
    }

    public function store(ProfessionalStoreRequest $request): JsonResponse
    {
        $data = $request->safe()->except('foto');

        return response()->json(['data' => $this->service->create($data, $request->file('foto'))], 201);
    }

    public function show(int $professional): JsonResponse
    {
        return response()->json(['data' => $this->service->find($professional)]);
    }

    public function update(ProfessionalUpdateRequest $request, int $professional): JsonResponse
    {
        $data = $request->safe()->except('foto');

        return response()->json(['data' => $this->service->update($professional, $data, $request->file('foto'))]);
    }

    public function destroy(int $professional): JsonResponse
    {
        $this->service->delete($professional);

        return response()->json(null, 204);
    }
}
