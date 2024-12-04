<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\Payments\PaymentStripe;
use App\Services\Payments\PaymentUala;
use Illuminate\Support\Facades\Validator;
use App\Models\Course;
use App\Models\Bundle;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payment_id',
        'provider',
        'status',
        'amount',
        'currency',
        'product_id',
        'metadata'
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
        $validator = Validator::make($data, [
            'course_id' => 'required_without:bundle_id|exists:courses,id|nullable',
            'bundle_id' => 'required_without:course_id|exists:bundles,id|nullable',
            'provider' => ['nullable', 'in:stripe,uala']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        if (!$user->email_verified_at) {
            throw new \Exception('El usuario no es un alumno registrado', 401);
        }

        // Verificar si ya existe una inscripción
        $query = Enrollment::where('user_id', $user->id);
        if ($data['course_id']) {
            $query->where('course_id', $data['course_id']);
        } elseif ($data['bundle_id']) {
            $query->where('bundle_id', $data['bundle_id']);
        }
        
        $enrollment = $query->first();

        if ($enrollment) {
            throw new \Exception('El usuario ya tiene una inscripción activa a este seminario o bundle', 401);
        }

        $country = strtolower(Student::where('user_id', $user->id)->first()->country ?? 'default');

        // Preparar datos para el pago
        $paymentData = [
            'type' => isset($data['course_id']) ? 'course' : 'bundle',
            'identifier' => $data['course_id'] ?? $data['bundle_id'],
        ];

        if (isset($data['provider'])) {
            return $data['provider'] === 'uala' 
                ? PaymentUala::createPaymentLink($paymentData, $user)
                : PaymentStripe::createPaymentLink($paymentData, $user);
        }

        return $country === 'argentina'
            ? PaymentUala::createPaymentLink($paymentData, $user)
            : PaymentStripe::createPaymentLink($paymentData, $user);
    }

    public static function getPaymentLink($paymentData, $user)
    {
        return $paymentData['provider'] === 'uala'
            ? PaymentUala::createPaymentLink($paymentData, $user)
            : PaymentStripe::createPaymentLink($paymentData, $user);
    }

    public static function checkout(array $data)
    {
        // Validar datos de entrada
        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'course_identifier' => ['nullable', 'string'],
            'bundle_identifier' => ['nullable', 'string'],
        ]);


        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        /* verificar que exista el curso o bundle */
        if (isset($data['course_identifier'])) {
            $course = Course::where('identifier', $data['course_identifier'])->first();
            if (!$course) {
                throw new \Exception('El curso no existe', 404);
            }
        }

        if (isset($data['bundle_identifier'])) {
            $bundle = Bundle::where('identifier', $data['bundle_identifier'])->first();
            if (!$bundle) {
                throw new \Exception('El bundle no existe', 404);
            }
        }

        // Crear o recuperar usuario
        try {
            $user = User::registerUser($data, validatedMail: true);
            $user = $user['user']->toArray();
            } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 500);
        }

        $data['provider'] = $data['country'] === 'argentina'
            ? 'uala'
            : 'stripe';
     
        // Generar link de pago

        $success_page = $data['course_identifier'] ? Course::where('identifier', $data['course_identifier'])->first()->success_page : Bundle::where('identifier', $data['bundle_identifier'])->first()->success_page;

        $paymentData = [
            'provider' => $data['provider'] ?? null,
            'type' => $data['course_identifier'] ? 'course' : 'bundle',
            'identifier' => $data['course_identifier'] ?? $data['bundle_identifier'],
            'success_page' => $success_page
        ];

        $result = self::getPaymentLink($paymentData, $user);

        Log::info('User', $user);

        return [
            'message' => 'Checkout iniciado exitosamente',
            'payment_url' => $result['payment_link'],
            'user' => [
                'username' => $user['username'],
                'email' => $user['email'],
            ]
        ];
    }
    
}
