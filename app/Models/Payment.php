<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\Payments\PaymentStripe;
use App\Services\Payments\PaymentUala;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payment_id',
        'provider_payment_id',
        'provider',
        'status',
        'amount',
        'currency',
        'product_id',
        'metadata',
        'buy_link'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function createPaymentLink(array $data, $user)
    {

        /** 1. VALIDACION DE DATOS **/

        $validator = Validator::make($data, [
            'identifier' => ['required', 'string'],
            'provider' => ['nullable', 'in:stripe,uala']
        ]);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
        if (!$user->email_verified_at) {
            throw new \Exception('El usuario no es un alumno registrado', 401);
        }

        /** 2. OBTENER EL PRODUCTO **/

        $product = Product::where('identifier', $data['identifier'])->first();
        if (!$product) {
            throw new \Exception('El producto no existe', 404);
        }

        /* primero listar todos los productos que tiene el usuario */
        $enrollments = Enrollment::where('user_id', $user->id)->get();


        /* Verificar que el usuario no tenga el producto */
        if ($enrollments->contains('product_id', $product->id)) {
            throw new \Exception('El usuario ya tiene este producto. Por favor, elija otro producto.', 400);
        }


        /* si es tipo bundle, verificar que tenga los 3 productos relacionados */
        if ($product->type === 'bundle') {
            foreach ($product->related_products as $related_product) {
                $related_product = Product::where('identifier', $related_product)->first();
                if ($enrollments->contains('product_id', $related_product->id)) {
                    throw new \Exception('El usuario ya tiene uno de los productos relacionados. Por favor, elija otro producto.', 400);
                }
            }
        }

        if ($product->type === 'maximizer') {
            foreach ($product->related_products as $related_product) {
                $related_product = Product::where('identifier', $related_product)->first();
                if ($enrollments->contains('product_id', $related_product->id)) {
                    throw new \Exception('El usuario ya tiene uno de los productos relacionados. Por favor, elija otro producto.', 400);
                }
            }
        }

        /** 3. OBTENER EL PAIS DEL USUARIO **/

        $country = strtolower(Student::where('user_id', $user->id)->first()->country ?? 'default');

        /** 4. OBTENER EL PROVEEDOR DE PAGO **/
        $provider = $data['provider'] ?? ($country === 'argentina' ? 'uala' : 'stripe');
        $paymentService = $provider === 'uala'
            ? new PaymentUala()
            : new PaymentStripe();

        /** 5. CREAR EL PAGO **/
        return $paymentService->createPaymentLink($product, $user);
    }
    
    public static function checkout(array $data)
    {

        /** 1. VALIDACION DE DATOS **/
        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'identifier' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        /** 2. OBTENER EL PRODUCTO **/

        $product = Product::where('identifier', $data['identifier'])->first();
        if (!$product) {
            throw new \Exception('El producto no existe', 404);
        }

        Log::info('Product', ['product' => $product]);

        // Crear o recuperar usuario
        try {
            $user = User::registerUser($data, validatedMail: true);
            $user = $user['user']->toArray();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 500);
        }


        /* primero listar todos los productos que tiene el usuario */
        $enrollments = Enrollment::where('user_id', $user->id)->get();
    

        /* Verificar que el usuario no tenga el producto */
        if ($enrollments->contains('product_id', $product->id)) {
            throw new \Exception('El usuario ya tiene este producto. Por favor, elija otro producto.', 400);
        }


        /* si es tipo bundle, verificar que tenga los 3 productos relacionados */
        if ($product->type === 'bundle') {
            foreach ($product->related_products as $related_product) {
                $related_product = Product::where('identifier', $related_product)->first();
                if ($enrollments->contains('product_id', $related_product->id)) {
                    throw new \Exception('El usuario ya tiene uno de los productos relacionados. Por favor, elija otro producto.', 400);
                }
            }
        }

        if ($product->type === 'maximizer') {
            foreach ($product->related_products as $related_product) {
                $related_product = Product::where('identifier', $related_product)->first();
                if ($enrollments->contains('product_id', $related_product->id)) {
                    throw new \Exception('El usuario ya tiene uno de los productos relacionados. Por favor, elija otro producto.', 400);
                }
            }
        }
        /** 3. OBTENER EL PROVEEDOR DE PAGO **/

        $provider = $data['country'] === 'argentina' ? 'uala' : 'stripe';
        $paymentService = $provider === 'uala'
            ? new PaymentUala()
            : new PaymentStripe();

        return $paymentService->createPaymentLink($product, $user);
    }
    
}
