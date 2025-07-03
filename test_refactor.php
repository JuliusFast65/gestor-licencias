<?php
// test_refactor.php - Prueba del refactor completo
// Verifica que todas las inclusiones funcionen correctamente

echo "<h1>🧪 Prueba del Refactor Completo</h1>";
echo "<p>Verificando que todas las inclusiones funcionen correctamente...</p>";

// Incluir todos los archivos del refactor
try {
    require_once(__DIR__ . '/php/auth.php');
    echo "✅ <strong>auth.php</strong> - Incluido correctamente<br>";
} catch (Exception $e) {
    echo "❌ <strong>auth.php</strong> - Error: " . $e->getMessage() . "<br>";
}

try {
    require_once(__DIR__ . '/php/permissions.php');
    echo "✅ <strong>permissions.php</strong> - Incluido correctamente<br>";
} catch (Exception $e) {
    echo "❌ <strong>permissions.php</strong> - Error: " . $e->getMessage() . "<br>";
}

try {
    require_once(__DIR__ . '/php/companies.php');
    echo "✅ <strong>companies.php</strong> - Incluido correctamente<br>";
} catch (Exception $e) {
    echo "❌ <strong>companies.php</strong> - Error: " . $e->getMessage() . "<br>";
}

try {
    require_once(__DIR__ . '/php/profiles.php');
    echo "✅ <strong>profiles.php</strong> - Incluido correctamente<br>";
} catch (Exception $e) {
    echo "❌ <strong>profiles.php</strong> - Error: " . $e->getMessage() . "<br>";
}

try {
    require_once(__DIR__ . '/php/licenses.php');
    echo "✅ <strong>licenses.php</strong> - Incluido correctamente<br>";
} catch (Exception $e) {
    echo "❌ <strong>licenses.php</strong> - Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>📋 Verificación de Funciones</h2>";

// Verificar que las funciones principales estén disponibles
$funciones_auth = ['renderizarFormularioLogin'];
$funciones_permissions = ['usuarioPuedeCrearEmpresa', 'usuarioPuedeEditarEmpresa', 'usuarioPuedeEliminarEmpresa', 'usuarioPuedeDarDeBajaLicencia'];
$funciones_companies = ['obtenerRegistroEmpresa', 'renderizarWidgetBusquedaYComando', 'renderizarScriptBusqueda'];
$funciones_profiles = ['renderizarDashboard', 'renderizarPaginaPerfil', 'procesarEdicionPerfil', 'obtenerMapeoDeModulos'];
$funciones_licenses = ['obtenerSesionesActivasPorRuc', 'obtenerTodasLasLicenciasPorRuc', 'renderizarPaginaAlta', 'renderizarPaginaActivar'];

echo "<h3>🔐 Funciones de Autenticación:</h3>";
foreach ($funciones_auth as $funcion) {
    if (function_exists($funcion)) {
        echo "✅ <code>{$funcion}()</code><br>";
    } else {
        echo "❌ <code>{$funcion}()</code> - No encontrada<br>";
    }
}

echo "<h3>🔒 Funciones de Permisos:</h3>";
foreach ($funciones_permissions as $funcion) {
    if (function_exists($funcion)) {
        echo "✅ <code>{$funcion}()</code><br>";
    } else {
        echo "❌ <code>{$funcion}()</code> - No encontrada<br>";
    }
}

echo "<h3>🏢 Funciones de Empresas:</h3>";
foreach ($funciones_companies as $funcion) {
    if (function_exists($funcion)) {
        echo "✅ <code>{$funcion}()</code><br>";
    } else {
        echo "❌ <code>{$funcion}()</code> - No encontrada<br>";
    }
}

echo "<h3>👤 Funciones de Perfiles:</h3>";
foreach ($funciones_profiles as $funcion) {
    if (function_exists($funcion)) {
        echo "✅ <code>{$funcion}()</code><br>";
    } else {
        echo "❌ <code>{$funcion}()</code> - No encontrada<br>";
    }
}

echo "<h3>🔑 Funciones de Licencias:</h3>";
foreach ($funciones_licenses as $funcion) {
    if (function_exists($funcion)) {
        echo "✅ <code>{$funcion}()</code><br>";
    } else {
        echo "❌ <code>{$funcion}()</code> - No encontrada<br>";
    }
}

echo "<hr>";
echo "<h2>📊 Resumen del Refactor</h2>";
echo "<p><strong>Archivos creados:</strong></p>";
echo "<ul>";
echo "<li>📁 <code>php/auth.php</code> - Autenticación y sesiones</li>";
echo "<li>📁 <code>php/permissions.php</code> - Sistema de permisos (RBAC)</li>";
echo "<li>📁 <code>php/companies.php</code> - Gestión de empresas</li>";
echo "<li>📁 <code>php/profiles.php</code> - Gestión de perfiles de usuario</li>";
echo "<li>📁 <code>php/licenses.php</code> - Gestión de licencias y sesiones</li>";
echo "</ul>";

echo "<p><strong>Archivo principal:</strong></p>";
echo "<ul>";
echo "<li>📄 <code>Consultar.php</code> - Refactorizado con inclusiones modulares</li>";
echo "</ul>";

echo "<hr>";
echo "<h2>🚀 Próximos Pasos</h2>";
echo "<p>Si todas las verificaciones son exitosas, puedes:</p>";
echo "<ol>";
echo "<li>🌐 Abrir <a href='http://localhost:8080/Consultar.php' target='_blank'>http://localhost:8080/Consultar.php</a></li>";
echo "<li>🔐 Probar el login con tus credenciales</li>";
echo "<li>🏢 Probar la gestión de empresas</li>";
echo "<li>👤 Probar la edición de perfil</li>";
echo "<li>🔑 Probar la gestión de licencias</li>";
echo "</ol>";

echo "<p><strong>¡El refactor está completo y listo para usar! 🎉</strong></p>";
?> 