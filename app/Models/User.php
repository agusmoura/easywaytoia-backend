<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use App\Models\Student;
use App\Models\UserDevice;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public static function registerUser(array $data, $validatedMail = false)
    {
        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $user = self::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => $validatedMail ? now() : null,
        ]);

        if (!$user) {
            throw new \Exception('Error al registrar el usuario');
        }

        // Create student profile
        Student::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'country' => $data['country'],
        ]);


        // Send verification email
        $user->sendEmailVerificationNotification();

        return [
            'user' => $user,
        ];
    }

    public static function loginUser(array $credentials)
    {
        $validator = Validator::make($credentials, [
            'password' => 'required|string',
            'identifier' => 'required|string'
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $loginField = filter_var($credentials['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $loginCredentials = [
            $loginField => $credentials['identifier'],
            'password' => $credentials['password']
        ];

        if (!$token = JWTAuth::attempt($loginCredentials)) {
            throw new \Exception('Credenciales inválidas', 401);
        }

        $user = auth()->user();

        if (!$user->email_verified_at) {
            throw new \Exception('El usuario no es un alumno registrado, por favor verifique su email', 401);
        }

        $deviceId = uniqid('dev_', true);
        self::handleUserDevice($user, $deviceId, $token);

        return [
            'token' => $token,
            'device_id' => $deviceId
        ];
    }

    public static function logoutUser(string $deviceId)
    {
        $user = auth()->user();
        
        UserDevice::where('device_id', $deviceId)
                 ->where('user_id', $user->id)
                 ->delete();

        JWTAuth::invalidate(JWTAuth::getToken());
    }

    private static function handleUserDevice($user, $deviceId, $token)
    {
        $deviceCount = UserDevice::where('user_id', $user->id)->count();
        if ($deviceCount >= 3) {
            UserDevice::where('user_id', $user->id)
                     ->orderBy('last_activity', 'asc')
                     ->first()
                     ->delete();
        }

        UserDevice::updateOrCreate(
            ['device_id' => $deviceId],
            [
                'user_id' => $user->id,
                'token' => $token,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'last_activity' => now()
            ]
        );
    }

    public static function forgotPassword(array $data)
    {
        $validator = Validator::make($data, [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $user = self::where('email', $data['email'])->first();
        if (!$user) {
            throw new \Exception('No encontramos un usuario con ese correo electrónico', 404);
        }

        $token = Password::createToken($user);
        
        // Enviar notificación personalizada
        $user->notify(new ResetPasswordNotification($token));

        return [
            'message' => 'Se ha enviado el enlace de restablecimiento de contraseña a su correo'
        ];
    }

    public static function resetPassword(array $data)
    {   
        $validator = Validator::make($data, [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'password_confirmation' => 'required|same:password'
        ], [
            'token.required' => 'El token de restablecimiento es requerido.',
            'email.required' => 'El correo electrónico es requerido.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'password.required' => 'La contraseña es requerida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password_confirmation.required' => 'La confirmación de contraseña es requerida.',
            'password_confirmation.same' => 'Las contraseñas no coinciden.'
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $user = self::where('email', $data['email'])->first();
        if (!$user) {
            throw new \Exception('No encontramos un usuario con ese correo electrónico', 404);
        }

        // Verificar el token
        $tokenValid = Password::tokenExists($user, $data['token']);
        if (!$tokenValid) {
            throw new \Exception('El token no es válido o ha expirado', 400);
        }

        // Actualizar la contraseña
        $user->password = Hash::make($data['password']);
        $user->save();

        // Eliminar el token de restablecimiento
        Password::deleteToken($user);

        // Cerrar todas las sesiones del usuario
        UserDevice::where('user_id', $user->id)->delete();

        return [
            'message' => 'Contraseña restablecida exitosamente'
        ];
    }

    public static function getAccountInfo($userId)
    {
        $user = self::findOrFail($userId);
        $student = Student::where('user_id', $user->id)->firstOrFail();
        
        // Generar URL del avatar
        $name = urlencode($student->name . '+' . $student->last_name);
        $avatarUrl = "https://ui-avatars.com/api/?name={$name}&size=128&bold=true&background=random";

        // Obtener cursos del estudiante
        $courses = $student->courses()
            ->withPivot(['created_at', 'payment_id'])
            ->join('payments', 'enrollments.payment_id', '=', 'payments.id')
            ->orderBy('enrollments.created_at', 'desc')
            ->get()
            ->map(function ($course) {
                return [
                    'type' => 'course',
                    'name' => $course->name,
                    'purchased_at' => $course->pivot->created_at->format('Y-m-d H:i:s'),
                    'amount' => $course->amount ?? 0
                ];
            });

        // Obtener bundles del estudiante
        $bundles = $student->bundles()
            ->withPivot(['created_at', 'payment_id'])
            ->join('payments', 'enrollments.payment_id', '=', 'payments.id')
            ->orderBy('enrollments.created_at', 'desc')
            ->get()
            ->map(function ($bundle) {
                return [
                    'type' => 'bundle',
                    'name' => $bundle->name,
                    'purchased_at' => $bundle->pivot->created_at->format('Y-m-d H:i:s'),
                    'amount' => $bundle->amount ?? 0
                ];
            });

        // Combinar y ordenar por fecha de compra
        $purchases = $courses->isEmpty() && $bundles->isEmpty() 
            ? [] 
            : $courses->concat($bundles)->sortByDesc('purchased_at')->values();

        Log::info($purchases);

        return [
            'user' => [
                'name' => $student->name,
                'last_name' => $student->last_name,
                'email' => $user->email,
                'phone' => $student->phone,
                'country' => $student->country,
                'avatar_url' => $avatarUrl
            ],
            'purchases' => $purchases
        ];
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function courses()
    {
        return $this->student->courses();
    }

    public function bundles()
    {
        return $this->student->bundles();
    }
}
