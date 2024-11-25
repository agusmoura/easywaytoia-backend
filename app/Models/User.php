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

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
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

    public static function registerUser(array $data)
    {
        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $user = self::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
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
            'address' => $data['address'],
        ]);

        // Generate verification URL
        // $verificationUrl = URL::temporarySignedRoute(
        //     'verification.verify',
        //     now()->addMinutes(60),
        //     [
        //         'id' => $user->getKey(),
        //         'hash' => sha1($user->getEmailForVerification()),
        //     ]
        // );

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
}
