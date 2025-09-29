# Ejemplo del Reporte de Asistencia

## Formato de las Columnas

### Hora Entrada
Ahora muestra el formato: **"07:00 - 07:11"**
- **07:00** = Hora programada en el horario del empleado
- **07:11** = Hora real de la primera checada

### Hora Salida  
Ahora muestra el formato: **"16:00 - 15:55"**
- **16:00** = Hora programada de salida en el horario
- **15:55** = Hora real de la última checada

## Casos de Uso

### ✅ Caso Normal
```
Hora Entrada: 07:00 - 07:11
Hora Salida:  16:00 - 15:55
```

### ⚠️ Sin Checada de Entrada
```
Hora Entrada: 07:00 - Sin checada
Hora Salida:  16:00 - 15:55
```

### ⚠️ Sin Horario Programado
```
Hora Entrada: Sin horario - 08:15
Hora Salida:  Sin horario - 17:30
```

### ❌ Sin Datos
```
Hora Entrada: Sin datos
Hora Salida:  Sin datos
```

## Estructura de la Tabla

| Fecha | Día | Hora Entrada | Hora Salida | Retraso | Tiempo Trabajado | Total Checadas |
|-------|-----|--------------|-------------|---------|------------------|----------------|
| 15/01/2024 | Lunes | 07:00 - 07:11 | 16:00 - 15:55 | 11 | 8.73 | 2 |
| 16/01/2024 | Martes | 07:00 - Sin checada | 16:00 - 15:45 | - | - | 0 |

## Cómo Funciona

1. **Busca el horario** del empleado por número y día de la semana
2. **Obtiene las checadas** del empleado para esa fecha
3. **Combina la información** en el formato solicitado
4. **Calcula retrasos** comparando horario programado vs primera checada
5. **Calcula tiempo trabajado** entre primera y última checada

## Ventajas del Nuevo Formato

- ✅ **Más claro**: Ves horario programado vs real en una sola celda
- ✅ **Fácil comparación**: Puedes ver rápidamente si llegó tarde o temprano
- ✅ **Menos columnas**: La tabla es más compacta
- ✅ **Información completa**: Tienes toda la información necesaria
