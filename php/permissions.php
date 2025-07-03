<?php
// permissions.php - Sistema de permisos basado en roles (RBAC) para el Gestor de Licencias
// Incluye: verificación de permisos por perfil de usuario, constantes de configuración y funciones de validación.
//
// Este archivo debe ser incluido después de auth.php para que las sesiones estén disponibles.

// Protección contra inclusiones múltiples
if (defined('PERMISSIONS_INCLUDED')) {
    return;
}
define('PERMISSIONS_INCLUDED', true);

// ===================================================================
// RBAC: SISTEMA DE PERMISOS BASADO EN ROLES
// ===================================================================

// Define una lista de RUCs permitidos para el perfil de desarrollo
define('RUCS_PRUEBA_DESARROLLO', [
    '1234567890123', // RUC de prueba original
    '0992247525001', // Nuevo RUC de prueba
    '0910155720001'  // Otro RUC de prueba
]);

/**
 * Verifica si el usuario actual tiene permiso para crear una empresa.
 * @return bool True si tiene permiso, false si no.
 */
function usuarioPuedeCrearEmpresa(): bool {
    // Normalizamos el perfil a minúsculas para una comparación robusta.
    $perfil = strtolower($_SESSION['perfil'] ?? '');
    return in_array($perfil, ['administrador', 'presidente']);
}

/**
 * Verifica si el usuario actual tiene permiso para editar datos de una empresa.
 * @param string $ruc El RUC de la empresa que se intenta editar.
 * @return bool True si tiene permiso, false si no.
 */
function usuarioPuedeEditarEmpresa(string $ruc): bool {
    // Normalizamos el perfil a minúsculas.
    $perfil = strtolower($_SESSION['perfil'] ?? '');

    if (in_array($perfil, ['administrador', 'presidente'])) {
        return true;
    }
    if ($perfil === 'desarrollo' && in_array($ruc, RUCS_PRUEBA_DESARROLLO)) {
        return true;
    }
    return false;
}

/**
 * Verifica si el usuario actual tiene permiso para eliminar una empresa.
 * @return bool True si tiene permiso, false si no.
 */
function usuarioPuedeEliminarEmpresa(): bool {
    // Normalizamos el perfil a minúsculas para una comparación robusta.
    $perfil = strtolower($_SESSION['perfil'] ?? '');
    return in_array($perfil, ['administrador', 'presidente']);
}

/**
 * Verifica si el usuario actual tiene permiso para dar de baja una licencia.
 * @param string $ruc El RUC de la empresa afectada.
 * @return bool True si tiene permiso, false si no.
 */
function usuarioPuedeDarDeBajaLicencia(string $ruc): bool {
    // Normalizamos el perfil a minúsculas.
    $perfil = strtolower($_SESSION['perfil'] ?? '');

    if (in_array($perfil, ['administrador', 'presidente'])) {
        return true;
    }
    if ($perfil === 'desarrollo' && in_array($ruc, RUCS_PRUEBA_DESARROLLO)) {
        return true;
    }
    return false;
}

/**
 * Función central para verificar permisos y detener el script si no se cumplen.
 * @param bool $tienePermiso El resultado de una de las funciones de verificación.
 * @param string $mensaje El mensaje a mostrar si no hay permiso.
 */
function verificarPermisoYSalir(bool $tienePermiso, string $mensaje = 'No tiene permiso para realizar esta acción.'): void {
    if (!$tienePermiso) {
        http_response_code(403); // Forbidden
        die("<div style='font-family: sans-serif; text-align: center; padding: 50px;'>
                <h1 style='color: #d9534f;'>Acceso Denegado</h1>
                <p style='font-size: 1.2em;'>{$mensaje}</p>
                <a href='{$_SERVER['PHP_SELF']}'>Volver al inicio</a>
             </div>");
    }
}

// ===================================================================
// FIN DEL SISTEMA DE PERMISOS
// ===================================================================
?> 