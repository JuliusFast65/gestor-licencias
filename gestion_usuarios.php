<?php
session_start();

// --- Guardián de Seguridad ---
if (!isset($_SESSION['pk_usuario']) || $_SESSION['perfil'] !== 'presidente') {
    header('HTTP/1.0 403 Forbidden');
    die('<h1>Acceso Denegado</h1><p>No tienes los permisos necesarios para acceder a este módulo.</p><a href="dashboard.php">Volver al Dashboard</a>');
}

// --- Conexión a BD ---
require_once('../apis/Conectar_BD.php');
$conn = Conectar_BD();

$accion = $_REQUEST['accion'] ?? 'listar';
$mensaje = '';
$errores = [];

// --- Lógica para procesar acciones (Crear, Actualizar, Eliminar) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pk_usuario = $_POST['pk_usuario'] ?? null;
    $usuario = trim($_POST['usuario']);
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']); // NUEVO: Captura del email
    $perfil = $_POST['perfil'];
    $password = $_POST['password'];

    // --- NUEVO: Bloque de validación antes de ejecutar la consulta ---
    
    // 1. Validar que el nombre de usuario no esté duplicado
    $stmt = $conn->prepare("SELECT PK_Usuario FROM Usuarios WHERE Usuario = ? AND PK_Usuario != ?");
    $stmt->bind_param("si", $usuario, $pk_usuario);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errores[] = "El nombre de usuario '{$usuario}' ya está en uso por otro usuario.";
    }
    
    // 2. Validar que el email no esté duplicado
    $stmt = $conn->prepare("SELECT PK_Usuario FROM Usuarios WHERE Email = ? AND PK_Usuario != ?");
    $stmt->bind_param("si", $email, $pk_usuario);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errores[] = "El email '{$email}' ya está registrado.";
    }

    // 3. Validar que la contraseña no esté vacía al crear un nuevo usuario
    if ($accion === 'guardar_nuevo' && empty($password)) {
        $errores[] = "La contraseña es obligatoria para crear un nuevo usuario.";
    }

    if (empty($errores)) {
        // Si no hay errores, procedemos a guardar o actualizar
        if ($accion === 'guardar_nuevo') {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO Usuarios (Usuario, Password, Nombre, Telefono, Email, Perfil, Alta) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssss", $usuario, $hashed_password, $nombre, $telefono, $email, $perfil);
            
            if ($stmt->execute()) {
                $mensaje = "Usuario creado exitosamente.";
                $accion = 'listar';
            } else {
                $errores[] = "Error al crear el usuario: " . $stmt->error;
            }

        } elseif ($accion === 'actualizar') {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE Usuarios SET Usuario = ?, Nombre = ?, Telefono = ?, Email = ?, Perfil = ?, Password = ? WHERE PK_Usuario = ?");
                $stmt->bind_param("ssssssi", $usuario, $nombre, $telefono, $email, $perfil, $hashed_password, $pk_usuario);
            } else {
                $stmt = $conn->prepare("UPDATE Usuarios SET Usuario = ?, Nombre = ?, Telefono = ?, Email = ?, Perfil = ? WHERE PK_Usuario = ?");
                $stmt->bind_param("sssssi", $usuario, $nombre, $telefono, $email, $perfil, $pk_usuario);
            }

            if ($stmt->execute()) {
                $mensaje = "Usuario actualizado exitosamente.";
                $accion = 'listar';
            } else {
                $errores[] = "Error al actualizar el usuario: " . $stmt->error;
            }
        }
    }

    // Si hubo errores, el script continuará y mostrará el formulario de nuevo con los errores
    if (!empty($errores)) {
        $mensaje = implode("<br>", $errores);
        // Forzamos a que se muestre de nuevo el formulario de edición o creación
        $accion = $pk_usuario ? 'editar' : 'crear';
    }
}

