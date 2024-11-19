<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Alumno extends Model
{
    use HasFactory;

    protected $table = 'alumno';

    protected $fillable = [
        'user_id',
        'nombre',
        'apellido',
        'pais',
        'telefono',
        'direccion',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
