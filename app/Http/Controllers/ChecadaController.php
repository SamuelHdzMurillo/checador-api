<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Checada;
use App\Models\Empleado;
use Carbon\Carbon;

class ChecadaController extends Controller
{
    /**
     * Registrar una nueva checada
     */
    public function registrarChecada(Request $request)
    {
        $request->validate([
            'numero_empleado' => 'required|integer',
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i:s'
        ]);

        try {
            // Verificar que el empleado existe
            $empleado = Empleado::where('EMPLEADO_NO', $request->numero_empleado)->first();
            if (!$empleado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            // Crear la checada
            $checada = Checada::create([
                'numero_empleado' => $request->numero_empleado,
                'fecha' => $request->fecha,
                'hora' => $request->hora
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Checada registrada exitosamente',
                'data' => $checada
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la checada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las checadas
     */
    public function obtenerChecadas(Request $request)
    {
        try {
            $query = Checada::with('empleado');

            // Filtro por número de empleado
            if ($request->has('numero_empleado')) {
                $query->where('numero_empleado', $request->numero_empleado);
            }

            // Filtro por rango de fechas
            if ($request->has('fecha_inicio')) {
                $query->where('fecha', '>=', $request->fecha_inicio);
            }

            if ($request->has('fecha_fin')) {
                $query->where('fecha', '<=', $request->fecha_fin);
            }

            $checadas = $query->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $checadas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las checadas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener checadas de un empleado específico
     */
    public function obtenerChecadasPorEmpleado($numeroEmpleado, Request $request)
    {
        try {
            $query = Checada::where('numero_empleado', $numeroEmpleado)->with('empleado');

            // Filtro por rango de fechas
            if ($request->has('fecha_inicio')) {
                $query->where('fecha', '>=', $request->fecha_inicio);
            }

            if ($request->has('fecha_fin')) {
                $query->where('fecha', '<=', $request->fecha_fin);
            }

            $checadas = $query->orderBy('fecha', 'desc')->orderBy('hora', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $checadas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las checadas del empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar múltiples checadas desde un array de datos
     */
    public function procesarChecadas(Request $request)
    {
        $request->validate([
            'checadas' => 'required|array',
            'checadas.*.numero_empleado' => 'required|integer',
            'checadas.*.fecha' => 'required|date',
            'checadas.*.hora' => 'required|date_format:H:i:s'
        ]);

        try {
            $checadasRegistradas = [];
            $errores = [];

            foreach ($request->checadas as $index => $checadaData) {
                try {
                    // Verificar que el empleado existe
                    $empleado = Empleado::where('EMPLEADO_NO', $checadaData['numero_empleado'])->first();
                    if (!$empleado) {
                        $errores[] = "Fila {$index}: Empleado {$checadaData['numero_empleado']} no encontrado";
                        continue;
                    }

                    // Crear la checada
                    $checada = Checada::create([
                        'numero_empleado' => $checadaData['numero_empleado'],
                        'fecha' => $checadaData['fecha'],
                        'hora' => $checadaData['hora']
                    ]);

                    $checadasRegistradas[] = $checada;

                } catch (\Exception $e) {
                    $errores[] = "Fila {$index}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Se procesaron " . count($checadasRegistradas) . " checadas exitosamente",
                'checadas_registradas' => count($checadasRegistradas),
                'errores' => $errores,
                'data' => $checadasRegistradas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar las checadas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar archivo de checadas
     * Formato esperado: numero_empleado	fecha_hora	1	0	1	0
     */
    public function procesarArchivoChecadas(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:txt,csv,dat|max:10240' // 10MB máximo
        ]);

        try {
            $archivo = $request->file('archivo');
            $contenido = file_get_contents($archivo->getPathname());
            $lineas = explode("\n", $contenido);
            
            $checadasRegistradas = [];
            $errores = [];
            $lineaNumero = 0;

            foreach ($lineas as $linea) {
                $lineaNumero++;
                
                // Saltar líneas vacías
                if (trim($linea) === '') {
                    continue;
                }

                try {
                    // Limpiar espacios al inicio y final de la línea
                    $lineaLimpia = trim($linea);
                    
                    // Dividir la línea por tabulaciones o espacios múltiples
                    $datos = preg_split('/\s+/', $lineaLimpia);
                    
                    if (count($datos) < 3) {
                        $errores[] = "Línea {$lineaNumero}: Formato inválido - se esperan al menos 3 campos (número, fecha, hora)";
                        continue;
                    }

                    $numeroEmpleado = (int) $datos[0];
                    $fecha = $datos[1];
                    $hora = $datos[2];
                    
                    // Validar formato de fecha
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                        $errores[] = "Línea {$lineaNumero}: Formato de fecha inválido - {$fecha}";
                        continue;
                    }
                    
                    // Validar formato de hora
                    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
                        $errores[] = "Línea {$lineaNumero}: Formato de hora inválido - {$hora}";
                        continue;
                    }

                    // Verificar que el empleado existe
                    $empleado = Empleado::where('EMPLEADO_NO', $numeroEmpleado)->first();
                    if (!$empleado) {
                        $errores[] = "Línea {$lineaNumero}: Empleado {$numeroEmpleado} no encontrado";
                        continue;
                    }

                    // Verificar si ya existe una checada con la misma fecha, hora y empleado
                    $checadaExistente = Checada::where('numero_empleado', $numeroEmpleado)
                        ->where('fecha', $fecha)
                        ->where('hora', $hora)
                        ->first();
                    
                    if ($checadaExistente) {
                        $errores[] = "Línea {$lineaNumero}: Ya existe una checada para el empleado {$numeroEmpleado} en {$fecha} {$hora}";
                        continue;
                    }

                    // Crear la checada
                    $checada = Checada::create([
                        'numero_empleado' => $numeroEmpleado,
                        'fecha' => $fecha,
                        'hora' => $hora
                    ]);

                    $checadasRegistradas[] = $checada;

                } catch (\Exception $e) {
                    $errores[] = "Línea {$lineaNumero}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Se procesaron " . count($checadasRegistradas) . " checadas exitosamente del archivo",
                'total_lineas' => $lineaNumero,
                'checadas_registradas' => count($checadasRegistradas),
                'errores' => $errores,
                'data' => $checadasRegistradas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }
}
