<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\RoleProvisioner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Por defecto el staff de factory queda con rol 'admin': la mayoria de
     * los tests solo necesitan un staff autenticado que pueda todo. Los tests
     * que ejercitan permisos especificos usan ->rol('recepcion'|'profesional')
     * o $user->syncRoles([]) para el caso sin permisos.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            app(RoleProvisioner::class)->asignarRol($user, 'admin');
        });
    }

    /**
     * Crea el staff con un rol especifico en vez del 'admin' por defecto.
     */
    public function rol(string $rol): static
    {
        return $this->afterCreating(function (User $user) use ($rol): void {
            app(RoleProvisioner::class)->asignarRol($user, $rol);
        });
    }
}
