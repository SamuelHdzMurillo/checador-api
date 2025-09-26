<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_empleado',
        'dia',
        'hora_entrada',
        'hora_salida',
        'empleado_id'
    ];

    protected $table = 'horarios';

    // Relación con Empleado
    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }
}
