# 🎉 Refactor Completo del Gestor de Licencias

## 📋 Resumen del Proyecto

El **Gestor de Licencias** es una aplicación PHP para administrar licencias de ERPs (FSoft, LSoft, LSoft Web) con dos tipos de licenciamiento:
- **Licenciamiento por máquina**: Basado en hardware
- **Licenciamiento por sesión**: Basado en sesiones de usuario

## 🔄 Estado del Refactor

### ✅ **COMPLETADO** - Arquitectura Modular

El proyecto ha sido completamente refactorizado desde un archivo monolítico (`Consultar.php`) a una arquitectura modular y mantenible.

## 📁 Nueva Estructura de Archivos

```
gestor-licencias/
├── 📄 Consultar.php              # Archivo principal (refactorizado)
├── 📄 index.php                  # Menú principal del sistema
├── 📄 reporte_licencias.php      # Reporte de licencias
├── 📄 reporte_empresas.php       # Reporte de empresas
├── 📄 reporte_errores.php        # Reporte de errores del ERP
├── 📁 php/                       # Módulos separados
│   ├── 🔐 auth.php              # Autenticación y sesiones
│   ├── 🔒 permissions.php       # Sistema de permisos (RBAC)
│   ├── 🏢 companies.php         # Gestión de empresas
│   ├── 👤 profiles.php          # Gestión de perfiles de usuario
│   └── 🔑 licenses.php          # Gestión de licencias y sesiones
├── 📁 apis/                      # APIs y endpoints
│   ├── 🔌 Conectar_BD.php       # Conexión a base de datos
│   ├── 🔌 Conectar_BD_Produccion.php # Conexión a BD de producción
│   ├── 🔌 Debug_Config.php      # Configuración de debug
│   ├── 🔌 Validar_Firma.php     # Validación de firmas HMAC
│   ├── 🔌 Obt_IP_Real.php       # Obtención de IP real
│   ├── 🔌 ObtToken.php          # Generación de tokens
│   ├── 🔌 Validar_Licencia.php  # Validación de licencias
│   ├── 🔌 Registrar_Sesion.php  # Registro de sesiones activas
│   ├── 🔌 Ping_Sesion.php       # Ping de sesiones
│   ├── 🔌 Logout_Sesion.php     # Cierre de sesiones
│   ├── 🔌 ObtNombreUltAct.php   # Obtener nombre y última actividad
│   ├── 🔌 Limpiar_Sesiones_Inactivas.php # Limpieza de sesiones
│   └── 🔌 Grabar_Error.php      # Registro de errores del ERP
└── 📄 test_refactor.php         # Archivo de prueba del refactor
```

## 🔌 Endpoints de APIs (Relacionados con ERPs)

### 📋 **Nota Importante**
Los archivos en la carpeta `apis/` son **endpoints utilizados por los ERPs** (FSoft, LSoft, LSoft Web) para comunicarse con el sistema de licenciamiento. Aunque no pertenecen directamente al proyecto web del Gestor de Licencias, están incluidos por su relación funcional.

### 🔌 **Endpoints Principales**

#### 1. **Validar_Licencia.php** - Validación de Licencias
- **Propósito:** Endpoint principal para validar licencias desde los ERPs
- **Método:** POST con firma HMAC
- **Parámetros:** RUC, Serie, Sistema, etc.
- **Respuesta:** JSON con estado de validación

#### 2. **Registrar_Sesion.php** - Gestión de Sesiones
- **Propósito:** Registrar sesiones activas de usuarios en ERPs
- **Método:** POST con autenticación
- **Parámetros:** RUC, Usuario, Sistema, etc.
- **Respuesta:** JSON con confirmación de registro

#### 3. **Grabar_Error.php** - Registro de Errores
- **Propósito:** Registrar errores desde los ERPs para monitoreo
- **Método:** POST con firma HMAC
- **Parámetros:** RUC, Empresa, Usuario, Error, Fuente, etc.
- **Respuesta:** JSON con confirmación de registro

#### 4. **Ping_Sesion.php** - Mantenimiento de Sesiones
- **Propósito:** Mantener sesiones activas (heartbeat)
- **Método:** POST
- **Parámetros:** Token de sesión
- **Respuesta:** JSON con estado de sesión

