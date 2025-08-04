<?php
/**
 * Script para verificar la estructura real de las tablas
 * Ejecutar desde la línea de comandos: php verificar_estructura_bd.php
 */

echo "=== VERIFICACIÓN DE ESTRUCTURA DE BASE DE DATOS ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Incluir el archivo de conexión
require_once('apis/Conectar_BD.php');

try {
    echo "Conectando a la base de datos...\n";
    $conn = Conectar_BD();
    
    if ($conn) {
        echo "✅ CONEXIÓN EXITOSA\n\n";
        
        // Verificar estructura de tabla Empresas
        echo "=== ESTRUCTURA DE TABLA EMPRESAS ===\n";
        $result = $conn->query("DESCRIBE Empresas");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                echo "Campo: " . $row['Field'] . " | Tipo: " . $row['Type'] . " | Null: " . $row['Null'] . " | Key: " . $row['Key'] . "\n";
            }
        } else {
            echo "❌ Error al describir tabla Empresas\n";
        }
        
        // Verificar estructura de tabla Licencias
        echo "\n=== ESTRUCTURA DE TABLA LICENCIAS ===\n";
        $result = $conn->query("DESCRIBE Licencias");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                echo "Campo: " . $row['Field'] . " | Tipo: " . $row['Type'] . " | Null: " . $row['Null'] . " | Key: " . $row['Key'] . "\n";
            }
        } else {
            echo "❌ Error al describir tabla Licencias\n";
        }
        
        echo "\n=== ESTRUCTURA DE TABLA SESIONES_ACTIVAS ===\n";
        $result = $conn->query("DESCRIBE Sesiones_Activas");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                echo "Campo: " . $row['Field'] . " | Tipo: " . $row['Type'] . " | Null: " . $row['Null'] . " | Key: " . $row['Key'] . "\n";
            }
        } else {
            echo "❌ Error al describir tabla Sesiones_Activas\n";
        }
        
        echo "\n=== MUESTRA DE DATOS DE EMPRESAS ===\n";
        $result = $conn->query("SELECT * FROM Empresas LIMIT 3");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "RUC: " . $row['Ruc'] . " | Nombre: " . $row['Nombre'] . " | Baja: " . ($row['Baja'] ?? 'NULL') . "\n";
            }
        } else {
            echo "❌ No hay datos en tabla Empresas o error en consulta\n";
        }
        
        echo "\n=== MUESTRA DE DATOS DE LICENCIAS ===\n";
        $result = $conn->query("SELECT * FROM Licencias LIMIT 3");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "PK_Licencia: " . $row['PK_Licencia'] . " | Serie: " . $row['Serie'] . " | Sistema: " . $row['Sistema'] . " | Fecha: " . $row['Fecha_Activacion'] . "\n";
            }
        } else {
            echo "❌ No hay datos en tabla Licencias o error en consulta\n";
        }
        
        echo "\n=== MUESTRA DE DATOS DE SESIONES_ACTIVAS ===\n";
        $result = $conn->query("SELECT * FROM Sesiones_Activas LIMIT 3");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "PK_Sesion: " . $row['PK_Sesion'] . " | Serie: " . $row['Serie'] . " | Sistema: " . $row['Sistema'] . " | Fecha: " . $row['Fecha_Activacion'] . "\n";
            }
        } else {
            echo "❌ No hay datos en tabla Sesiones_Activas o error en consulta\n";
        }
        
        $conn->close();
        echo "\n✅ VERIFICACIÓN COMPLETADA\n";
    } else {
        echo "❌ ERROR: No se pudo establecer la conexión\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE LA VERIFICACIÓN ===\n";
?> 