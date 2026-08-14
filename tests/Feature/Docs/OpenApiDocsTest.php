<?php

namespace Tests\Feature\Docs;

use Tests\TestCase;

/**
 * Verifica que la documentacion OpenAPI se sirve y es consumible por ambos
 * frontends: la UI (Swagger UI) carga y el contrato crudo es YAML valido que
 * cubre los dominios y los codigos de error clave (401/403/404/409/422/429).
 *
 * Ambas rutas son publicas y sin tenant: son el contrato de referencia.
 */
class OpenApiDocsTest extends TestCase
{
    public function test_la_ui_de_documentacion_carga(): void
    {
        $response = $this->get('/api/documentation');

        $response->assertOk();
        $this->assertStringContainsString('swagger-ui', $response->getContent());
        $this->assertStringContainsString('/api/openapi.yaml', $response->getContent());
    }

    public function test_el_contrato_openapi_se_sirve_y_es_valido(): void
    {
        $response = $this->get('/api/openapi.yaml');

        $response->assertOk();
        $this->assertStringContainsString('application/yaml', $response->headers->get('Content-Type'));

        $spec = $response->streamedContent() ?: $response->getContent();

        // Encabezado OpenAPI y titulo.
        $this->assertStringContainsString('openapi: 3.0', $spec);
        $this->assertStringContainsString('Clinica Dental API', $spec);
    }

    public function test_el_contrato_cubre_los_dominios_y_errores_clave(): void
    {
        $spec = $this->get('/api/openapi.yaml')->streamedContent();

        // Dominios (paths) principales.
        foreach ([
            '/staff/login',
            '/paciente/login',
            '/staff/professionals',
            '/staff/patients',
            '/staff/treatments',
            '/staff/budgets',
            '/staff/availability',
            '/staff/appointments',
            '/paciente/appointments',
            '/publico/tratamientos',
        ] as $path) {
            $this->assertStringContainsString($path.':', $spec, "Falta documentar {$path}");
        }

        // Codigos de error relevantes y el error de negocio del bloqueo optimista.
        foreach (['Unauthorized', 'Forbidden', 'NotFound', 'SlotUnavailable', 'ValidationError', 'TooManyRequests'] as $ref) {
            $this->assertStringContainsString($ref, $spec);
        }
        $this->assertStringContainsString('slot_no_disponible', $spec);
    }
}
