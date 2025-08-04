<?php
/**
 * Función para conectar a la base de datos usando mysqli
 * @param int $tipo Tipo de conexión (0 = mysqli, 1 = legacy - deprecated)
 * @return mysqli|resource Conexión a la base de datos
 */
function Conectar_BD($tipo = 0){ 
   // Detectar si estamos en desarrollo local o producción
   $is_local = false;
   if (isset($_SERVER['HTTP_HOST'])) {
       // Si es localhost con puerto específico, es desarrollo local
       $is_local = (strpos($_SERVER['HTTP_HOST'], 'localhost:') !== false);
   } else {
       // Si no hay HTTP_HOST (línea de comandos), asumir local
       $is_local = true;
   }
   
   if ($is_local) {
       // DESARROLLO LOCAL - MySQL local con puerto personalizado
       $host = 'localhost:3307';  // MySQL local de XAMPP en puerto 3307
       $usuario = 'root';    // Usuario por defecto de XAMPP
       $password = '';       // Sin contraseña por defecto
       $database = 'listosof_listosoft';
   } else {
       // PRODUCCIÓN - MySQL local (sin puerto específico)
       $host = 'localhost';  // MySQL en puerto por defecto (3306)
       $usuario = 'listosof';
       $password = 'rdtF)jHnHR*!';
       $database = 'listosof_listosoft';
   }
   
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