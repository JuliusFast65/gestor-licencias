<?php
/**
 * Función para conectar a la base de datos usando mysqli
 * @param int $tipo Tipo de conexión (0 = mysqli, 1 = legacy - deprecated)
 * @return mysqli|resource Conexión a la base de datos
 */
function Conectar_BD($tipo = 0){ 
   // Configuración de la base de datos
   // DESARROLLO LOCAL - MySQL local
   $host = 'localhost';  // MySQL local de XAMPP
   $usuario = 'root';    // Usuario por defecto de XAMPP
   $password = '';       // Sin contraseña por defecto
   $database = 'listosof_listosoft';
   
   // PRODUCCIÓN - MySQL remoto (comentado para desarrollo)
   // $host = '154.38.178.58';  // IP del servidor para acceso remoto
   // $usuario = 'listosof';
   // $password = 'rdtF)jHnHR*!';
   // $database = 'listosof_listosoft';
   
   if ($tipo) {
       // Conexión legacy (deprecated - no usar)
       if (! $conn = mysql_pconnect($host, $usuario, $password))
          die("Error de Base de Datos"); 
   } else {
        // Conexión moderna usando mysqli
        $conn = new mysqli($host, $usuario, $password, $database);
        
        // Verificar si hay errores de conexión
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Configurar charset para evitar problemas con caracteres especiales
        $conn->set_charset("utf8");
   }
   return $conn;
}
?>