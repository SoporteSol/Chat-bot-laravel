<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    // Inicialización de la base de datos
    public function run(): void
    {
        // No creamos datos de prueba automáticamente; las instancias se configuran desde el panel
    }
}
