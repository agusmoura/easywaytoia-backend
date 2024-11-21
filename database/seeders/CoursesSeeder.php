<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Bundle;

class CoursesSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            [
                'identifier' => 'selia',
                'name' => 'Sumergiéndose en la I.A.',
                'description' => '¿Que es la I.A? Las distintas plataformas de I.A- ChatGPT, Bing Copilot, Perplexity, Claude y POE',
                'slug' => 'sumergiendo-en-la-ia',
                'is_active' => true,
                'stripe_price_id' => 'price_1QICn4GBpK56jnVeCcQ35fsX'
            ],
            [
                'identifier' => 'belia',
                'name' => 'Buceando en la I.A.',
                'description' => 'Nueve modos de generar los prompts y cómo crear un bot, 150 prompts adicionales y 100 roles para la ChatGpt',
                'slug' => 'buceando-en-la-ia',
                'is_active' => true,
                'stripe_price_id' => 'price_1QICptGBpK56jnVe5VaRQffz'
            ],
            [
                'identifier' => 'rolia',
                'name' => 'Una recorrida por el océano de la I.A.',
                'description' => 'Generación de imágenes, videos y avatares, de sonido y speech, páginas web, creadoras de música y de libros, y editores de video.',
                'slug' => 'una-recorrida-por-el-oceano-de-la-ia',
                'is_active' => true,
                'stripe_price_id' => 'price_1QICsaGBpK56jnVePi5BjRAQ'
            ],
            [
                'identifier' => 'selia>max',
                'name' => 'Maximizador de Sumergiendose en la I.A.',
                'description' => 'Agregue el seminario Buceando en la I.A antes de terminar la compra.',
                'slug' => 'maximizador-de-sumergiendose-en-la-ia',
                'is_active' => true,
                'stripe_price_id' => 'price_1QID6pGBpK56jnVefDftrqaQ'
            ],
            [
                'identifier' => 'belia>mul',
                'name' => 'Multiplicador de Buceando en la I.A.',
                'description' => 'Agregue el seminario Una recorrida por el océano de la I.A. antes de concluir su compra.',
                'slug' => 'multiplicador-de-buceando-en-la-ia',
                'is_active' => true,
                'stripe_price_id' => 'price_1QID87GBpK56jnVeLXs3sbqu'
            ],
        ];

        $bundles = [
            [
                'identifier' => 'bndl_a_2x1',
                'name' => 'Bundle 2x1',
                'description' => 'Con la compra de Buceando en la I.A. adquiere también Sumergiéndose en la I.A.',
                'is_active' => true,
                'stripe_price_id' => 'price_1QICwUGBpK56jnVeWy58IAY1',
            ],
            [
                'identifier' => 'bndl_b_1_2_3',
                'name' => 'Bundle 2 - los tres seminarios de introducción a la inteligencia artificial',
                'description' => 'Adquiera los 3 seminarios, Sumergiéndose en la I.A., Buceando en la I.A y Una recorrida por el océano de la I.A. en un solo paquete a precio promocional!',
                'is_active' => true,
                'stripe_price_id' => 'price_1QID02GBpK56jnVe5ZhsADnZ',
            ]
        ];

        foreach ($courses as $course) {
            Course::create($course);
        }

        foreach ($bundles as $bundle) {
            Bundle::create($bundle);
        }

        /* wait for the bundle to be created */
        sleep(1);

        // Link courses to bundles
        $bundle_2x1 = Bundle::where('identifier', 'bndl_a_2x1')->first();
        $bundle_2x1->setCourses(['selia', 'belia']);

        $bundle_2_3 = Bundle::where('identifier', 'bndl_b_1_2_3')->first();
        $bundle_2_3->setCourses(['selia', 'belia', 'rolia']);
    }
}
