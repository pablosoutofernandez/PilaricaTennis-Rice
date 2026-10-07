<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Crea el administrador si aún no existe. Se puede ejecutar en cada despliegue:
     * nunca cambia la contraseña de un administrador que ya existe.
     */
    public function run(): void
    {
        $name = config('torneo.admin.name');

        if (User::where('name', $name)->exists()) {
            return;
        }

        (new User)->forceFill([
            'name' => $name,
            'password' => config('torneo.admin.password'),
            'is_admin' => true,
        ])->save();
    }
}
