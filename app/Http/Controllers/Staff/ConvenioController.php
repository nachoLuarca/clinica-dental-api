<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ConvenioStoreRequest;
use App\Http\Requests\Staff\ConvenioUpdateRequest;
use App\Services\ConvenioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de convenios (guard 'staff'). Subida de logo: mismo criterio que
 * TenantController -el front debe mandar POST multipart con
 * '_method=PATCH' para actualizar-.
 */
class ConvenioController extends Controller
{
    public function __construct(private readonly ConvenioService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginate($this->perPage($request))
        );
    }

    public function store(ConvenioStoreRequest $request): JsonResponse
    {
        $data = $request->safe()->except('logo');

        return response()->json(['data' => $this->service->create($data, $request->file('logo'))], 201);
    }

    public function show(int $convenio): JsonResponse
    {
        return response()->json(['data' => $this->service->find($convenio)]);
    }

    public function update(ConvenioUpdateRequest $request, int $convenio): JsonResponse
    {
        $data = $request->safe()->except('logo');

        return response()->json(['data' => $this->service->update($convenio, $data, $request->file('logo'))]);
    }

    public function destroy(int $convenio): JsonResponse
    {
        $this->service->delete($convenio);

        return response()->json(null, 204);
    }
}
