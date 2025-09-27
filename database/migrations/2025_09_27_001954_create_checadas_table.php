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
        Schema::create('checadas', function (Blueprint $table) {
            $table->id();
            $table->integer('numero_empleado'); // Número del empleado
            $table->datetime('fecha_hora'); // Fecha y hora de la checada
            $table->timestamps();
            
            // Índices para mejorar el rendimiento
            $table->index('numero_empleado');
            $table->index('fecha_hora');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checadas');
    }
};
