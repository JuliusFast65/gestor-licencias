# API de Hibernación de Sesiones ERP

## Descripción
Este endpoint marca las sesiones ERP como "hibernadas" cuando el sistema se va a hibernar, liberando temporalmente las licencias. El despertar se maneja automáticamente en `Ping_Sesion.php` cuando se detecta actividad.

## Endpoint
```
POST /apis/Hibernar_Sesion.php
```

## Parámetros Requeridos

### Para Hibernar:
```json
{
    "RUC": "0992671661001",
    "ping_token": "token_de_la_sesion_activa"
}
```

## Campos del JSON

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `RUC` | string | Sí | RUC de la empresa |
| `ping_token` | string | Sí | Token de la sesión a hibernar |
| `Serie` | string | No | Número de serie de la máquina (opcional) |

## Respuestas

### Hibernación Exitosa:
```json
{
    "Fin": "OK",
    "Mensaje": "Sesión hibernada exitosamente.",
    "ping_token": "token_de_la_sesion",
    "estado": "H",
    "sesion": {
        "tipo": "LSOFT_BA",
        "usuario": "LSOFT_BA",
        "fecha_inicio": "2024-01-15 10:30:00"
    }
}
```

### Error:
```json
{
    "Fin": "Error",
    "Mensaje": "Descripción del error"
}
```

## Códigos de Estado HTTP

- **200**: Operación exitosa
- **400**: Datos incompletos o acción inválida
- **404**: Sesión no encontrada
- **500**: Error interno del servidor

## Casos de Uso

### 1. Hibernación del Sistema
Cuando el ERP detecta que el sistema se va a hibernar:
```bash
curl -X POST http://localhost/apis/Hibernar_Sesion.php \
  -H "Content-Type: application/json" \
  -H "X-Timestamp: $(date +%s)" \
  -H "X-Signature: $(echo -n "$timestamp$RUC" | openssl dgst -sha256 -hmac "$SECRET_KEY" -binary | base64)" \
  -d '{
    "RUC": "0992671661001",
    "ping_token": "abc123...",
    "accion": "hibernar"
  }'
```



## Seguridad

- Requiere validación de firma HMAC (`Validar_Firma.php`)
- Solo permite modificar sesiones propias (mismo RUC)
- Registra todas las operaciones en logs de debug

## Base de Datos

### Campos Agregados a `sesiones_erp`:
- `estado`: CHAR(1) - 'A' (activa), 'H' (hibernada), 'C' (cerrada)
- `fecha_hibernacion`: DATETIME (cuándo se hibernó)
- `fecha_despertar`: DATETIME (cuándo se despertó)

### Script SQL:
Ejecutar `agregar_campos_hibernacion.sql` para agregar los campos necesarios.

## Integración con el ERP

### Eventos del Sistema:
1. **OnSuspend**: Llamar a hibernar antes de hibernar
2. **OnResume**: No es necesario - Ping_Sesion.php reactiva automáticamente
3. **OnShutdown**: Opcionalmente hibernar antes de cerrar

### Ejemplo en C#:
```csharp
protected override void OnSuspend()
{
    // Hibernar sesión antes de suspender
    HibernarSesionERP();
    base.OnSuspend();
}

// No es necesario implementar OnResume
// Ping_Sesion.php reactiva automáticamente la sesión
```

## Ventajas

1. **Liberación de Licencias**: Las licencias hibernadas no cuentan para el límite
2. **Trazabilidad**: Registro completo de cuándo se hibernó/despertó
3. **Flexibilidad**: Permite hibernar/despertar múltiples veces
4. **Seguridad**: Mantiene la validación de firma HMAC
5. **Compatibilidad**: No afecta el funcionamiento existente