#### 5. **ObtToken.php** - Generación de Tokens
- **Propósito:** Generar tokens de autenticación para ERPs
- **Método:** POST
- **Parámetros:** Credenciales de ERP
- **Respuesta:** JSON con token de acceso

### 🔒 **Seguridad y Autenticación**

#### **Validar_Firma.php** - Validación HMAC
- **Propósito:** Validar firmas HMAC en peticiones de ERPs
- **Función:** `validarPeticion()` - Valida y decodifica JSON firmado
- **Seguridad:** Previene peticiones no autorizadas

#### **Debug_Config.php** - Configuración de Debug
- **Propósito:** Configurar logs de depuración para endpoints
- **Función:** `log_debug()` - Registra eventos de debug
- **Uso:** Solo en desarrollo, deshabilitado en producción

### 🔧 **Utilidades de Conexión**

#### **Conectar_BD.php** - Conexión Principal
- **Propósito:** Conexión a base de datos para el sistema web
- **Configuración:** Desarrollo local (XAMPP)

#### **Conectar_BD_Produccion.php** - Conexión de Producción
- **Propósito:** Conexión a base de datos de producción para ERPs
- **Configuración:** Servidor remoto de producción

## 🏗️ Módulos Creados

### 1. 🔐 **auth.php** - Autenticación
- **Funciones principales:**
  - `renderizarFormularioLogin()` - Formulario de login
  - Procesamiento de login/logout
  - Verificación de sesiones activas
  - Protección contra inclusiones múltiples

### 2. 🔒 **permissions.php** - Sistema de Permisos (RBAC)
- **Funciones principales:**
  - `usuarioPuedeCrearEmpresa()`
  - `usuarioPuedeEditarEmpresa($ruc)`
  - `usuarioPuedeEliminarEmpresa()`
  - `usuarioPuedeDarDeBajaLicencia($ruc)`
  - `verificarPermisoYSalir($tiene_permiso)`

### 3. 🏢 **companies.php** - Gestión de Empresas
- **Funciones principales:**
  - `obtenerRegistroEmpresa($conn, $ruc)`
  - `renderizarWidgetBusquedaYComando()`
  - `renderizarScriptBusqueda()`
  - `renderizarFormularioEmpresa($conn, $ruc)`
  - `procesarFormularioEmpresa($conn, $ruc)`
  - `procesarEliminarEmpresa($conn, $ruc)`

### 4. 👤 **profiles.php** - Gestión de Perfiles
- **Funciones principales:**
  - `renderizarDashboard($conn)`
  - `renderizarPaginaPerfil($conn)`
  - `procesarEdicionPerfil($conn)`
  - `obtenerMapeoDeModulos()`

### 5. 🔑 **licenses.php** - Gestión de Licencias
- **Funciones principales:**
  - `obtenerSesionesActivasPorRuc($conn, $ruc)`
  - `obtenerTodasLasLicenciasPorRuc($conn, $ruc)`
  - `renderizarPaginaAlta($conn, $ruc)`
  - `renderizarPaginaActivar($conn, $ruc, $serie)`
  - `procesarBajaLicencia($conn, $ruc, $pkLicencia)`

## 📊 Reportes del Sistema

### 1. **reporte_licencias.php** - Reporte de Licencias
- **Funcionalidades:**
  - Filtros por empresa, sistema y estado
  - Contadores de licencias por sistema
  - Exportación a Excel
  - Filtros en cabeceras y ordenamiento

### 2. **reporte_empresas.php** - Reporte de Empresas
- **Funcionalidades:**
  - Filtros por nombre y estado
  - Contadores de empresas
  - Exportación a Excel
  - Filtros en cabeceras y ordenamiento

### 3. **reporte_errores.php** - Reporte de Errores ERP
- **Funcionalidades:**
  - Filtros rápidos de fecha (Hoy, Esta semana, Este mes, etc.)
  - Columnas: Fecha, RUC, Empresa, Usuario, Versión, Fuente, Línea, Número, Error, Programa
  - Filtros en cabeceras y ordenamiento
  - Estructura basada en tabla real `Errores`

## 🎯 Beneficios del Refactor

### ✅ **Mantenibilidad**
- Código organizado por responsabilidades
- Fácil localización de funciones
- Reducción de duplicación de código

