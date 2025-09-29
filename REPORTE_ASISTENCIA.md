# Sistema de Reporte de Asistencia

## Descripción
Sistema completo para generar reportes de asistencia de empleados con filtros por CCT y rango de fechas. El reporte muestra información detallada de horarios, checadas, retrasos y tiempo trabajado.

## Características

### 📊 Reporte Detallado
- **Información del empleado**: Nombre completo, puesto, número de empleado
- **Horarios programados**: Hora de entrada y salida según horario asignado
- **Checadas reales**: Primera y última checada del día
- **Cálculos automáticos**: Retrasos en minutos y tiempo trabajado en horas
- **Múltiples empleados**: Varios empleados en una sola hoja

### 🔍 Filtros Disponibles
- **CCT**: Filtro por clave o nombre del centro de trabajo
- **Rango de fechas**: Fecha de inicio y fin del reporte
- **Validación**: Fecha fin debe ser mayor o igual a fecha inicio

### 📄 Formatos de Exportación
- **Vista Web**: Interfaz responsive con Bootstrap
- **PDF**: Exportación directa a PDF con formato profesional
- **Excel**: Preparado para exportación (en desarrollo)

## Estructura de Datos

### Tabla Empleados
- `EMPLEADO_NO`: Número de empleado
- `EMPLEADO_NOMBRE_COMPLETO`: Nombre completo
- `EMPLEADO_PUESTO`: Puesto de trabajo
- `EMPLEADO_CCT_CLAVE`: Clave del CCT
- `EMPLEADO_CCT_NOMBRE`: Nombre del CCT

### Tabla Horarios
- `numero_empleado`: Número del empleado
- `dia`: Día de la semana (Lunes, Martes, etc.)
- `hora_entrada`: Hora programada de entrada
- `hora_salida`: Hora programada de salida

### Tabla Checadas
- `numero_empleado`: Número del empleado
- `fecha`: Fecha de la checada
- `hora`: Hora de la checada

## Uso del Sistema

### 1. Acceso Web
```
http://tu-dominio.com/reporte-asistencia
```

### 2. API Endpoints

#### Generar Reporte (JSON)
```http
POST /api/reportes/asistencia
Content-Type: application/x-www-form-urlencoded

fecha_inicio=2024-01-01
fecha_fin=2024-01-31
cct=25DPR1234X
```

#### Exportar PDF
```http
POST /api/reportes/asistencia/pdf
Content-Type: application/x-www-form-urlencoded

fecha_inicio=2024-01-01
fecha_fin=2024-01-31
cct=25DPR1234X
```

### 3. Ejemplo de Respuesta JSON
```json
{
  "success": true,
  "data": [
    {
      "numero_empleado": "12345",
      "nombre": "Juan Pérez García",
      "puesto": "Profesor",
      "cct": "Escuela Primaria Ejemplo",
      "asistencias": [
        {
          "fecha": "2024-01-15",
          "dia": "Lunes",
          "horario_entrada": "08:00:00",
          "horario_salida": "16:00:00",
          "primera_checada": "08:05:00",
          "ultima_checada": "15:55:00",
          "retraso_entrada": 5,
          "tiempo_trabajado": 7.83,
          "total_checadas": 2
        }
      ]
    }
  ],
  "filtros": {
    "fecha_inicio": "2024-01-01",
    "fecha_fin": "2024-01-31",
    "cct": "25DPR1234X"
  }
}
```

## Cálculos Automáticos

### Retraso de Entrada
- Se calcula la diferencia en minutos entre la hora programada y la primera checada
- Si la checada es antes del horario, el retraso es 0
- Se muestra en color rojo si hay retraso, verde si es puntual

### Tiempo Trabajado
- Diferencia en horas entre la primera y última checada
- Se redondea a 2 decimales
- Se muestra en formato decimal (ej: 7.83 horas)

## Requisitos del Sistema

### Dependencias PHP
- Laravel 9+
- Carbon (para manejo de fechas)
- DomPDF (para exportación PDF)

### Base de Datos
- MySQL/MariaDB
- Tablas: empleados, horarios, checadas

## Instalación

1. **Instalar dependencias**:
```bash
composer install
```

2. **Configurar base de datos**:
```bash
php artisan migrate
```

3. **Acceder al sistema**:
```
http://tu-dominio.com/reporte-asistencia
```

## Personalización

### Modificar Estilos
Los estilos se encuentran en `resources/views/reporte-asistencia.blade.php` en la sección `<style>`.

### Agregar Campos
Para agregar nuevos campos al reporte, modificar:
1. `ReporteAsistenciaController.php` - método `generarReporte()`
2. `reporte-asistencia.blade.php` - tabla HTML
3. `generarHTMLReporte()` - formato PDF

### Cambiar Cálculos
Los métodos de cálculo están en `ReporteAsistenciaController.php`:
- `calcularRetraso()`: Lógica de retrasos
- `calcularTiempoTrabajado()`: Lógica de tiempo trabajado

## Solución de Problemas

### Error: "No se encontraron empleados"
- Verificar que el CCT existe en la base de datos
- Comprobar que hay empleados asociados al CCT

### Error: "No hay checadas"
- Verificar que existen checadas en el rango de fechas
- Comprobar el formato de fecha en la base de datos

### PDF no se genera
- Verificar que DomPDF está instalado
- Comprobar permisos de escritura en storage/

## Soporte
Para soporte técnico o mejoras, contactar al equipo de desarrollo.
