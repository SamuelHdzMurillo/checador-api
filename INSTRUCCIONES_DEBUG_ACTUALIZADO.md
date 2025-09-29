# Instrucciones para Debugging del Reporte de Asistencia (ACTUALIZADO)

## 🔍 Verificar Horarios Disponibles

### 1. Verificar que existen horarios en la base de datos
```bash
GET http://localhost/incidencias/api/reportes/verificar-horarios?cct=TU_CCT_AQUI
```

Esto te mostrará todos los horarios disponibles para los empleados del CCT especificado.

### 2. Verificar la estructura de la tabla horarios
```sql
SELECT * FROM horarios LIMIT 5;
```

### 3. Verificar que los empleados tienen horarios
```sql
SELECT 
    e.EMPLEADO_NOMBRE_COMPLETO,
    e.EMPLEADO_NO,
    h.dia,
    h.hora_entrada,
    h.hora_salida
FROM empleados e
LEFT JOIN horarios h ON e.EMPLEADO_NO = h.numero_empleado
WHERE e.EMPLEADO_CCT_CLAVE = 'TU_CCT_AQUI'
ORDER BY e.EMPLEADO_NO, h.dia;
```

## 🐛 Pasos de Debugging

### Paso 1: Verificar Datos
1. **Accede a**: `http://localhost/incidencias/api/reportes/verificar-horarios?cct=TU_CCT`
2. **Revisa** que aparezcan horarios para los empleados
3. **Verifica** que los días estén como números (1, 2, 3, etc.)

### Paso 2: Revisar Logs
1. **Genera un reporte** con fechas y CCT
2. **Revisa el archivo** `storage/logs/laravel.log`
3. **Busca** los mensajes que empiezan con "Buscando horario para empleado"

### Paso 3: Verificar Formato de Días
Los días deben estar guardados en la base de datos como NÚMEROS:
- 1 = Lunes
- 2 = Martes  
- 3 = Miércoles
- 4 = Jueves
- 5 = Viernes
- 6 = Sábado
- 7 = Domingo

## 🔧 Posibles Problemas y Soluciones

### Problema 1: No se encuentran horarios
**Causa**: Los días en la base de datos no están como números
**Solución**: 
```sql
-- Si tienes días como texto, convertir a números:
UPDATE horarios SET dia = 1 WHERE dia = 'Lunes';
UPDATE horarios SET dia = 2 WHERE dia = 'Martes';
UPDATE horarios SET dia = 3 WHERE dia = 'Miércoles';
UPDATE horarios SET dia = 4 WHERE dia = 'Jueves';
UPDATE horarios SET dia = 5 WHERE dia = 'Viernes';
UPDATE horarios SET dia = 6 WHERE dia = 'Sábado';
UPDATE horarios SET dia = 7 WHERE dia = 'Domingo';
```

### Problema 2: Número de empleado no coincide
**Causa**: El campo `numero_empleado` en horarios no coincide con `EMPLEADO_NO` en empleados
**Solución**: Verificar que los datos estén sincronizados

### Problema 3: CCT no encuentra empleados
**Causa**: El CCT no existe o está mal escrito
**Solución**: Verificar el CCT exacto en la base de datos

## 📊 Ejemplo de Respuesta Correcta

```json
{
  "success": true,
  "data": [
    {
      "empleado": "Juan Pérez García",
      "numero_empleado": "12345",
      "horarios": [
        {
          "dia_numero": 1,
          "dia_nombre": "Lunes",
          "hora_entrada": "07:00:00",
          "hora_salida": "16:00:00"
        },
        {
          "dia_numero": 2,
          "dia_nombre": "Martes", 
          "hora_entrada": "07:00:00",
          "hora_salida": "16:00:00"
        }
      ]
    }
  ]
}
```

## 🚀 Prueba Completa

1. **Verificar horarios**: `GET /api/reportes/verificar-horarios?cct=TU_CCT`
2. **Generar reporte**: `POST /api/reportes/asistencia` con fechas y CCT
3. **Revisar logs**: `storage/logs/laravel.log`
4. **Verificar resultado**: El reporte debe mostrar "07:00 - 07:11" en lugar de "Sin datos"

## 📝 Notas Importantes

- Los logs se guardan en `storage/logs/laravel.log`
- El formato de días debe ser NÚMEROS: 1, 2, 3, 4, 5, 6, 7
- El número de empleado debe coincidir entre las tablas
- El CCT debe existir en la base de datos

## 🔄 Mapeo de Días

| Carbon dayOfWeek | Número en BD | Nombre |
|------------------|---------------|--------|
| 0 (Domingo)      | 7             | Domingo |
| 1 (Lunes)        | 1             | Lunes |
| 2 (Martes)        | 2             | Martes |
| 3 (Miércoles)     | 3             | Miércoles |
| 4 (Jueves)        | 4             | Jueves |
| 5 (Viernes)       | 5             | Viernes |
| 6 (Sábado)        | 6             | Sábado |
