<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve la documentacion OpenAPI de la API.
 *
 * Decision de enfoque: se mantiene un contrato OpenAPI 3 a mano en
 * `resources/openapi/openapi.yaml` (fuente unica de verdad) en vez de generar
 * el spec desde anotaciones en los controllers (l5-swagger). Motivos:
 *  - No agrega una dependencia Composer ni acopla la doc al codigo con
 *    anotaciones que ensucian los controllers (que en esta arquitectura solo
 *    orquestan y delegan en servicios).
 *  - El .yaml es importable tal cual por ambos frontends (portal y paciente)
 *    para generar clientes/tipos, y es trivial de versionar y migrar.
 *  - La UI (Swagger UI) se carga desde CDN, sin assets propios en el repo.
 *
 * Rutas:
 *  - GET /api/openapi.yaml   -> el contrato crudo.
 *  - GET /api/documentation  -> la UI interactiva (Swagger UI) que lo consume.
 */
class DocsController extends Controller
{
    private const SPEC_PATH = 'openapi/openapi.yaml';

    public function spec(): BinaryFileResponse
    {
        $path = resource_path(self::SPEC_PATH);

        abort_unless(is_file($path), 404, 'Contrato OpenAPI no encontrado.');

        return response()->file($path, [
            'Content-Type' => 'application/yaml',
        ]);
    }

    public function ui(): Response
    {
        $specUrl = url('/api/openapi.yaml');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Clinica Dental API - Documentacion</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css" />
  <style>body { margin: 0; } .topbar { display: none; }</style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.onload = function () {
      window.ui = SwaggerUIBundle({
        url: "{$specUrl}",
        dom_id: "#swagger-ui",
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis],
      });
    };
  </script>
</body>
</html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
