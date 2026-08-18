<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Tenant = clinica dental. Es la raiz de aislamiento del sistema.
 *
 * NO usa BelongsToTenant: la propia tabla de tenants no se filtra por tenant.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre',
        'slug',
        'logo_path',
        'color_primario',
        'activo',
    ];

    protected $appends = ['logo_url', 'color_contraste'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * URL publica del logo (disco 'public', servido via /storage). Null si la
     * clinica todavia no subio uno.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->logo_path !== null
                ? Storage::disk('public')->url($this->logo_path)
                : null,
        );
    }

    /**
     * '#ffffff' o '#000000', el que de mejor contraste (WCAG) sobre
     * color_primario. Para que el frontend no tenga que calcularlo -y
     * termine con texto blanco ilegible si una clinica elige un color claro-.
     * Null si la clinica no configuro color_primario (el frontend usa su
     * default).
     */
    protected function colorContraste(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->color_primario === null) {
                return null;
            }

            return self::mejorContrasteSobre($this->color_primario);
        });
    }

    /**
     * Luminancia relativa segun WCAG 2.x: elige blanco o negro segun cual de
     * los dos de mayor contraste contra el color de fondo dado.
     */
    private static function mejorContrasteSobre(string $colorHex): string
    {
        $hex = ltrim($colorHex, '#');

        if (strlen($hex) === 3) {
            $hex = implode('', array_map(fn ($c) => str_repeat($c, 2), str_split($hex)));
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '#ffffff';
        }

        [$r, $g, $b] = array_map(
            fn (string $c) => hexdec($c) / 255,
            str_split($hex, 2),
        );

        $canal = fn (float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminancia = 0.2126 * $canal($r) + 0.7152 * $canal($g) + 0.0722 * $canal($b);

        // Contraste contra blanco (luminancia 1) vs contra negro (luminancia 0).
        $contrasteBlanco = (1 + 0.05) / ($luminancia + 0.05);
        $contrasteNegro = ($luminancia + 0.05) / 0.05;

        return $contrasteBlanco >= $contrasteNegro ? '#ffffff' : '#000000';
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Professional, $this>
     */
    public function professionals(): HasMany
    {
        return $this->hasMany(Professional::class);
    }

    /**
     * @return HasMany<Patient, $this>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }
}
