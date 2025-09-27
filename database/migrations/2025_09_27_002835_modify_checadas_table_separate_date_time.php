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
        Schema::table('checadas', function (Blueprint $table) {
            // Agregar nuevos campos como nullable primero
            $table->date('fecha')->nullable()->after('numero_empleado');
            $table->time('hora')->nullable()->after('fecha');
        });
        
        // Migrar datos existentes si los hay
        $checadas = DB::table('checadas')->whereNotNull('fecha_hora')->get();
        foreach ($checadas as $checada) {
            $fechaHora = \Carbon\Carbon::parse($checada->fecha_hora);
            DB::table('checadas')
                ->where('id', $checada->id)
                ->update([
                    'fecha' => $fechaHora->format('Y-m-d'),
                    'hora' => $fechaHora->format('H:i:s')
                ]);
        }
        
        Schema::table('checadas', function (Blueprint $table) {
            // Hacer los campos no nulos después de migrar datos
            $table->date('fecha')->nullable(false)->change();
            $table->time('hora')->nullable(false)->change();
            
            // Eliminar el campo fecha_hora
            $table->dropColumn('fecha_hora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checadas', function (Blueprint $table) {
            // Revertir cambios
            $table->datetime('fecha_hora')->after('numero_empleado');
            $table->dropColumn(['fecha', 'hora']);
        });
    }
};
