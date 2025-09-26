<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Horario;
use App\Models\Empleado;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HorarioController extends Controller
{
    public function procesarExcelHorarios(Request $request)
    {
        try {
            // Validar que se haya enviado un archivo
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB máximo
                'limpiar_horarios' => 'boolean' // Opcional: limpiar horarios existentes antes de procesar
            ]);

            $limpiarHorarios = $request->boolean('limpiar_horarios', false);

            $archivo = $request->file('excel_file');
            
            // Cargar el archivo Excel
            $spreadsheet = IOFactory::load($archivo->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Verificar que el archivo tenga al menos una fila de datos (excluyendo encabezados)
            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo Excel debe contener al menos una fila de datos'
                ], 400);
            }

            // Obtener encabezados (primera fila)
            $encabezados = array_shift($rows);
            
            // Mapear los encabezados a los campos de la base de datos
            $mapeoCampos = [
                'numero_empleado' => null,
                'dia' => null,
                'hora_entrada' => null,
                'hora_salida' => null
            ];

            // Buscar las columnas correspondientes en el Excel
            foreach ($encabezados as $index => $encabezado) {
                $encabezadoLimpio = trim(strtoupper($encabezado));
                
                // Buscar coincidencias exactas y también variaciones comunes
                $coincidencias = [
                    'numero_empleado' => ['NUMERO_EMPLEADO', 'NUMERO EMPLEADO', 'NUM_EMPLEADO', 'EMPLEADO_NO'],
                    'dia' => ['DIA', 'DÍA', 'DAY'],
                    'hora_entrada' => ['HORA_ENTRADA', 'HORA ENTRADA', 'ENTRADA', 'HORA_INICIO'],
                    'hora_salida' => ['HORA_SALIDA', 'HORA SALIDA', 'SALIDA', 'HORA_FIN']
                ];
                
                foreach ($coincidencias as $campo => $variaciones) {
                    if (in_array($encabezadoLimpio, $variaciones)) {
                        $mapeoCampos[$campo] = $index;
                        break;
                    }
                }
            }

            // Verificar que se encontraron los campos obligatorios
            $camposObligatorios = ['numero_empleado', 'dia', 'hora_entrada', 'hora_salida'];
            $camposNoEncontrados = [];
            
            foreach ($camposObligatorios as $campo) {
                if ($mapeoCampos[$campo] === null) {
                    $camposNoEncontrados[] = $campo;
                }
            }
            
            if (!empty($camposNoEncontrados)) {
                return response()->json([
                    'success' => false,
                    'message' => "No se encontraron las siguientes columnas: " . implode(', ', $camposNoEncontrados),
                    'columnas_detectadas' => $encabezados,
                    'mapeo_encontrado' => array_filter($mapeoCampos, function($value) {
                        return $value !== null;
                    })
                ], 400);
            }

            // Si se solicita limpiar horarios, eliminar todos los horarios existentes
            if ($limpiarHorarios) {
                Horario::truncate();
            }

            $horariosProcesados = 0;
            $horariosCreados = 0;
            $horariosActualizados = 0;
            $errores = [];
            $empleadosNoEncontrados = [];

            // Procesar cada fila
            foreach ($rows as $numeroFila => $fila) {
                try {
                    // Saltar filas vacías
                    if (empty(array_filter($fila))) {
                        continue;
                    }

                    $datosHorario = [
                        'numero_empleado' => trim($fila[$mapeoCampos['numero_empleado']] ?? ''),
                        'dia' => trim($fila[$mapeoCampos['dia']] ?? ''),
                        'hora_entrada' => trim($fila[$mapeoCampos['hora_entrada']] ?? ''),
                        'hora_salida' => trim($fila[$mapeoCampos['hora_salida']] ?? '')
                    ];

                    // Validar campos obligatorios
                    if (empty($datosHorario['numero_empleado']) || 
                        empty($datosHorario['dia']) || 
                        empty($datosHorario['hora_entrada']) || 
                        empty($datosHorario['hora_salida'])) {
                        $errores[] = "Fila " . ($numeroFila + 2) . ": Faltan campos obligatorios";
                        continue;
                    }

                    // Buscar empleado por número de empleado
                    $empleado = Empleado::where('EMPLEADO_NO', $datosHorario['numero_empleado'])->first();

                    if (!$empleado) {
                        $empleadosNoEncontrados[] = "Fila " . ($numeroFila + 2) . ": Empleado con número {$datosHorario['numero_empleado']} no encontrado";
                        continue;
                    }

                    // Verificar si ya existe un horario exacto para este empleado (mismo día, misma hora entrada y salida)
                    $horarioExistente = Horario::where('empleado_id', $empleado->id)
                        ->where('dia', $datosHorario['dia'])
                        ->where('hora_entrada', $datosHorario['hora_entrada'])
                        ->where('hora_salida', $datosHorario['hora_salida'])
                        ->first();

                    // Preparar datos para insertar/actualizar
                    $datosParaGuardar = [
                        'numero_empleado' => $datosHorario['numero_empleado'],
                        'dia' => $datosHorario['dia'],
                        'hora_entrada' => $datosHorario['hora_entrada'],
                        'hora_salida' => $datosHorario['hora_salida'],
                        'empleado_id' => $empleado->id
                    ];

                    if ($horarioExistente) {
                        // Si existe exactamente el mismo horario, no hacer nada
                        $horariosActualizados++;
                    } else {
                        // Crear nuevo horario (permite múltiples horarios por empleado y día)
                        // Esto permite que un empleado tenga varios horarios en el mismo día
                        // Ejemplo: Turno mañana (08:00-12:00) y turno tarde (14:00-18:00)
                        Horario::create($datosParaGuardar);
                        $horariosCreados++;
                    }

                    $horariosProcesados++;

                } catch (\Exception $e) {
                    $errores[] = "Fila " . ($numeroFila + 2) . ": " . $e->getMessage();
                    Log::error("Error procesando horario en fila " . ($numeroFila + 2) . ": " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Archivo de horarios procesado exitosamente',
                'data' => [
                    'horarios_procesados' => $horariosProcesados,
                    'horarios_creados' => $horariosCreados,
                    'horarios_actualizados' => $horariosActualizados,
                    'empleados_no_encontrados' => $empleadosNoEncontrados,
                    'errores' => $errores,
                    'limpiar_horarios' => $limpiarHorarios
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error procesando archivo Excel de horarios: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function obtenerHorarios(Request $request)
    {
        try {
            $horarios = Horario::with('empleado')->orderBy('numero_empleado')->orderBy('dia')->get();
            
            return response()->json([
                'success' => true,
                'data' => $horarios
            ]);
        } catch (\Exception $e) {
            Log::error("Error obteniendo horarios: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los horarios'
            ], 500);
        }
    }

    public function obtenerHorariosPorEmpleado(Request $request, $numeroEmpleado)
    {
        try {
            $empleado = Empleado::where('EMPLEADO_NO', $numeroEmpleado)->first();
            
            if (!$empleado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            $horarios = $empleado->horarios()->orderBy('dia')->orderBy('hora_entrada')->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'empleado' => $empleado,
                    'horarios' => $horarios
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error obteniendo horarios del empleado: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los horarios del empleado'
            ], 500);
        }
    }

    public function obtenerHorariosPorEmpleadoYDia(Request $request, $numeroEmpleado, $dia)
    {
        try {
            $empleado = Empleado::where('EMPLEADO_NO', $numeroEmpleado)->first();
            
            if (!$empleado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            $horarios = $empleado->horarios()
                ->where('dia', $dia)
                ->orderBy('hora_entrada')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'empleado' => $empleado,
                    'dia' => $dia,
                    'horarios' => $horarios,
                    'total_horarios' => $horarios->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error obteniendo horarios del empleado por día: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los horarios del empleado por día'
            ], 500);
        }
    }

    public function descargarPlantillaHorarios()
    {
        try {
            // Crear un nuevo spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Configurar encabezados
            $encabezados = [
                'A1' => 'numero_empleado',
                'B1' => 'cct',
                'C1' => 'cct_no',
                'D1' => 'dia',
                'E1' => 'hora_entrada',
                'F1' => 'hora_salida'
            ];
            
            // Escribir encabezados
            foreach ($encabezados as $celda => $valor) {
                $sheet->setCellValue($celda, $valor);
            }
            
            // Agregar datos de ejemplo (mostrando múltiples horarios por día)
            $ejemplos = [
                ['1756', 'D_G', '', '1', '08:00', '12:00'],  // Turno mañana
                ['1756', 'D_G', '', '1', '14:00', '18:00'],  // Turno tarde (mismo empleado, mismo día)
                ['1757', 'D_G', '', '2', '08:00', '15:00'],  // Turno completo
                ['1758', 'D_G', '', '3', '07:00', '11:00'],  // Turno mañana
                ['1758', 'D_G', '', '3', '15:00', '19:00'],  // Turno tarde (mismo empleado, mismo día)
                ['1759', 'D_G', '', '4', '09:00', '17:00'],  // Turno completo
                ['1760', 'D_G', '', '5', '08:00', '12:00'],  // Turno mañana
                ['1760', 'D_G', '', '5', '13:00', '17:00']   // Turno tarde (mismo empleado, mismo día)
            ];
            
            $fila = 2;
            foreach ($ejemplos as $ejemplo) {
                $columna = 'A';
                foreach ($ejemplo as $valor) {
                    $sheet->setCellValue($columna . $fila, $valor);
                    $columna++;
                }
                $fila++;
            }
            
            // Estilizar encabezados
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            $sheet->getStyle('A1:F1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2E8F0');
            
            // Ajustar ancho de columnas
            $sheet->getColumnDimension('A')->setWidth(15);
            $sheet->getColumnDimension('B')->setWidth(10);
            $sheet->getColumnDimension('C')->setWidth(10);
            $sheet->getColumnDimension('D')->setWidth(8);
            $sheet->getColumnDimension('E')->setWidth(12);
            $sheet->getColumnDimension('F')->setWidth(12);
            
            // Crear el archivo Excel
            $writer = new Xlsx($spreadsheet);
            
            // Configurar headers para descarga
            $filename = 'plantilla_horarios_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            return response()->streamDownload(function() use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error generando plantilla de horarios: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
}
