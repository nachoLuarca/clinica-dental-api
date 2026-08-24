<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Convenio de salud que acepta la clinica (Fonasa, isapre, caja de
 * compensacion, aseguradora). Aislado por tenant.
 */
class Convenio extends Model
{
    use BelongsToTenant;

    protected $table = 'convenios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'tipo',
        'logo_path',
        'descripcion',
        'activo',
    ];

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * URL publica del logo (disco 'public', servido via /storage). Null si
     * el convenio todavia no tiene uno cargado. Mismo patron que
     * Tenant::logoUrl().
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->logo_path !== null
                ? Storage::disk('public')->url($this->logo_path)
                : null,
        );
    }
}