### ✅ **Escalabilidad**
- Nuevas funcionalidades se pueden agregar en módulos separados
- Fácil extensión del sistema de permisos
- Arquitectura preparada para crecimiento

### ✅ **Legibilidad**
- Código más limpio y organizado
- Comentarios descriptivos en cada módulo
- Separación clara de responsabilidades

### ✅ **Reutilización**
- Funciones modulares que se pueden reutilizar
- Sistema de permisos centralizado
- Componentes independientes

### ✅ **Integración con ERPs**
- Endpoints bien definidos para comunicación con ERPs
- Sistema de autenticación robusto con HMAC
- Registro y monitoreo de errores centralizado

## 🧪 Cómo Probar el Refactor

### 1. **Verificar Inclusiones**
```bash
# Abrir en el navegador:
http://localhost/test_refactor.php
```

### 2. **Probar Aplicación Principal**
```bash
# Abrir en el navegador:
http://localhost/Consultar.php
```

### 3. **Probar Reportes**
```bash
# Reporte de Licencias:
http://localhost/reporte_licencias.php

# Reporte de Empresas:
http://localhost/reporte_empresas.php

# Reporte de Errores:
http://localhost/reporte_errores.php
```

### 4. **Funcionalidades a Probar**
- ✅ Login con credenciales existentes
- ✅ Dashboard principal
- ✅ Gestión de empresas (buscar, crear, editar, eliminar)
- ✅ Edición de perfil de usuario
- ✅ Gestión de licencias (alta, activación, baja)
- ✅ Sistema de permisos por perfil
- ✅ Reportes con filtros y exportación
- ✅ Endpoints de APIs para ERPs

## 🔧 Configuración Requerida

### Servidor Web
- **XAMPP** (Apache + MySQL + PHP)
- Puerto: 80 (por defecto)
- DocumentRoot: `C:/xampp/htdocs`

### Base de Datos
- **MySQL** con las tablas del sistema
- Conexión configurada en `apis/Conectar_BD.php`
- Conexión de producción en `apis/Conectar_BD_Produccion.php`

### PHP
- **Versión:** 7.4 o superior
- **Extensiones requeridas:** mysqli, session, json

## 📊 Estadísticas del Refactor

- **Archivos originales:** 1 archivo monolítico
- **Archivos después del refactor:** 15+ archivos modulares
- **Líneas de código en Consultar.php:** Reducidas de ~1000+ a ~400
- **Funciones extraídas:** 50+ funciones organizadas por módulo
- **Endpoints de APIs:** 12+ endpoints para comunicación con ERPs
- **Reportes:** 3 reportes completos con filtros y exportación
- **Tiempo de refactor:** Completado en sesiones incrementales

## 🚀 Próximos Pasos Sugeridos

### Opcionales (No críticos)
1. **📁 php/utils.php** - Funciones utilitarias comunes
2. **📁 php/admin.php** - Funciones específicas de administración
3. **📁 php/debug.php** - Funciones de depuración
4. **📁 css/** - Estilos CSS separados
5. **📁 js/** - JavaScript separado

### Mejoras Futuras
1. **API REST** - Separar lógica de negocio de presentación
2. **Base de datos** - Migrar a PDO o un ORM
3. **Frontend** - Framework JavaScript moderno
4. **Seguridad** - Implementar CSRF tokens, rate limiting
5. **Monitoreo** - Dashboard de monitoreo de ERPs en tiempo real

## 🎉 Conclusión

El refactor ha sido **completado exitosamente**. El proyecto ahora tiene:

- ✅ **Arquitectura modular** y mantenible
- ✅ **Separación de responsabilidades** clara
- ✅ **Código reutilizable** y escalable
- ✅ **Sistema de permisos** robusto
- ✅ **Fácil navegación** y comprensión del código
- ✅ **Endpoints de APIs** para integración con ERPs
- ✅ **Reportes completos** con filtros y exportación
- ✅ **Sistema de monitoreo** de errores centralizado

**¡El Gestor de Licencias está listo para desarrollo futuro y mantenimiento!** 🚀

---

*Refactor completado el: $(Get-Date)*
*Estado: ✅ COMPLETADO* 