// Procesar GET para eliminar
if ($accion === 'eliminar' && isset($_GET['id'])) {
    $pk_usuario_eliminar = $_GET['id'];
    if ($pk_usuario_eliminar == $_SESSION['pk_usuario']) {
        $mensaje = "Error: No puedes eliminar tu propio usuario.";
    } else {
        $stmt = $conn->prepare("UPDATE Usuarios SET Baja = NOW() WHERE PK_Usuario = ?");
        $stmt->bind_param("i", $pk_usuario_eliminar);
        $stmt->execute();
        $mensaje = "Usuario eliminado (desactivado) correctamente.";
    }
    $accion = 'listar';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <style>
        /* (Los estilos CSS son los mismos, no es necesario copiarlos de nuevo si ya los tienes) */
        body { font-family: sans-serif; margin: 2rem; background-color: #f9f9f9; }
        h1, h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 0.5rem; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 0.6rem 1.2rem; margin: 0.2rem; border-radius: 5px; color: white; cursor: pointer; border: none; }
        .btn-primary { background-color: #007bff; }
        .btn-success { background-color: #28a745; }
        .btn-warning { background-color: #ffc107; color: #212529; }
        .btn-danger { background-color: #dc3545; }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { border: 1px solid #ddd; padding: 0.8rem; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .form-container { max-width: 500px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; }
        .mensaje { padding: 1rem; margin-bottom: 1rem; border-radius: 5px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensaje-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>
<div class="container">
    <a href="dashboard.php">← Volver al Dashboard</a>
    <h1>Gestión de Usuarios</h1>

    <?php if ($mensaje && empty($errores)): ?>
        <div class="mensaje"><?php echo $mensaje; ?></div>
    <?php elseif ($mensaje && !empty($errores)): ?>
        <div class="mensaje mensaje-error"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <?php if ($accion === 'listar'): ?>
        <h2>Lista de Usuarios Activos</h2>
        <a href="?accion=crear" class="btn btn-primary">Crear Nuevo Usuario</a>
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th> <!-- NUEVO -->
                    <th>Teléfono</th>
                    <th>Perfil</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // MODIFICADO: Se añade Email a la consulta
                $result = $conn->query("SELECT PK_Usuario, Nombre, Usuario, Email, Telefono, Perfil FROM Usuarios WHERE Baja IS NULL ORDER BY Nombre");
                while ($row = $result->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['Nombre']); ?></td>
                    <td><?php echo htmlspecialchars($row['Usuario']); ?></td>
                    <td><?php echo htmlspecialchars($row['Email']); ?></td> <!-- NUEVO -->
                    <td><?php echo htmlspecialchars($row['Telefono']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($row['Perfil'])); ?></td>
                    <td>
                        <a href="?accion=editar&id=<?php echo $row['PK_Usuario']; ?>" class="btn btn-warning">Editar</a>
                        <a href="?accion=eliminar&id=<?php echo $row['PK_Usuario']; ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro?');">Eliminar</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($accion === 'crear' || $accion === 'editar'): ?>
        <?php
        $es_edicion = ($accion === 'editar');
        $titulo_form = $es_edicion ? 'Editar Usuario' : 'Crear Nuevo Usuario';
        $accion_form = $es_edicion ? 'actualizar' : 'guardar_nuevo';
        
        $usuario_data = [
            'PK_Usuario' => '', 'Usuario' => '', 'Nombre' => '', 'Email' => '', 'Telefono' => '', 'Perfil' => 'soporte'
        ];

        if ($es_edicion && isset($_GET['id'])) {
            $stmt = $conn->prepare("SELECT PK_Usuario, Usuario, Nombre, Email, Telefono, Perfil FROM Usuarios WHERE PK_Usuario = ?");
            $stmt->bind_param("i", $_GET['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $usuario_data = $result->fetch_assoc();
            }
        }
        
        // MODIFICADO: Si hubo un error de validación, se repopulan los datos con lo que el usuario envió
        if (!empty($errores)) {
            $usuario_data = array_merge($usuario_data, $_POST);
        }
        ?>
        <h2><?php echo $titulo_form; ?></h2>
        <div class="form-container">
            <form method="POST" action="gestion_usuarios.php">
                <input type="hidden" name="accion" value="<?php echo $accion_form; ?>">
                <input type="hidden" name="pk_usuario" value="<?php echo htmlspecialchars($usuario_data['PK_Usuario']); ?>">
                
                <div class="form-group">
                    <label for="nombre">Nombre Completo:</label>
                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario_data['Nombre']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="usuario">Nombre de Usuario (para login):</label>
                    <input type="text" id="usuario" name="usuario" value="<?php echo htmlspecialchars($usuario_data['Usuario']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label> <!-- NUEVO -->
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario_data['Email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($usuario_data['Telefono']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="perfil">Perfil:</label>
                    <select id="perfil" name="perfil" required>
                        <?php
                        $perfiles = ['presidente', 'administrador', 'soporte', 'desarrollo'];
                        foreach ($perfiles as $p) {
                            $selected = ($usuario_data['Perfil'] === $p) ? 'selected' : '';
                            echo "<option value='{$p}' {$selected}>" . ucfirst($p) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña<?php echo $es_edicion ? ' (Dejar en blanco para no cambiar)' : ''; ?>:</label>
                    <input type="password" id="password" name="password" <?php echo !$es_edicion ? 'required' : ''; ?>>
                </div>
                <button type="submit" class="btn btn-success">Guardar Cambios</button>
                <a href="gestion_usuarios.php" class="btn" style="background-color:#6c757d;">Cancelar</a>
            </form>
        </div>
    <?php endif; ?>

</div>
</body>
</html>