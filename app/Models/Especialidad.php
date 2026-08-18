<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Especialidad odontologica (Odontologia General, Ortodoncia, Endodoncia,
 * etc.). Aislada por tenant: cada clinica arma su propio catalogo.
 *
 * Se relaciona con Treatment::categoria (texto libre) via
 * EspecialidadCategoria, y con Professional via el pivot
 * professional_especialidad (un profesional puede tener varias).
 */
class Especialidad extends Model
{
    use BelongsToTenant;

    protected $table = 'especialidades';

    protected $fillable = [
        'tenant_id',
        'nombre',
    ];

    /**
     * @return HasMany<EspecialidadCategoria, $this>
     */
    public function categorias(): HasMany
    {
        return $this->hasMany(EspecialidadCategoria::class);
    }

    /**
     * @return BelongsToMany<Professional, $this>
     */
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class, 'professional_especialidad')->withTimestamps();
    }
}
