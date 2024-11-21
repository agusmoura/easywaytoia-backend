<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Bundle;
use App\Models\PaymentLink;
class CoursesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = [
            [
                'identifier' => 'iamf',
                'name' => '¿La Inteligencia Artificial? ¡¡Pero si es muy fácil!!',
                'description' => '',
                'slug' => 'inteligencia-artificial-pero-si-es-muy-facil',
                'is_active' => true,
            ],
            [
                'identifier' => 'selia',
                'name' => 'Sumergiéndose en la I.A.',
                'description' => '¿Que es la I.A? Las distintas plataformas de I.A- ChatGPT, Bing Copilot, Perplexity, Claude y POE',
                'slug' => 'sumergiendo-en-la-ia',
                'is_active' => true,
            ],
            [
                'identifier' => 'belia',
                'name' => 'Buceando en la I.A.',
                'description' => 'Nueve modos de generar los prompts y cómo crear un bot, 150 prompts adicionales y 100 roles para la ChatGpt',
                'slug' => 'buceando-en-la-ia',
                'is_active' => true,
            ],
            [
                'identifier' => 'rolia',
                'name' => 'Una recorrida por el océano de la I.A.',
                'description' => 'Generación de imágenes, videos y avatares, de sonido y speech, páginas web, creadoras de música y de libros, y editores de video.',
                'slug' => 'una-recorrida-por-el-oceano-de-la-ia',
                'is_active' => true,
            ],
            [
                'identifier' => 'selia>max',
                'name' => 'Maximizador de Sumergiendose en la I.A.',
                'description' => 'Agregue el seminario Buceando en la I.A antes de terminar la compra.',
                'slug' => 'maximizador-de-sumergiendose-en-la-ia',
                'is_active' => true,
            ],
            [
                'identifier' => 'belia>mul',
                'name' => 'Multiplicador de Buceando en la I.A.',
                'description' => 'Agregue el seminario Una recorrida por el océano de la I.A. antes de concluir su compra.',
                'slug' => 'multiplicador-de-buceando-en-la-ia',
                'is_active' => true,
            ],
        ];

        $bundles = [
            [
                'identifier' => 'bndl_a_2x1',
                'name' => 'Bundle 2x1',
                'description' => 'Con la compra de Buceando en la I.A. adquiere también Sumergiéndose en la I.A.',
                'is_active' => true,
            ],
            [
                'identifier' => 'bndl_b_1_2_3',
                'name' => 'Bundle 2 - los tres seminarios de introducción a la inteligencia artificial',
                'description' => 'Adquiera los 3 seminarios, Sumergiéndose en la I.A., Buceando en la I.A y Una recorrida por el océano de la I.A. en un solo paquete a precio promocional!',
                'is_active' => true,
            ]
        ];

        $payment_links = [
            [
                'identifier' => 'selia',
                'link' => 'https://buy.stripe.com/test_fZe7t3fKGbcP1SUdQQ',
                'provider' => 'stripe',
                'is_active' => true,
            ],
            [
                'identifier' => 'belia',
                'link' => 'https://buy.stripe.com/test_7sIcNndCybcP9lm289',
                'provider' => 'stripe',
                'is_active' => true,
            ],
            [
                'identifier' => 'rolia',
                'link' => 'https://buy.stripe.com/test_cN28x7dCya8LfJK28a',
                'provider' => 'stripe',
                'is_active' => true,
            ],
            [
                'identifier' => 'selia>max',
                'link' => 'https://buy.stripe.com/test_bIY9Bbaqm4Or4128wB',
                'provider' => 'stripe',
                'is_active' => true,
            ],
            [
                'identifier' => 'belia>mul',
                'link' => 'https://buy.stripe.com/test_bIYcNnbuq2GjeFG9AG',
                'provider' => 'stripe',
                'is_active' => true,
            ],
            [
                'identifier' => 'bndl_a_2x1',
                'link' => 'https://buy.stripe.com/test_7sIeVv7eaep1cxycMP',
                'provider' => 'stripe',
                'is_active' => true,
            ],
            [
                'identifier' => 'bndl_b_1_2_3',
                'link' => 'https://buy.stripe.com/test_7sI3cN1TQ3Kncxy4gk',
                'provider' => 'stripe',
                'is_active' => true,
            ],
        ];

     

        foreach ($courses as $course) {
            Course::create($course);
        }

        foreach ($bundles as $bundle) {
            Bundle::create($bundle);
        }

        foreach ($payment_links as $payment_link) {
            PaymentLink::create($payment_link);
        }

        $bundle_2x1 = Bundle::where('identifier', 'bndl_a_2x1')->first();
        $bundle_2x1->courses()->attach([1, 2]);

        $bundle_2_3 = Bundle::where('identifier', 'bndl_b_1_2_3')->first();
        $bundle_2_3->courses()->attach([1, 2, 3]);

        $this->linkPaymentsToCourses();
    }

    private function linkPaymentsToCourses(): void
    {
        // Link individual courses to their payment links
        $coursePaymentMap = [
            'selia' => 'selia',
            'belia' => 'belia',
            'rolia' => 'rolia',
            'selia>max' => 'selia>max',
            'belia>mul' => 'belia>mul'
        ];

        foreach ($coursePaymentMap as $courseId => $paymentId) {
            $course = Course::where('identifier', $courseId)->first();
            $paymentLink = PaymentLink::where('identifier', $paymentId)->first();
            
            if ($course && $paymentLink) {
                \DB::table('course_payment_link')->insert([
                    'course_id' => $course->id,
                    'payment_link_id' => $paymentLink->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Link bundles to their payment links
        $bundlePaymentMap = [
            'bndl_a_2x1' => 'bndl_a_2x1',
            'bndl_b_1_2_3' => 'bndl_b_1_2_3'
        ];

        foreach ($bundlePaymentMap as $bundleId => $paymentId) {
            $bundle = Bundle::where('identifier', $bundleId)->first();
            $paymentLink = PaymentLink::where('identifier', $paymentId)->first();
            
            if ($bundle && $paymentLink) {
                \DB::table('course_payment_link')->insert([
                    'bundle_id' => $bundle->id,
                    'payment_link_id' => $paymentLink->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }
}
