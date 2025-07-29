<?
function Conectar_BD($tipo = 0){ 
   if ($tipo) {
       // Establezco una conexion con el servidor de BD
       if (! $conn = mysql_pconnect("localhost","listosof","rdtF)jHnHR*!"))
          die("Error de Base de Datos"); }
   else {
        $conn = new mysqli("localhost","listosof","rdtF)jHnHR*!","listosof_listosoft");

        /*if (! $conn)
          die("Error de Base de Datos. " . $conn->connect_errno);*/
        // Para más detalles del estado de la conexión:
        /*echo "<br>Host info: " . $conn->host_info;
        echo "<br>Client info: " . $conn->client_info;
        echo "<br>Server info: " . $conn->server_info;*/

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
   }
   return $conn;
}
?>