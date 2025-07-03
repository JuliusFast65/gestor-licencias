<?php
// Script de prueba para verificar que companies.php funciona correctamente

// Simular sesión
session_start();
$_SESSION['usuario'] = 'test';
$_SESSION['nombre'] = 'Usuario de Prueba';
$_SESSION['perfil'] = 'administrador';

// Incluir los archivos necesarios
require_once(__DIR__ . '/php/auth.php');
require_once(__DIR__ . '/php/permissions.php');
require_once(__DIR__ . '/php/companies.php');
require_once(__DIR__ . '/php/profiles.php');
require_once(__DIR__ . '/php/licenses.php');

echo "<h1>Prueba de Companies.php</h1>";

// Verificar que las funciones están disponibles
$funciones_esperadas = [
    'obtenerRegistroEmpresa',
    'renderizarFormularioEmpresa', 
    'procesarFormularioEmpresa',
    'procesarBusquedaLive',
    'renderizarWidgetBusquedaYComando',
    'renderizarScriptBusqueda',
    'procesarEliminarEmpresa'
];

echo "<h2>Verificando funciones disponibles:</h2>";
foreach ($funciones_esperadas as $funcion) {
    if (function_exists($funcion)) {
        echo "✅ <strong>{$funcion}</strong> - Disponible<br>";
    } else {
        echo "❌ <strong>{$funcion}</strong> - NO disponible<br>";
    }
}

// Verificar constantes de protección
echo "<h2>Verificando constantes de protección:</h2>";
if (defined('AUTH_INCLUDED')) {
    echo "✅ AUTH_INCLUDED definida<br>";
} else {
    echo "❌ AUTH_INCLUDED NO definida<br>";
}

if (defined('PERMISSIONS_INCLUDED')) {
    echo "✅ PERMISSIONS_INCLUDED definida<br>";
} else {
    echo "❌ PERMISSIONS_INCLUDED NO definida<br>";
}

if (defined('COMPANIES_INCLUDED')) {
    echo "✅ COMPANIES_INCLUDED definida<br>";
} else {
    echo "❌ COMPANIES_INCLUDED NO definida<br>";
}

echo "<h2>Prueba completada</h2>";
echo "<p>Si todas las funciones están disponibles, la refactorización fue exitosa.</p>";
?> 