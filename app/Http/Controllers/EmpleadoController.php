<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Empleado;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmpleadoController extends Controller
{
    public function procesarExcel(Request $request)
    {
        try {
            // Validar que se haya enviado un archivo
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240' // 10MB máximo
            ]);

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
                'EMPLEADO_NO' => null,
                'EMPLEADO_NOMBRE_COMPLETO' => null,
                'EMPLEADO_CURP' => null,
                'EMPLEADO_RFC' => null,
                'EMPLEADO_CCT_NO' => null,
                'EMPLEADO_CCT_CLAVE' => null,
                'EMPLEADO_CCT_NOMBRE' => null,
                'EMPLEADO_PUESTO' => null
            ];

            // Buscar las columnas correspondientes en el Excel
            foreach ($encabezados as $index => $encabezado) {
                $encabezadoLimpio = trim(strtoupper($encabezado));
                
                // Buscar coincidencias exactas y también variaciones comunes
                $coincidencias = [
                    'EMPLEADO_NO' => ['EMPLEADO_NO', 'NUMERO_EMPLEADO', 'NUMERO EMPLEADO', 'NUM_EMPLEADO'],
                    'EMPLEADO_NOMBRE_COMPLETO' => ['EMPLEADO_NOMBRE_COMPLETO', 'NOMBRE_COMPLETO', 'NOMBRE COMPLETO', 'NOMBRE'],
                    'EMPLEADO_CURP' => ['EMPLEADO_CURP', 'CURP'],
                    'EMPLEADO_RFC' => ['EMPLEADO_RFC', 'RFC'],
                    'EMPLEADO_CCT_NO' => ['EMPLEADO_CCT_NO', 'CCT_NO', 'CCT NO'],
                    'EMPLEADO_CCT_CLAVE' => ['EMPLEADO_CCT_CLAVE', 'CCT_CLAVE', 'CCT CLAVE'],
                    'EMPLEADO_CCT_NOMBRE' => ['EMPLEADO_CCT_NOMBRE', 'CCT_NOMBRE', 'CCT NOMBRE'],
                    'EMPLEADO_PUESTO' => ['EMPLEADO_PUESTO', 'PUESTO']
                ];
                
                foreach ($coincidencias as $campo => $variaciones) {
                    if (in_array($encabezadoLimpio, $variaciones)) {
                        $mapeoCampos[$campo] = $index;
                        break;
                    }
                }
            }

            // Verificar que se encontraron los campos obligatorios
            $camposObligatorios = ['EMPLEADO_NOMBRE_COMPLETO', 'EMPLEADO_CURP', 'EMPLEADO_RFC'];
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

            $empleadosProcesados = 0;
            $empleadosActualizados = 0;
            $empleadosCreados = 0;
            $errores = [];

            // Procesar cada fila
            foreach ($rows as $numeroFila => $fila) {
                try {
                    // Saltar filas vacías
                    if (empty(array_filter($fila))) {
                        continue;
                    }

                    $datosEmpleado = [
                        'EMPLEADO_NO' => trim($fila[$mapeoCampos['EMPLEADO_NO']] ?? ''),
                        'EMPLEADO_NOMBRE_COMPLETO' => trim($fila[$mapeoCampos['EMPLEADO_NOMBRE_COMPLETO']] ?? ''),
                        'EMPLEADO_CURP' => trim($fila[$mapeoCampos['EMPLEADO_CURP']] ?? ''),
                        'EMPLEADO_RFC' => trim($fila[$mapeoCampos['EMPLEADO_RFC']] ?? ''),
                        'EMPLEADO_CCT_NO' => trim($fila[$mapeoCampos['EMPLEADO_CCT_NO']] ?? ''),
                        'EMPLEADO_CCT_CLAVE' => trim($fila[$mapeoCampos['EMPLEADO_CCT_CLAVE']] ?? ''),
                        'EMPLEADO_CCT_NOMBRE' => trim($fila[$mapeoCampos['EMPLEADO_CCT_NOMBRE']] ?? ''),
                        'EMPLEADO_PUESTO' => trim($fila[$mapeoCampos['EMPLEADO_PUESTO']] ?? '')
                    ];

                    // Validar campos obligatorios
                    if (empty($datosEmpleado['EMPLEADO_NOMBRE_COMPLETO']) || 
                        empty($datosEmpleado['EMPLEADO_CURP']) || 
                        empty($datosEmpleado['EMPLEADO_RFC'])) {
                        $errores[] = "Fila " . ($numeroFila + 2) . ": Faltan campos obligatorios";
                        continue;
                    }

                    // Buscar empleado existente por los campos de identificación
                    $empleadoExistente = Empleado::where('EMPLEADO_NOMBRE_COMPLETO', $datosEmpleado['EMPLEADO_NOMBRE_COMPLETO'])
                        ->where('EMPLEADO_CURP', $datosEmpleado['EMPLEADO_CURP'])
                        ->where('EMPLEADO_RFC', $datosEmpleado['EMPLEADO_RFC'])
                        ->first();

                    if ($empleadoExistente) {
                        // Actualizar empleado existente
                        $empleadoExistente->update([
                            'EMPLEADO_NO' => $datosEmpleado['EMPLEADO_NO'],
                            'EMPLEADO_CCT_NO' => $datosEmpleado['EMPLEADO_CCT_NO'],
                            'EMPLEADO_CCT_CLAVE' => $datosEmpleado['EMPLEADO_CCT_CLAVE'],
                            'EMPLEADO_CCT_NOMBRE' => $datosEmpleado['EMPLEADO_CCT_NOMBRE'],
                            'EMPLEADO_PUESTO' => $datosEmpleado['EMPLEADO_PUESTO']
                        ]);
                        $empleadosActualizados++;
                    } else {
                        // Crear nuevo empleado
                        Empleado::create($datosEmpleado);
                        $empleadosCreados++;
                    }

                    $empleadosProcesados++;

                } catch (\Exception $e) {
                    $errores[] = "Fila " . ($numeroFila + 2) . ": " . $e->getMessage();
                    Log::error("Error procesando empleado en fila " . ($numeroFila + 2) . ": " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Archivo procesado exitosamente',
                'data' => [
                    'empleados_procesados' => $empleadosProcesados,
                    'empleados_creados' => $empleadosCreados,
                    'empleados_actualizados' => $empleadosActualizados,
                    'errores' => $errores
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error procesando archivo Excel: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function obtenerEmpleados(Request $request)
    {
        try {
            $empleados = Empleado::orderBy('EMPLEADO_NOMBRE_COMPLETO')->get();
            
            return response()->json([
                'success' => true,
                'data' => $empleados
            ]);
        } catch (\Exception $e) {
            Log::error("Error obteniendo empleados: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los empleados'
            ], 500);
        }
    }

    public function descargarPlantillaEmpleados()
    {
        try {
            // Crear un nuevo spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Configurar encabezados
            $encabezados = [
                'A1' => 'EMPLEADO_NO',
                'B1' => 'EMPLEADO_NOMBRE_COMPLETO',
                'C1' => 'EMPLEADO_CURP',
                'D1' => 'EMPLEADO_RFC',
                'E1' => 'EMPLEADO_CCT_NO',
                'F1' => 'EMPLEADO_CCT_CLAVE',
                'G1' => 'EMPLEADO_CCT_NOMBRE',
                'H1' => 'EMPLEADO_PUESTO'
            ];
            
            // Escribir encabezados
            foreach ($encabezados as $celda => $valor) {
                $sheet->setCellValue($celda, $valor);
            }
            
            // Agregar datos de ejemplo
            $ejemplos = [
                ['1756', 'Juan Pérez García', 'PERG800101HDFRRN01', 'PERG800101ABC', 'C04', 'CLAVE001', 'Centro de Trabajo 1', 'Profesor'],
                ['1757', 'María López Hernández', 'LOPH850315MDFRRN02', 'LOPH850315DEF', 'C05', 'CLAVE002', 'Centro de Trabajo 2', 'Directora'],
                ['1758', 'Carlos Rodríguez Silva', 'RODS900220HDFRRN03', 'RODS900220GHI', 'C06', 'CLAVE003', 'Centro de Trabajo 3', 'Coordinador']
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
            $sheet->getStyle('A1:H1')->getFont()->setBold(true);
            $sheet->getStyle('A1:H1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2E8F0');
            
            // Ajustar ancho de columnas
            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(18);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(10);
            $sheet->getColumnDimension('F')->setWidth(12);
            $sheet->getColumnDimension('G')->setWidth(20);
            $sheet->getColumnDimension('H')->setWidth(15);
            
            // Crear el archivo Excel
            $writer = new Xlsx($spreadsheet);
            
            // Configurar headers para descarga
            $filename = 'plantilla_empleados_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            return response()->streamDownload(function() use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error generando plantilla de empleados: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
}
