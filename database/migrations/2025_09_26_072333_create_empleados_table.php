<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->string('EMPLEADO_NOMBRE_COMPLETO');
            $table->string('EMPLEADO_CURP', 18)->unique();
            $table->string('EMPLEADO_RFC', 13)->unique();
            $table->string('EMPLEADO_CCT_NO')->nullable();
            $table->string('EMPLEADO_CCT_CLAVE')->nullable();
            $table->string('EMPLEADO_CCT_NOMBRE')->nullable();
            $table->string('EMPLEADO_PUESTO')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
