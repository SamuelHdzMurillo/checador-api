<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .reporte-container {
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            margin: 8px 0;
            overflow: hidden;
        }
        .filtros-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .tabla-reporte {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin: 0;
        }
        .tabla-reporte th {
            background-color: #28a745;
            color: white;
            font-size: 8px;
            padding: 4px 3px;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
            border: 1px solid #28a745;
        }
        .tabla-reporte td {
            padding: 3px 3px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #dee2e6;
            background-color: white;
            font-size: 7px;
        }
        .empleado-header {
            background-color: #e9ecef !important;
            font-weight: bold;
            color: #495057;
            text-align: left;
            padding: 8px 12px;
        }
        .retraso {
            color: #dc3545;
            font-weight: bold;
        }
        .puntual {
            color: #28a745;
            font-weight: bold;
        }
        .sin-datos {
            color: #6c757d;
            font-style: italic;
        }
        .estatus-falta {
            color: #dc3545;
            font-weight: bold;
        }
        .estatus-normal {
            color: #28a745;
            font-weight: bold;
        }
        .estatus-retraso {
            color: #ffc107;
            font-weight: bold;
        }
        .loading {
            display: none;
        }
        .btn-export {
            margin-left: 10px;
        }
        .resumen-empleado {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 10px;
            margin-bottom: 15px;
        }
        .header-empleado {
            background-color: #f8f9fa;
            padding: 4px 6px;
            font-weight: bold;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            font-size: 9px;
        }
        .time-cell {
            font-family: 'Courier New', monospace;
            font-size: 7px;
        }
        .container-fluid {
            max-width: 100%;
            padding: 0;
        }
        .main-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #28a745;
            padding-bottom: 15px;
        }
        .logo-section {
            display: flex;
            align-items: center;
        }
        .logo-cecyte {
            background-color: #28a745;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
            margin-right: 10px;
        }
        .logo-text {
            color: #28a745;
            font-size: 12px;
            font-weight: bold;
        }
        .departamento {
            color: #28a745;
            font-size: 11px;
            text-align: right;
        }
        .reporte-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: black;
            margin: 20px 0;
        }
        .summary-row { 
            background-color: black; 
            color: white; 
            font-weight: bold; 
        }
        .summary-total { 
            background-color: white; 
            color: black; 
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-chart-line"></i> Reporte de Asistencia</h2>
                
                <!-- Filtros -->
                <div class="filtros-section">
                    <h5><i class="fas fa-filter"></i> Filtros</h5>
                    <form id="filtrosForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                            </div>
                            <div class="col-md-3">
                                <label for="fecha_fin" class="form-label">Fecha Fin</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" required>
                            </div>
                            <div class="col-md-4">
                                <label for="cct" class="form-label">CCT</label>
                                <input type="text" class="form-control" id="cct" name="cct" placeholder="Ingrese CCT o nombre del centro" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Generar Reporte
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Loading -->
                <div class="loading text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p>Generando reporte...</p>
                </div>

                <!-- Botones de exportación -->
                <div class="mb-3" id="botonesExport" style="display: none;">
                    <button class="btn btn-success" id="exportarPDF">
                        <i class="fas fa-file-pdf"></i> Exportar PDF
                    </button>
                    <button class="btn btn-info" id="exportarExcel">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>

                <!-- Resultados -->
                <div id="resultados"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('filtrosForm').addEventListener('submit', function(e) {
            e.preventDefault();
            generarReporte();
        });

        function generarReporte() {
            const formData = new FormData(document.getElementById('filtrosForm'));
            const loading = document.querySelector('.loading');
            const resultados = document.getElementById('resultados');
            const botonesExport = document.getElementById('botonesExport');

            loading.style.display = 'block';
            resultados.innerHTML = '';
            botonesExport.style.display = 'none';

            fetch('/api/reportes/asistencia', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                if (data.success) {
                    mostrarReporte(data.data, data.filtros);
                    botonesExport.style.display = 'block';
                } else {
                    mostrarError('Error al generar el reporte');
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                mostrarError('Error de conexión: ' + error.message);
            });
        }

        function mostrarReporte(reporte, filtros) {
            let html = `
                <div class="reporte-container">
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
                        REPORTE DETALLADO DE REGISTRO ENTRADA Y SALIDA EN EL PERIODO: ${filtros.fecha_inicio} - ${filtros.fecha_fin}
                    </div>
            `;

            reporte.forEach((empleado, index) => {
                // Agregar salto de página cada 3 empleados
                if (index > 0 && index % 3 === 0) {
                    html += '<div style="page-break-before: always;"></div>';
                }
                
                html += `
                    <div class="reporte-container" style="margin-bottom: 8px; page-break-inside: avoid;">
                        <div class="header-empleado">
                            ${empleado.numero_empleado} ${empleado.nombre} ${empleado.puesto}
                        </div>
                        
                        <table class="tabla-reporte" style="margin-bottom: 3px;">
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
                `;

                empleado.asistencias.forEach(asistencia => {
                    const estatusEntradaClass = asistencia.estatus_entrada === 'Falta Entrada' ? 'estatus-falta' : 
                                               asistencia.estatus_entrada === 'Puntual' ? 'estatus-normal' : 'sin-datos';
                    const estatusSalidaClass = asistencia.estatus_salida === 'Falta' ? 'estatus-falta' : 
                                              asistencia.estatus_salida === 'Normal' ? 'estatus-normal' : 'sin-datos';
                    
                    html += `
                        <tr>
                            <td>${asistencia.fecha}</td>
                            <td>${asistencia.dia.toLowerCase()}</td>
                            <td class="time-cell">${asistencia.hora_entrada || '00:00 - 00:00'}</td>
                            <td class="${estatusEntradaClass}">${asistencia.estatus_entrada || 'Falta'}</td>
                            <td class="time-cell">${asistencia.hora_salida || '00:00 - 00:00'}</td>
                            <td class="${estatusSalidaClass}">${asistencia.estatus_salida || 'Falta'}</td>
                            <td>${asistencia.tiempo_trabajado || '00:00'}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                        
                        <table class="tabla-reporte" style="margin-bottom: 5px;">
                            <tr class="summary-row">
                                <td class="summary-total">Total horas: ${calcularTotalHoras(empleado.asistencias)}</td>
                                <td>Horas trabajadas: ${calcularHorasTrabajadas(empleado.asistencias)}</td>
                                <td>Faltas días: ${calcularFaltasDias(empleado.asistencias)}</td>
                                <td>Entrada faltante: ${calcularEntradaFaltante(empleado.asistencias)}</td>
                                <td>Salida faltante: ${calcularSalidaFaltante(empleado.asistencias)}</td>
                                <td></td>
                                <td></td>
                            </tr>
                        </table>
                    </div>
                `;
            });

            html += '</div>';
            document.getElementById('resultados').innerHTML = html;
        }

        function mostrarError(mensaje) {
            document.getElementById('resultados').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> ${mensaje}
                </div>
            `;
        }

        function formatearFecha(fecha) {
            return new Date(fecha).toLocaleDateString('es-ES');
        }

        // Funciones para calcular totales
        function calcularTotalHoras(asistencias) {
            let totalMinutos = 0;
            asistencias.forEach(asistencia => {
                if (asistencia.tiempo_trabajado && asistencia.tiempo_trabajado !== '00:00') {
                    const tiempo = asistencia.tiempo_trabajado.split(':');
                    if (tiempo.length >= 2) {
                        const horas = parseInt(tiempo[0]);
                        const minutos = parseInt(tiempo[1]);
                        totalMinutos += (horas * 60) + minutos;
                    }
                }
            });
            const horas = Math.floor(totalMinutos / 60);
            const minutos = totalMinutos % 60;
            return `${horas}:${minutos.toString().padStart(2, '0')}`;
        }

        function calcularHorasTrabajadas(asistencias) {
            return asistencias.filter(a => a.tiempo_trabajado && a.tiempo_trabajado !== '00:00').length;
        }

        function calcularFaltasDias(asistencias) {
            return asistencias.filter(a => a.estatus_entrada === 'Falta Entrada').length;
        }

        function calcularEntradaFaltante(asistencias) {
            return asistencias.filter(a => a.estatus_entrada === 'Falta Entrada').length;
        }

        function calcularSalidaFaltante(asistencias) {
            return asistencias.filter(a => a.estatus_salida === 'Falta').length;
        }

        // Exportar PDF
        document.getElementById('exportarPDF').addEventListener('click', function() {
            const formData = new FormData(document.getElementById('filtrosForm'));
            
            fetch('/api/reportes/asistencia/pdf', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'reporte_asistencia.pdf';
                a.click();
                window.URL.revokeObjectURL(url);
            })
            .catch(error => {
                alert('Error al exportar PDF: ' + error.message);
            });
        });

        // Exportar Excel (funcionalidad básica)
        document.getElementById('exportarExcel').addEventListener('click', function() {
            alert('Funcionalidad de Excel en desarrollo');
        });

        // Establecer fechas por defecto (último mes)
        const hoy = new Date();
        const haceUnMes = new Date();
        haceUnMes.setMonth(haceUnMes.getMonth() - 1);
        
        document.getElementById('fecha_inicio').value = haceUnMes.toISOString().split('T')[0];
        document.getElementById('fecha_fin').value = hoy.toISOString().split('T')[0];
    </script>
</body>
</html>
