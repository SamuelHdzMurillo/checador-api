<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\ChecadaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rutas para empleados
Route::post('/empleados/procesar-excel', [EmpleadoController::class, 'procesarExcel']);
Route::get('/empleados', [EmpleadoController::class, 'obtenerEmpleados']);
Route::get('/empleados/plantilla', [EmpleadoController::class, 'descargarPlantillaEmpleados']);

// Rutas para horarios
Route::post('/horarios/procesar-excel', [HorarioController::class, 'procesarExcelHorarios']);
Route::get('/horarios', [HorarioController::class, 'obtenerHorarios']);
Route::get('/horarios/empleado/{numeroEmpleado}', [HorarioController::class, 'obtenerHorariosPorEmpleado']);
Route::get('/horarios/empleado/{numeroEmpleado}/dia/{dia}', [HorarioController::class, 'obtenerHorariosPorEmpleadoYDia']);
Route::get('/horarios/plantilla', [HorarioController::class, 'descargarPlantillaHorarios']);

// Rutas para checadas
Route::post('/checadas/registrar', [ChecadaController::class, 'registrarChecada']);
Route::post('/checadas/procesar', [ChecadaController::class, 'procesarChecadas']);
Route::post('/checadas/procesar-archivo', [ChecadaController::class, 'procesarArchivoChecadas']);
Route::get('/checadas', [ChecadaController::class, 'obtenerChecadas']);
Route::get('/checadas/empleado/{numeroEmpleado}', [ChecadaController::class, 'obtenerChecadasPorEmpleado']);
