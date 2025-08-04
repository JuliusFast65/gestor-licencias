<?php
/**
 * Script para verificar la estructura de la tabla Empresas
 * Abrir en el navegador: http://localhost/verificar_empresas.php
 */

echo "<h2>=== VERIFICACIÓN DE ESTRUCTURA DE TABLA EMPRESAS ===</h2>";
echo "<p>Fecha: " . date('Y-m-d H:i:s') . "</p>";

// Incluir el archivo de conexión
require_once('apis/Conectar_BD.php');

try {
    echo "<p>Conectando a la base de datos...</p>";
    $conn = Conectar_BD();
    
    if ($conn) {
        echo "<p>✅ CONEXIÓN EXITOSA</p>";
        
        // Verificar estructura de tabla Empresas
        echo "<h3>=== ESTRUCTURA DE TABLA EMPRESAS ===</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $result = $conn->query("DESCRIBE Empresas");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['Field'] . "</td>";
                echo "<td>" . $row['Type'] . "</td>";
                echo "<td>" . $row['Null'] . "</td>";
                echo "<td>" . $row['Key'] . "</td>";
                echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row['Extra'] ?? '') . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='6'>❌ Error al describir tabla Empresas</td></tr>";
        }
        echo "</table>";
        
        // Mostrar algunos datos de ejemplo
        echo "<h3>=== MUESTRA DE DATOS DE EMPRESAS ===</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>RUC</th><th>Nombre</th><th>Baja</th></tr>";
        
        $result = $conn->query("SELECT * FROM Empresas LIMIT 5");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['Ruc'] . "</td>";
                echo "<td>" . $row['Nombre'] . "</td>";
                echo "<td>" . ($row['Baja'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='3'>❌ No hay datos en tabla Empresas o error en consulta</td></tr>";
        }
        echo "</table>";
        
        $conn->close();
        echo "<p>✅ VERIFICACIÓN COMPLETADA</p>";
    } else {
        echo "<p>❌ ERROR: No se pudo establecer la conexión</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ ERROR: " . $e->getMessage() . "</p>";
}

echo "<h3>=== FIN DE LA VERIFICACIÓN ===</h3>";
?> 