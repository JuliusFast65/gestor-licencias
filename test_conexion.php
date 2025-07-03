<?php
/**
 * Script de prueba para verificar la conexión remota a MySQL
 * Ejecutar desde la línea de comandos: php test_conexion.php
 */

echo "=== PRUEBA DE CONEXIÓN REMOTA A MYSQL ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Incluir el archivo de conexión
require_once('apis/Conectar_BD.php');

try {
    echo "Intentando conectar a la base de datos...\n";
    
    // Intentar la conexión
    $conn = Conectar_BD();
    
    if ($conn) {
        echo "✅ CONEXIÓN EXITOSA\n";
        echo "Host: " . $conn->host_info . "\n";
        echo "Server: " . $conn->server_info . "\n";
        echo "Client: " . $conn->client_info . "\n";
        
        // Probar una consulta simple
        $result = $conn->query("SELECT COUNT(*) as total FROM Usuarios");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Total de usuarios en la BD: " . $row['total'] . "\n";
        }
        
        $conn->close();
        echo "\n✅ PRUEBA COMPLETADA - Todo funciona correctamente\n";
    } else {
        echo "❌ ERROR: No se pudo establecer la conexión\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Esto puede indicar que:\n";
    echo "1. MySQL no permite conexiones remotas\n";
    echo "2. El firewall está bloqueando el puerto 3306\n";
    echo "3. El usuario no tiene permisos de conexión remota\n";
    echo "4. La IP o credenciales son incorrectas\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
?> 