<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Checada extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_empleado',
        'fecha',
        'hora'
    ];

    protected $table = 'checadas';

    // Relación con Empleado
    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'numero_empleado', 'EMPLEADO_NO');
    }

    // Cast de fecha y hora para manejo automático
    protected $casts = [
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s',
    ];
}
