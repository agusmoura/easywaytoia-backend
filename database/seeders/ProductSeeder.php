<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Crear cursos individuales
        $courses = [
            [
                'identifier' => 'lm',
                'name' => '¿La Inteligencia Artificial? ¡Pero si es muy fácil!',
                'description' => '¿Que es la I.A? Las distintas plataformas de I.A- ChatGPT, Bing Copilot, Perplexity, Claude y POE',
                'type' => 'course',
                'is_active' => true,
                'stripe_price_id' => 'price_1QICn4GBpK56jnVeCcQ35fsX',
                'price' => 0,
                'success_page' => config('app.prod_frontend_url') . '/felicitacionLM'
            ],
            [
                'identifier' => 'selia',
                'name' => 'Sumergiéndose en la I.A.',
                'description' => '¿Que es la I.A? Las distintas plataformas de I.A- ChatGPT, Bing Copilot, Perplexity, Claude y POE',
                'type' => 'course',
                'is_active' => true,
                'stripe_price_id' => 'prod_R2hbONXVIQgzOV',
                'price' => 25,
                'success_page' => config('app.prod_frontend_url') . '/felicitacionSelia'
            ],
            [
                'identifier' => 'belia',
                'name' => 'Buceando en la I.A.',
                'description' => 'Nueve modos de generar los prompts y cómo crear un bot, 150 prompts adicionales y 100 roles para la ChatGpt',
                'type' => 'course',
                'is_active' => true,
                'stripe_price_id' => 'prod_R2hfIILzpuBWN9',
                'price' => 25,
                'success_page' => config('app.prod_frontend_url') . '/felicitacionBelia'
            ],
            [
                'identifier' => 'rolia',
                'name' => 'Una recorrida por el océano de la I.A.',
                'description' => 'Generación de imágenes, videos y avatares, de sonido y speech, páginas web, creadoras de música y de libros, y editores de video',
                'type' => 'course',
                'is_active' => true,
                'stripe_price_id' => 'prod_R2hlScXGHKFoW6',
                'price' => 25,
                'success_page' => config('app.prod_frontend_url') . '/felicitacionRolia'
            ]
        ];

        // Crear maximizadores
        $maximizers = [
            [
                'identifier' => 'selia>max',
                'name' => 'Maximizador de Sumergiendose en la I.A.',
                'description' => 'Agregue el seminario Buceando en la I.A antes de terminar la compra.',
                'type' => 'maximizer',
                'is_active' => true,
                'stripe_price_id' => 'prod_RBKRTswMnrmejI',
                'price' => 25,
                'related_products' => ['belia'],
                'success_page' => config('app.prod_frontend_url') . '/home'
            ],
            [
                'identifier' => 'belia>mul',
                'name' => 'Multiplicador de Buceando en la I.A.',
                'description' => 'Agregue el seminario Una recorrida por el océano de la I.A. antes de concluir su compra.',
                'type' => 'maximizer',
                'is_active' => true,
                'stripe_price_id' => 'prod_RBKUJExUkODvge',
                'price' => 25,
                'related_products' => ['rolia'],
                'success_page' => config('app.prod_frontend_url') . '/home'
            ]
        ];

        // Crear bundles
        $bundles = [
            [
                'identifier' => 'bndl_a_2x1',
                'name' => 'Bundle 2x1',
                'description' => 'Con la compra de Buceando en la I.A. adquiere también Sumergiéndose en la I.A.',
                'type' => 'bundle',
                'is_active' => true,
                'stripe_price_id' => 'prod_R2hoLQC9vA2TP3',
                'price' => 45,
                'success_page' => config('app.prod_frontend_url') . '/felicitacionBundleA',
                'related_products' => ['selia', 'belia']
            ],
            [
                'identifier' => 'bndl_b_1_2_3',
                'name' => 'Bundle 2 - los tres seminarios de introducción a la inteligencia artificial',
                'description' => 'Adquiera los 3 seminarios, Sumergiéndose en la I.A., Buceando en la I.A y Una recorrida por el océano de la I.A. en un solo paquete a precio promocional!',
                'type' => 'bundle',
                'is_active' => true,
                'stripe_price_id' => 'prod_R2htOHAKPdoN98',
                'price' => 65,
                'success_page' => config('app.prod_frontend_url') . '/felicitacionBundleB',
                'related_products' => ['selia', 'belia', 'rolia']
            ]
        ];

        // Crear todos los productos
        foreach ($courses as $course) {
            Product::create($course);
        }

        foreach ($maximizers as $maximizer) {
            $product = Product::create($maximizer);
            
            // Establecer relaciones
            if (!empty($maximizer['related_products'])) {
                $relatedProducts = Product::whereIn('identifier', $maximizer['related_products'])->get();
                $product->childProducts()->attach($relatedProducts->pluck('id'));
            }
        }

        foreach ($bundles as $bundle) {
            $product = Product::create($bundle);
            
            // Establecer relaciones
            if (!empty($bundle['related_products'])) {
                $relatedProducts = Product::whereIn('identifier', $bundle['related_products'])->get();
                $product->childProducts()->attach($relatedProducts->pluck('id'));
            }
        }
    }
} 