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
                c.name as curso,
                c.identifier as identificador_curso,
                b.name as paquete,
                b.identifier as identificador_paquete,
                p.created_at as fecha_inscripcion,
                p.status as estado_pago
            FROM student s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN user_devices ud ON u.id = ud.user_id
            LEFT JOIN enrollments e ON u.id = e.user_id
            LEFT JOIN courses c ON e.course_id = c.id
            LEFT JOIN bundles b ON e.bundle_id = b.id
            LEFT JOIN payments p ON e.payment_id = p.id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('alumnos_view');
        DB::statement('DROP VIEW IF EXISTS alumnos_inscriptos');
    }
};
