<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\Payments\PaymentStripe;
use App\Services\Payments\PaymentUala;
use Illuminate\Support\Facades\Validator;

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
            'type' => ['required', 'in:course,bundle'],
            'identifier' => ['required', 'string'],
            'provider' => ['nullable', 'in:stripe,uala']
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        if (!$user->email_verified_at) {
            throw new \Exception('El usuario no es un alumno registrado', 401);
        }

        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $data['identifier'])
            ->orWhere('bundle_id', $data['identifier'])
            ->first();

        if ($enrollment) {
            throw new \Exception('El usuario ya tiene una inscripción activa a este seminario o bundle', 401);
        }

        $country = strtolower(Student::where('user_id', $user->id)->first()->country);

        if (isset($data['provider'])) {
            return $data['provider'] === 'uala' 
                ? PaymentUala::createPaymentLink($data, $user)
                : PaymentStripe::createPaymentLink($data, $user);
        }

        return $country === 'argentina'
            ? PaymentUala::createPaymentLink($data, $user)
            : PaymentStripe::createPaymentLink($data, $user);
    }
}
