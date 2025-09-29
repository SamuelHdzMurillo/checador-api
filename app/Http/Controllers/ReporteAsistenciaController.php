<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Empleado;
use App\Models\Horario;
use App\Models\Checada;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReporteAsistenciaController extends Controller
{
    /**
     * Verificar horarios disponibles (para debugging)
     */
    public function verificarHorarios(Request $request)
    {
        $cct = $request->get('cct');
        
        // Obtener empleados del CCT
        $empleados = Empleado::where('EMPLEADO_CCT_CLAVE', $cct)
            ->orWhere('EMPLEADO_CCT_NOMBRE', 'like', '%' . $cct . '%')
            ->get();

        $resultado = [];
        
        foreach ($empleados as $empleado) {
            $horarios = Horario::where('numero_empleado', $empleado->EMPLEADO_NO)->get();
            
            $resultado[] = [
                'empleado' => $empleado->EMPLEADO_NOMBRE_COMPLETO,
                'numero_empleado' => $empleado->EMPLEADO_NO,
                'horarios' => $horarios->map(function($h) {
                    $nombreDia = $this->obtenerNombreDia($h->dia);
                    return [
                        'dia_numero' => $h->dia,
                        'dia_nombre' => $nombreDia,
                        'hora_entrada' => $h->hora_entrada,
                        'hora_salida' => $h->hora_salida
                    ];
                })
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $resultado
        ]);
    }

    /**
     * Generar reporte de asistencia por rango de fechas y CCT
     */
    public function generarReporte(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'cct' => 'required|string'
        ]);

        $fechaInicio = Carbon::parse($request->fecha_inicio);
        $fechaFin = Carbon::parse($request->fecha_fin);
        $cct = $request->cct;

        // Obtener empleados del CCT especificado
        $empleados = Empleado::where('EMPLEADO_CCT_CLAVE', $cct)
            ->orWhere('EMPLEADO_CCT_NOMBRE', 'like', '%' . $cct . '%')
            ->get();

        $reporte = [];

        foreach ($empleados as $empleado) {
            $datosEmpleado = [
                'numero_empleado' => $empleado->EMPLEADO_NO,
                'nombre' => $empleado->EMPLEADO_NOMBRE_COMPLETO,
                'puesto' => $empleado->EMPLEADO_PUESTO,
                'cct' => $empleado->EMPLEADO_CCT_NOMBRE,
                'asistencias' => []
            ];

            // Obtener datos por cada día en el rango
            $fechaActual = $fechaInicio->copy();
            while ($fechaActual->lte($fechaFin)) {
                $diaSemana = $this->obtenerDiaSemana($fechaActual->dayOfWeek);
                
                // Obtener horario del empleado para ese día (los días se guardan como números)
                $horario = Horario::where('numero_empleado', $empleado->EMPLEADO_NO)
                    ->where('dia', $diaSemana)
                    ->first();
                
                // Debug: Log para verificar la búsqueda
                \Log::info("Buscando horario para empleado: {$empleado->EMPLEADO_NO}, día: {$diaSemana}");
                if ($horario) {
                    \Log::info("Horario encontrado: entrada={$horario->hora_entrada}, salida={$horario->hora_salida}");
                } else {
                    \Log::info("No se encontró horario para este empleado y día");
                }

                // Obtener checadas del empleado para esa fecha
                $checadas = Checada::where('numero_empleado', $empleado->EMPLEADO_NO)
                    ->whereDate('fecha', $fechaActual->format('Y-m-d'))
                    ->orderBy('hora')
                    ->get();

                // Formatear horarios y checadas para mostrar
                $horaEntradaFormateada = $this->formatearHoraEntrada($horario, $checadas);
                $horaSalidaFormateada = $this->formatearHoraSalida($horario, $checadas);

                // Calcular estatus de entrada y salida
                $estatusEntrada = $this->calcularEstatusEntrada($horario, $checadas);
                $estatusSalida = $this->calcularEstatusSalida($horario, $checadas);
                $tiempoTrabajado = $this->calcularTiempoTrabajadoFormateado($checadas);

                $asistenciaDia = [
                    'fecha' => $fechaActual->format('Y-m-d'),
                    'dia' => $this->obtenerNombreDia($diaSemana),
                    'hora_entrada' => $horaEntradaFormateada,
                    'estatus_entrada' => $estatusEntrada,
                    'hora_salida' => $horaSalidaFormateada,
                    'estatus_salida' => $estatusSalida,
                    'tiempo_trabajado' => $tiempoTrabajado,
                    'horario_entrada_programada' => $horario ? $horario->hora_entrada : null,
                    'horario_salida_programada' => $horario ? $horario->hora_salida : null,
                    'checadas' => $checadas->pluck('hora')->toArray(),
                    'primera_checada' => $checadas->first() ? $checadas->first()->hora : null,
                    'ultima_checada' => $checadas->last() ? $checadas->last()->hora : null,
                    'total_checadas' => $checadas->count()
                ];

                // Calcular retrasos y tiempo trabajado
                if ($horario && $checadas->isNotEmpty()) {
                    $asistenciaDia['retraso_entrada'] = $this->calcularRetraso($horario->hora_entrada, $asistenciaDia['primera_checada']);
                    $asistenciaDia['tiempo_trabajado'] = $this->calcularTiempoTrabajado($asistenciaDia['primera_checada'], $asistenciaDia['ultima_checada']);
                }

                $datosEmpleado['asistencias'][] = $asistenciaDia;
                $fechaActual->addDay();
            }

            $reporte[] = $datosEmpleado;
        }

        return response()->json([
            'success' => true,
            'data' => $reporte,
            'filtros' => [
                'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                'fecha_fin' => $fechaFin->format('Y-m-d'),
                'cct' => $cct
            ]
        ]);
    }

    /**
     * Generar reporte en formato PDF
     */
    public function generarReportePDF(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'cct' => 'required|string'
        ]);

        $fechaInicio = Carbon::parse($request->fecha_inicio);
        $fechaFin = Carbon::parse($request->fecha_fin);
        $cct = $request->cct;

        // Obtener datos del reporte
        $reporteData = $this->generarReporte($request);
        $reporte = json_decode($reporteData->getContent(), true)['data'];

        // Generar HTML para el PDF
        $html = $this->generarHTMLReporte($reporte, $fechaInicio, $fechaFin, $cct);

        // Configurar PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');
        
        return $pdf->download('reporte_asistencia_' . $fechaInicio->format('Y-m-d') . '_' . $fechaFin->format('Y-m-d') . '.pdf');
    }

    /**
     * Formatear hora de entrada: "07:00 - 07:11"
     */
    private function formatearHoraEntrada($horario, $checadas)
    {
        $horaProgramada = $horario ? $this->formatearSoloHora($horario->hora_entrada) : null;
        $primeraChecada = $checadas->first() ? $this->formatearSoloHora($checadas->first()->hora) : null;
        
        if ($horaProgramada && $primeraChecada) {
            return $horaProgramada . ' - ' . $primeraChecada;
        } elseif ($horaProgramada) {
            return $horaProgramada . ' - Sin checada';
        } elseif ($primeraChecada) {
            return 'Sin horario - ' . $primeraChecada;
        } else {
            return 'Sin datos';
        }
    }

    /**
     * Formatear hora de salida: "16:00 - 15:55"
     */
    private function formatearHoraSalida($horario, $checadas)
    {
        $horaProgramada = $horario ? $this->formatearSoloHora($horario->hora_salida) : null;
        $ultimaChecada = $checadas->last() ? $this->formatearSoloHora($checadas->last()->hora) : null;
        
        if ($horaProgramada && $ultimaChecada) {
            return $horaProgramada . ' - ' . $ultimaChecada;
        } elseif ($horaProgramada) {
            return $horaProgramada . ' - Sin checada';
        } elseif ($ultimaChecada) {
            return 'Sin horario - ' . $ultimaChecada;
        } else {
            return 'Sin datos';
        }
    }

    /**
     * Formatear solo la hora sin fecha (HH:MM)
     */
    private function formatearSoloHora($hora)
    {
        if (!$hora) {
            return null;
        }
        
        // Si es un objeto Carbon o DateTime, formatear solo la hora
        if ($hora instanceof \Carbon\Carbon || $hora instanceof \DateTime) {
            return $hora->format('H:i');
        }
        
        // Si es string, intentar parsearlo y extraer solo la hora
        try {
            $carbon = Carbon::parse($hora);
            return $carbon->format('H:i');
        } catch (\Exception $e) {
            // Si no se puede parsear, devolver tal como está
            return $hora;
        }
    }

    /**
     * Obtener número del día de la semana (1=Lunes, 2=Martes, etc.)
     */
    private function obtenerDiaSemana($dayOfWeek)
    {
        // Carbon usa 0=Domingo, 1=Lunes, etc.
        // Pero en la base de datos: 1=Lunes, 2=Martes, etc.
        $diasNumeros = [
            0 => 7, // Domingo = 7
            1 => 1, // Lunes = 1
            2 => 2, // Martes = 2
            3 => 3, // Miércoles = 3
            4 => 4, // Jueves = 4
            5 => 5, // Viernes = 5
            6 => 6  // Sábado = 6
        ];
        
        return $diasNumeros[$dayOfWeek] ?? 1;
    }

    /**
     * Convertir número de día a nombre
     */
    private function obtenerNombreDia($numeroDia)
    {
        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo'
        ];
        
        return $dias[$numeroDia] ?? 'Desconocido';
    }

    /**
     * Calcular retraso en minutos
     */
    private function calcularRetraso($horaHorario, $horaChecada)
    {
        if (!$horaHorario || !$horaChecada) {
            return null;
        }

        $horario = Carbon::parse($horaHorario);
        $checada = Carbon::parse($horaChecada);
        
        if ($checada->gt($horario)) {
            return $checada->diffInMinutes($horario);
        }
        
        return 0;
    }

    /**
     * Calcular estatus de entrada
     */
    private function calcularEstatusEntrada($horario, $checadas)
    {
        if (!$horario) {
            return 'Sin horario';
        }
        
        if ($checadas->isEmpty()) {
            return 'Falta Entrada';
        }
        
        $horaProgramada = Carbon::parse($horario->hora_entrada);
        $primeraChecada = Carbon::parse($checadas->first()->hora);
        
        if ($primeraChecada->gt($horaProgramada->addMinutes(15))) {
            return 'Retraso';
        }
        
        return 'Puntual';
    }

    /**
     * Calcular estatus de salida
     */
    private function calcularEstatusSalida($horario, $checadas)
    {
        if (!$horario) {
            return 'Sin horario';
        }
        
        if ($checadas->count() < 2) {
            return 'Falta';
        }
        
        $horaProgramada = Carbon::parse($horario->hora_salida);
        $ultimaChecada = Carbon::parse($checadas->last()->hora);
        
        if ($ultimaChecada->lt($horaProgramada->subMinutes(15))) {
            return 'Salida Temprano';
        }
        
        return 'Normal';
    }

    /**
     * Calcular tiempo trabajado formateado (HH:MM)
     */
    private function calcularTiempoTrabajadoFormateado($checadas)
    {
        if ($checadas->count() < 2) {
            return '00:00';
        }
        
        try {
            $primeraChecada = Carbon::parse($checadas->first()->hora);
            $ultimaChecada = Carbon::parse($checadas->last()->hora);
            
            $minutos = $ultimaChecada->diffInMinutes($primeraChecada);
            $horas = intval($minutos / 60);
            $minutosRestantes = $minutos % 60;
            
            return sprintf('%02d:%02d', $horas, $minutosRestantes);
        } catch (\Exception $e) {
            return '00:00';
        }
    }

    /**
     * Calcular tiempo trabajado en horas
     */
    private function calcularTiempoTrabajado($primeraChecada, $ultimaChecada)
    {
        if (!$primeraChecada || !$ultimaChecada) {
            return null;
        }

        $inicio = Carbon::parse($primeraChecada);
        $fin = Carbon::parse($ultimaChecada);
        
        return round($fin->diffInMinutes($inicio) / 60, 2);
    }

    /**
     * Generar HTML para el reporte PDF
     */
    private function generarHTMLReporte($reporte, $fechaInicio, $fechaFin, $cct)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Asistencia</title>
            <style>
                @page {
                    size: letter;
                    margin: 0.4in 0.25in;
                }
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 7px; 
                    margin: 0; 
                    padding: 0; 
                    line-height: 1.0;
                }
                .main-header { 
                    display: flex; 
                    justify-content: space-between; 
                    align-items: center; 
                    margin-bottom: 8px; 
                    border-bottom: 1px solid #28a745;
                    padding-bottom: 5px;
                    page-break-inside: avoid;
                }
                .logo-section {
                    display: flex;
                    align-items: center;
                }
                .logo-cecyte {
                    background-color: #28a745;
                    color: white;
                    padding: 4px 6px;
                    border-radius: 2px;
                    font-weight: bold;
                    font-size: 9px;
                    margin-right: 5px;
                }
                .logo-text {
                    color: #28a745;
                    font-size: 8px;
                    font-weight: bold;
                }
                .departamento {
                    color: #28a745;
                    font-size: 7px;
                    text-align: right;
                }
                .reporte-title {
                    text-align: center;
                    font-size: 10px;
                    font-weight: bold;
                    color: black;
                    margin: 8px 0;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 5px; 
                    font-size: 6px;
                }
                th, td { 
                    border: 1px solid #000; 
                    padding: 2px; 
                    text-align: center; 
                    font-size: 6px;
                }
                th { 
                    background-color: #28a745; 
                    color: white; 
                    font-weight: bold; 
                    font-size: 6px;
                }
                .employee-header { 
                    background-color: #f8f9fa; 
                    font-weight: bold; 
                    text-align: left; 
                    padding: 3px 4px; 
                    font-size: 8px;
                }
                .time-cell { 
                    font-family: monospace; 
                    font-size: 5px; 
                }
                .estatus-falta { 
                    color: #dc3545; 
                    font-weight: bold; 
                    font-size: 6px;
                }
                .estatus-normal { 
                    color: #28a745; 
                    font-weight: bold; 
                    font-size: 6px;
                }
                .sin-datos { 
                    color: #6c757d; 
                    font-size: 6px;
                }
                .summary-row { 
                    background-color: black; 
                    color: white; 
                    font-weight: bold; 
                    font-size: 6px;
                }
                .summary-total { 
                    background-color: white; 
                    color: black; 
                }
                .page-break { 
                    page-break-before: always; 
                }
                .no-break { 
                    page-break-inside: avoid; 
                }
            </style>
        </head>
        <body>
            <div class="main-header">
                <div class="logo-section">
                    <div class="logo-cecyte">CECYTE</div>
                    <div>
                        <div class="logo-text">Baja California Sur</div>
                    </div>
                </div>
                <div class="departamento">
                    Procesado en Dirección General<br>
                    Departamento de Informática
                </div>
            </div>
            
            <div class="reporte-title">
                REPORTE DETALLADO DE REGISTRO ENTRADA Y SALIDA EN EL PERIODO: ' . $fechaInicio->format('Y-m-d') . ' - ' . $fechaFin->format('Y-m-d') . '
            </div>
        ';

        $empleadoIndex = 0;
        $maxEmpleadosPorPagina = 3; // Máximo de empleados por página
        
        foreach ($reporte as $empleado) {
            // Calcular si necesitamos salto de página
            $necesitaSaltoPagina = ($empleadoIndex > 0 && $empleadoIndex % $maxEmpleadosPorPagina == 0);
            
            if ($necesitaSaltoPagina) {
                $html .= '<div class="page-break"></div>';
            }
            
            $html .= '
            <div class="no-break" style="margin-bottom: 8px;">
                <div class="employee-header">
                    ' . $empleado['numero_empleado'] . ' ' . $empleado['nombre'] . ' ' . $empleado['puesto'] . '
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Hora Entrada</th>
                            <th>Estatus</th>
                            <th>Hora Salida</th>
                            <th>Estatus</th>
                            <th>Tiempo Trabajado</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            foreach ($empleado['asistencias'] as $asistencia) {
                $estatusEntradaClass = isset($asistencia['estatus_entrada']) && $asistencia['estatus_entrada'] === 'Falta Entrada' ? 'color: #dc3545; font-weight: bold;' : 
                                      (isset($asistencia['estatus_entrada']) && $asistencia['estatus_entrada'] === 'Puntual' ? 'color: #28a745; font-weight: bold;' : 'color: #6c757d;');
                $estatusSalidaClass = isset($asistencia['estatus_salida']) && $asistencia['estatus_salida'] === 'Falta' ? 'color: #dc3545; font-weight: bold;' : 
                                     (isset($asistencia['estatus_salida']) && $asistencia['estatus_salida'] === 'Normal' ? 'color: #28a745; font-weight: bold;' : 'color: #6c757d;');
                
                $html .= '
                <tr>
                    <td>' . $asistencia['fecha'] . '</td>
                    <td>' . strtolower($asistencia['dia']) . '</td>
                    <td class="time-cell">' . ($asistencia['hora_entrada'] ?? '00:00 - 00:00') . '</td>
                    <td class="' . (isset($asistencia['estatus_entrada']) && $asistencia['estatus_entrada'] === 'Falta Entrada' ? 'estatus-falta' : 
                                      (isset($asistencia['estatus_entrada']) && $asistencia['estatus_entrada'] === 'Puntual' ? 'estatus-normal' : 'sin-datos')) . '">' . ($asistencia['estatus_entrada'] ?? 'Falta') . '</td>
                    <td class="time-cell">' . ($asistencia['hora_salida'] ?? '00:00 - 00:00') . '</td>
                    <td class="' . (isset($asistencia['estatus_salida']) && $asistencia['estatus_salida'] === 'Falta' ? 'estatus-falta' : 
                                     (isset($asistencia['estatus_salida']) && $asistencia['estatus_salida'] === 'Normal' ? 'estatus-normal' : 'sin-datos')) . '">' . ($asistencia['estatus_salida'] ?? 'Falta') . '</td>
                    <td>' . ($asistencia['tiempo_trabajado'] ?? '00:00') . '</td>
                </tr>
                ';
            }

            // Calcular totales del empleado
            $totalHoras = $this->calcularTotalHoras($empleado['asistencias']);
            $horasTrabajadas = $this->calcularHorasTrabajadas($empleado['asistencias']);
            $faltasDias = $this->calcularFaltasDias($empleado['asistencias']);
            $entradaFaltante = $this->calcularEntradaFaltante($empleado['asistencias']);
            $salidaFaltante = $this->calcularSalidaFaltante($empleado['asistencias']);
            
            $html .= '
                    </tbody>
                </table>
                
                <table style="margin-bottom: 5px;">
                    <tr class="summary-row">
                        <td class="summary-total">Total horas: ' . $totalHoras . '</td>
                        <td>Horas trabajadas: ' . $horasTrabajadas . '</td>
                        <td>Faltas días: ' . $faltasDias . '</td>
                        <td>Entrada faltante: ' . $entradaFaltante . '</td>
                        <td>Salida faltante: ' . $salidaFaltante . '</td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </div>
            ';
            
            $empleadoIndex++;
        }

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Calcular total de horas del empleado
     */
    private function calcularTotalHoras($asistencias)
    {
        $totalMinutos = 0;
        foreach ($asistencias as $asistencia) {
            if (isset($asistencia['tiempo_trabajado']) && $asistencia['tiempo_trabajado'] !== '00:00') {
                $tiempo = explode(':', $asistencia['tiempo_trabajado']);
                if (count($tiempo) >= 2) {
                    $horas = intval($tiempo[0]);
                    $minutos = intval($tiempo[1]);
                    $totalMinutos += ($horas * 60) + $minutos;
                }
            }
        }
        $horas = intval($totalMinutos / 60);
        $minutos = $totalMinutos % 60;
        return sprintf('%d:%02d', $horas, $minutos);
    }

    /**
     * Calcular horas trabajadas
     */
    private function calcularHorasTrabajadas($asistencias)
    {
        $diasTrabajados = 0;
        foreach ($asistencias as $asistencia) {
            if (isset($asistencia['tiempo_trabajado']) && 
                $asistencia['tiempo_trabajado'] !== '00:00' && 
                $asistencia['tiempo_trabajado'] !== null) {
                $diasTrabajados++;
            }
        }
        return $diasTrabajados;
    }

    /**
     * Calcular faltas de días
     */
    private function calcularFaltasDias($asistencias)
    {
        $faltas = 0;
        foreach ($asistencias as $asistencia) {
            if (isset($asistencia['estatus_entrada']) && 
                $asistencia['estatus_entrada'] === 'Falta Entrada') {
                $faltas++;
            }
        }
        return $faltas;
    }

    /**
     * Calcular entradas faltantes
     */
    private function calcularEntradaFaltante($asistencias)
    {
        $faltantes = 0;
        foreach ($asistencias as $asistencia) {
            if (isset($asistencia['estatus_entrada']) && 
                $asistencia['estatus_entrada'] === 'Falta Entrada') {
                $faltantes++;
            }
        }
        return $faltantes;
    }

    /**
     * Calcular salidas faltantes
     */
    private function calcularSalidaFaltante($asistencias)
    {
        $faltantes = 0;
        foreach ($asistencias as $asistencia) {
            if (isset($asistencia['estatus_salida']) && 
                $asistencia['estatus_salida'] === 'Falta') {
                $faltantes++;
            }
        }
        return $faltantes;
    }
}
