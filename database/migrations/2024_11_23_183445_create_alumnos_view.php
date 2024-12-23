<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS alumnos_inscriptos');
        
        DB::statement('CREATE VIEW alumnos_inscriptos AS
            SELECT 
                s.id as id_alumno,
                s.name as nombre,
                s.last_name as apellido,
                s.country as pais,
                s.phone as telefono,
                u.email as email,
                u.username as usuario,
                ud.last_activity as ultimo_acceso,
                p.name as producto,
                p.identifier as identificador_producto,
                p.type as tipo_producto,
                pay.created_at as fecha_inscripcion,
                pay.status as estado_pago
            FROM student s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN user_devices ud ON u.id = ud.user_id
            LEFT JOIN enrollments e ON u.id = e.user_id
            LEFT JOIN products p ON e.product_id = p.id
            LEFT JOIN payments pay ON e.payment_id = pay.id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumnos_inscriptos');
    }
};
