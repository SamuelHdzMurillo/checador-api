<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    use HasFactory;

    protected $fillable = [
        'EMPLEADO_NO',
        'EMPLEADO_NOMBRE_COMPLETO',
        'EMPLEADO_CURP',
        'EMPLEADO_RFC',
        'EMPLEADO_CCT_NO',
        'EMPLEADO_CCT_CLAVE',
        'EMPLEADO_CCT_NOMBRE',
        'EMPLEADO_PUESTO'
    ];

    protected $table = 'empleados';

    // Relación con Horarios
    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }
}
