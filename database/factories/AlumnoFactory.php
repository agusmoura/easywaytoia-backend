<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

class AlumnoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'pais' => fake()->country(),
            'telefono' => fake()->phoneNumber(),
            'direccion' => fake()->address(),
        ];
    }
} 