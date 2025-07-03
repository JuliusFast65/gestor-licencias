<?php
// companies.php - Lógica de gestión de empresas para el Gestor de Licencias
// Incluye: CRUD de empresas, formularios, búsqueda y eliminación.
// Este archivo debe ser incluido después de auth.php y permissions.php.

// Protección contra inclusiones múltiples
if (defined('COMPANIES_INCLUDED')) {
    return;
}
define('COMPANIES_INCLUDED', true);

// --- Funciones de gestión de empresas ---

// Obtener datos de una empresa por RUC
function obtenerRegistroEmpresa(mysqli $conn, string $Ruc): ?array {
    $stmt = $conn->prepare("SELECT * FROM Empresas WHERE Ruc = ?");
    $stmt->bind_param("s", $Ruc);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Muestra el formulario de alta o edición de una empresa.
 * Si $Ruc es null, es alta; si no, es edición.
 * Usa variables de sesión para mensajes de error y datos temporales.
 * Depende de: obtenerRegistroEmpresa(), usuarioPuedeEliminarEmpresa()
 */
function renderizarFormularioEmpresa(mysqli $conn, ?string $Ruc): void {
    $esEdicion = $Ruc !== null;
    $empresa = [];
    $tituloPagina = "Alta de Nueva Empresa";

    // VALIDACIÓN/ELIMINACIÓN: Comprobar si hay datos de un intento fallido en la sesión
    $datosFormulario = $_SESSION['form_data_temp'] ?? null;
    $errorFormulario = $_SESSION['form_error_temp'] ?? null;
    unset($_SESSION['form_data_temp'], $_SESSION['form_error_temp']);

    if ($esEdicion) {
        $empresa = obtenerRegistroEmpresa($conn, $Ruc);
        if (!$empresa) {
            die("Error: No se encontró la empresa con RUC " . htmlspecialchars($Ruc));
        }
        $tituloPagina = "Editando Empresa: " . htmlspecialchars($empresa['Nombre']);
    }

    // Usar datos de la sesión si existen (en caso de error de validación), si no, usar los de la BD o por defecto.
    $fuenteDeDatos = $datosFormulario ?? $empresa;
    
    $val = function($key, $default = '') use ($fuenteDeDatos) {
        return htmlspecialchars($fuenteDeDatos[$key] ?? $default, ENT_QUOTES, 'UTF-8');
    };

    $actionUrl = htmlspecialchars($_SERVER['PHP_SELF']);
    $accionProceso = $esEdicion ? 'Procesar_Edicion_Empresa' : 'Procesar_Alta_Empresa';

    // Lógica para determinar la URL de retorno del botón "Cancelar"
    $urlCancelar = $_SERVER['PHP_SELF']; // Por defecto, al dashboard
    $rucContexto = $Ruc ?? $_SESSION['Ruc'] ?? '';
    if (!empty($rucContexto)) {
        $urlCancelar = $_SERVER['PHP_SELF'] . '?Accion=Administrar&Ruc=' . urlencode($rucContexto);
    }
    
    $inicioSuscripcionDefault = $val('Inicio_Suscripcion') ?: date('Y-m-d');
    $finSuscripcionDefault = $val('Fin_Suscripcion') ?: date('Y-m-d', strtotime('+1 year'));

    $enListaNegra = $val('En_Lista_Negra');
    $opcionSiSeleccionada = $enListaNegra === '1' ? 'selected' : '';
    $opcionNoSeleccionada = $enListaNegra !== '1' ? 'selected' : '';

    echo <<<HTML
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>{$tituloPagina}</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 20px; }
        .form-container { max-width: 900px; margin: auto; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .form-header { padding: 20px 30px; background-color: #343a40; color: white; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .form-content { padding: 30px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .input-group { display: flex; flex-direction: column; }
        label { font-weight: bold; margin-bottom: 5px; color: #333; }
        input[type=text], input[type=number], input[type=email], input[type=date], textarea, select {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em;
        }
        input:disabled, input:read-only { background-color: #e9ecef; cursor: not-allowed; }
        .form-actions { text-align: right; margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;}
        .btn-submit { background-color: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1em; }
        .btn-submit:hover { background-color: #218838; }
        .btn-cancel { display: inline-block; background-color: #6c757d; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px; }
        .tab-nav { list-style-type: none; padding: 0; margin: 0; border-bottom: 2px solid #dee2e6; display: flex; }
        .tab-nav-item { margin-bottom: -2px; }
        .tab-nav-link { display: block; padding: 10px 20px; border: 2px solid transparent; border-bottom: none; border-top-left-radius: .25rem; border-top-right-radius: .25rem; cursor: pointer; color: #007bff; text-decoration: none; }
        .tab-nav-link.active { color: #495057; background-color: #fff; border-color: #dee2e6 #dee2e6 #fff; }
        .tab-content { display: none; padding-top: 20px; }
        .tab-content.active { display: block; }
        .sub-section-title { margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; font-size: 1.1em; color: #495057; }
    </style>
    </head><body>
    <div class="form-container">
        <div class="form-header"><h1>{$tituloPagina}</h1></div>
        <div class="form-content">
HTML;

    if ($errorFormulario) {
        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px;'>" . htmlspecialchars($errorFormulario) . "</div>";
    }
    if (isset($_SESSION['mensaje_flash_error'])) {
        echo "<div style='background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px;'>" . htmlspecialchars($_SESSION['mensaje_flash_error']) . "</div>";
        unset($_SESSION['mensaje_flash_error']);
    }

    echo <<<HTML
            <form action="{$actionUrl}" method="POST" id="empresa-form">
                <input type="hidden" name="Accion" value="{$accionProceso}">
                <ul class="tab-nav">
                    <li class="tab-nav-item"><a class="tab-nav-link active" data-tab="tab-general">Datos Principales</a></li>
                    <li class="tab-nav-item"><a class="tab-nav-link" data-tab="tab-suscripcion">Suscripción y Mensajes</a></li>
                    <li class="tab-nav-item"><a class="tab-nav-link" data-tab="tab-licencias">Licencias y Versiones</a></li>
                    <li class="tab-nav-item"><a class="tab-nav-link" data-tab="tab-sistema">Datos de Sistema</a></li>
                </ul>

                <div id="tab-general" class="tab-content active">
                    <div class="form-grid">
                        <div class="input-group"><label for="Nombre">Nombre</label><input type="text" id="Nombre" name="Nombre" value="{$val('Nombre')}" maxlength="30" required></div>
                        <div class="input-group"><label for="RUC">RUC</label><input type="text" id="RUC" name="RUC" value="{$val('RUC')}" maxlength="13" required " . ($esEdicion ? 'readonly' : '') . "></div>
                        <div class="input-group"><label for="Telefono">Teléfono</label><input type="text" id="Telefono" name="Telefono" value="{$val('Telefono')}" maxlength="9"></div>
                        <div class="input-group"><label for="Ciudad">Ciudad</label><input type="text" id="Ciudad" name="Ciudad" value="{$val('Ciudad')}" maxlength="30"></div>
                        <div class="input-group"><label for="eMail">Email</label><input type="email" id="eMail" name="eMail" value="{$val('eMail')}" maxlength="100"></div>
                        <div class="input-group"><label for="eMail2">Email 2</label><input type="email" id="eMail2" name="eMail2" value="{$val('eMail2')}" maxlength="100"></div>
                    </div>
                    <div class="input-group" style="margin-top:20px;"><label for="Comentario">Comentario Interno</label><textarea id="Comentario" name="Comentario" rows="4">{$val('Comentario')}</textarea></div>
                </div>
                <div id="tab-suscripcion" class="tab-content">
                    <!-- Un sólo grid de 3 columnas -->
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem;">
                        <div class="input-group">
                            <label for="Inicio_Suscripcion">Suscrito desde</label>
                            <input type="date" id="Inicio_Suscripcion" name="Inicio_Suscripcion"
                                   value="{$inicioSuscripcionDefault}" required>
                        </div>
                        <div class="input-group">
                            <label for="Fin_Suscripcion">Suscrito hasta</label>
                            <input type="date" id="Fin_Suscripcion" name="Fin_Suscripcion"
                                   value="{$finSuscripcionDefault}" required>
                        </div>
                        <div class="input-group">
                            <label for="En_Lista_Negra">¿Mostrar Mensaje al Iniciar?</label>
                            <select id="En_Lista_Negra" name="En_Lista_Negra">
                                <option value="1" {$opcionSiSeleccionada}>Sí</option>
                                <option value="0" {$opcionNoSeleccionada}>No</option>
                            </select>
                        </div>
                    </div>
                
                    <!-- Mensaje a Mostrar como textarea full-width, 4 filas, mismo estilo que Comentario Interno -->
                    <div class="input-group" style="margin-top:20px;">
                        <label for="Motivo_Lista_Negra">Mensaje a Mostrar</label>
                        <textarea id="Motivo_Lista_Negra" name="Motivo_Lista_Negra" rows="4">{$val('Motivo_Lista_Negra')}</textarea>
                    </div>
                </div>
                <div id="tab-licencias" class="tab-content">
                    <p>Ingrese la cantidad de licencias adquiridas para cada módulo y la versión instalada en la empresa.</p>
                
                    <h4 class="sub-section-title">F-Soft (Escritorio)</h4>
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(4,1fr); gap: 20px;">
                        <div class="input-group">
                            <label for="Version_FSoft">Versión</label>
                            <input type="text" id="Version_FSoft" name="Version_FSoft"
                                   value="{$val('Version_FSoft')}" maxlength="10" readonly>
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_FSOFT_BA">Lic. Básicas</label>
                            <input type="number" id="Cant_Lic_FSOFT_BA" name="Cant_Lic_FSOFT_BA"
                                   value="{$val('Cant_Lic_FSOFT_BA', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_FSOFT_RP">Mód. Nómina</label>
                            <input type="number" id="Cant_Lic_FSOFT_RP" name="Cant_Lic_FSOFT_RP"
                                   value="{$val('Cant_Lic_FSOFT_RP', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_FSOFT_CE">Mód. Comp. Elec.</label>
                            <input type="number" id="Cant_Lic_FSOFT_CE" name="Cant_Lic_FSOFT_CE"
                                   value="{$val('Cant_Lic_FSOFT_CE', 0)}" min="0" style="text-align: right;">
                        </div>
                    </div>
                
                    <h4 class="sub-section-title">L-Soft (Escritorio)</h4>
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(4,1fr); gap: 20px;">
                        <div class="input-group">
                            <label for="Version_LSoft">Versión</label>
                            <input type="text" id="Version_LSoft" name="Version_LSoft"
                                   value="{$val('Version_LSoft')}" maxlength="10" readonly>
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_BA">Lic. Básicas</label>
                            <input type="number" id="Cant_Lic_LSOFT_BA" name="Cant_Lic_LSOFT_BA"
                                   value="{$val('Cant_Lic_LSOFT_BA', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_RP">Mód. Nómina</label>
                            <input type="number" id="Cant_Lic_LSOFT_RP" name="Cant_Lic_LSOFT_RP"
                                   value="{$val('Cant_Lic_LSOFT_RP', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_CE">Mód. Comp. Elec.</label>
                            <input type="number" id="Cant_Lic_LSOFT_CE" name="Cant_Lic_LSOFT_CE"
                                   value="{$val('Cant_Lic_LSOFT_CE', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_AF">Mód. Activos Fijos</label>
                            <input type="number" id="Cant_Lic_LSOFT_AF" name="Cant_Lic_LSOFT_AF"
                                   value="{$val('Cant_Lic_LSOFT_AF', 0)}" min="0" style="text-align: right;">
                        </div>
                        <div class="input-group">
                            <label for="Cant_Lic_LSOFT_OP">Mód. Producción</label>
                            <input type="number" id="Cant_Lic_LSOFT_OP" name="Cant_Lic_LSOFT_OP"
                                   value="{$val('Cant_Lic_LSOFT_OP', 0)}" min="0" style="text-align: right;">
                        </div>
                    </div>
                
                    <h4 class="sub-section-title">L-Soft Web (Blazor)</h4>
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(4,1fr); gap: 20px;">
                         <div class="input-group">
                             <label for="Version_LSoft_Web">Versión</label>
                             <input type="text" id="Version_LSoft_Web" name="Version_LSoft_Web"
                                    value="{$val('Version_LSoft_Web')}" maxlength="10" readonly>
                         </div>
                         <div class="input-group">
                             <label for="Cant_Lic_LSOFTW_BA">Lic. Básicas</label>
                             <input type="number" id="Cant_Lic_LSOFTW_BA" name="Cant_Lic_LSOFTW_BA"
                                    value="{$val('Cant_Lic_LSOFTW_BA', 0)}" min="0" style="text-align: right;">
                         </div>
                         <div class="input-group">
                             <label for="Cant_Lic_LSOFTW_RP">Mód. Nómina</label>
                             <input type="number" id="Cant_Lic_LSOFTW_RP" name="Cant_Lic_LSOFTW_RP"
                                    value="{$val('Cant_Lic_LSOFTW_RP', 0)}" min="0" style="text-align: right;">
                         </div>
                         <div class="input-group">
                             <label for="Cant_Lic_LSOFTW_AF">Mód. Activos Fijos</label>
                             <input type="number" id="Cant_Lic_LSOFTW_AF" name="Cant_Lic_LSOFTW_AF"
                                    value="{$val('Cant_Lic_LSOFTW_AF', 0)}" min="0" style="text-align: right;">
                         </div>
                         <div class="input-group">
                             <label for="Cant_Lic_LSOFTW_OP">Mód. Producción</label>
                             <input type="number" id="Cant_Lic_LSOFTW_OP" name="Cant_Lic_LSOFTW_OP"
                                    value="{$val('Cant_Lic_LSOFTW_OP', 0)}" min="0" style="text-align: right;">
                         </div>
                    </div>
                </div>

                <div id="tab-sistema" class="tab-content">
                     <div class="form-grid">
                        <div class="input-group"><label for="Codigo">Código</label><input type="text" id="Codigo" value="{$val('Codigo')}" disabled></div>
                        <div class="input-group"><label for="Alta">Fecha de Alta</label><input type="text" id="Alta" value="{$val('Alta')}" readonly></div>
                        <div class="input-group"><label for="Cant_Ingresos">Cantidad de Ingresos</label><input type="number" id="Cant_Ingresos" value="{$val('Cant_Ingresos')}" disabled></div>
                    </div>
                </div>
            </form>
            <div class="form-actions">
                <button type="submit" form="empresa-form" class="btn-submit">Guardar Cambios</button>
HTML;

    if ($esEdicion && usuarioPuedeEliminarEmpresa()) {
        $rucSeguro = htmlspecialchars($Ruc, ENT_QUOTES, 'UTF-8');
        $confirmarJS = "return confirm('¿Está SEGURO de que desea ELIMINAR esta empresa? Esta acción es irreversible y solo se puede hacer si no tiene licencias activas.');";
        echo "<form action='{$actionUrl}?Ruc={$rucSeguro}' method='POST' style='display:inline-block; margin-left:10px;'>
                  <input type='hidden' name='Accion' value='Procesar_Eliminar_Empresa'>
                  <button type='submit' onclick=\"{$confirmarJS}\"
                          style='background-color:#d9534f; color:white; border:none; padding:12px 20px; border-radius:5px; cursor:pointer; font-size:1.1em;'>
                      Eliminar Empresa
                  </button>
              </form>";
    }
    // CAMBIO: El href del botón "Cancelar" ahora usa la URL dinámica
    echo <<<HTML
                <a href="{$urlCancelar}" class="btn-cancel">Cancelar</a>
            </div>
        </div>
    </div>    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.tab-nav-link');
            const tabContents = document.querySelectorAll('.tab-content');
            tabLinks.forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const tabId = this.getAttribute('data-tab');
                    tabLinks.forEach(l => l.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
    </body></html>
HTML;
    exit;
}

/**
 * Procesa el formulario de alta o edición de empresa.
 * Valida datos, maneja errores y redirige según el resultado.
 * Depende de: contarLicenciasUsadas() (que está en Consultar.php)
 */
function procesarFormularioEmpresa(mysqli $conn, ?string $RucEdicion): void {
    
    // Estandarización de nombres de campos
    $campos_a_procesar = [
        'RUC', 'Nombre', 'Telefono', 'Ciudad', 'eMail', 'eMail2', 'Comentario',
        'Inicio_Suscripcion', 'Fin_Suscripcion', 'En_Lista_Negra', 'Motivo_Lista_Negra',
        'Version_FSoft', 'Version_LSoft', 'Version_LSoft_Web',
        'Cant_Lic_FSOFT_BA', 'Cant_Lic_FSOFT_RP', 'Cant_Lic_FSOFT_CE', 
        'Cant_Lic_LSOFT_BA', 'Cant_Lic_LSOFT_RP', 'Cant_Lic_LSOFT_CE', 'Cant_Lic_LSOFT_AF', 'Cant_Lic_LSOFT_OP',
        'Cant_Lic_LSOFTW_BA', 'Cant_Lic_LSOFTW_RP', 'Cant_Lic_LSOFTW_AF', 'Cant_Lic_LSOFTW_OP'
    ];
    
    $datos = [];
    foreach ($campos_a_procesar as $campo) {
        if (strpos($campo, 'Cant_Lic_') === 0) {
            $datos[$campo] = (int)($_POST[$campo] ?? 0);
        } else {
            $datos[$campo] = $_POST[$campo] ?? null;
        }
    }

    $esEdicion = $RucEdicion !== null;

    // VALIDACIÓN: Validar RUC duplicado solo en modo ALTA
    if (!$esEdicion) {
        $ruc_a_validar = $datos['RUC'];
        if (empty($ruc_a_validar)) {
            $_SESSION['form_data_temp'] = $_POST;
            $_SESSION['form_error_temp'] = "El campo RUC es obligatorio.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Alta_Empresa');
            exit;
        }

        $stmt_check = $conn->prepare("SELECT 1 FROM Empresas WHERE RUC = ?");
        $stmt_check->bind_param("s", $ruc_a_validar);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            // RUC duplicado encontrado
            $_SESSION['form_data_temp'] = $_POST; // Guardar todos los datos del POST
            $_SESSION['form_error_temp'] = "El RUC '{$ruc_a_validar}' ya está registrado. Por favor, ingrese uno diferente.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Alta_Empresa');
            exit;
        }
    }

    if ($esEdicion) {
        // --- LÓGICA DE UPDATE ---
        $datos['RUC'] = $RucEdicion;
        $set_parts = [];
        $params = [];
        $types = '';
        $campos_para_set = array_diff($campos_a_procesar, ['RUC']);
        foreach ($campos_para_set as $campo) {
            $set_parts[] = "`$campo` = ?";
            $params[] = &$datos[$campo];
            $types .= (strpos($campo, 'Cant_Lic_') === 0 || strpos($campo, 'En_Lista_Negra') !== false) ? 'i' : 's';
        }
        $sql = "UPDATE Empresas SET " . implode(', ', $set_parts) . " WHERE RUC = ?";
        $types .= 's';
        $params[] = &$RucEdicion;

    } else {
        // --- LÓGICA DE INSERT ---
        $datos['Alta'] = date("Y-m-d H:i:s");
        $campos_para_insert = array_merge($campos_a_procesar, ['Alta']);
        $placeholders = implode(', ', array_fill(0, count($campos_para_insert), '?'));
        $columnas = implode('`, `', $campos_para_insert);
        $sql = "INSERT INTO Empresas (`$columnas`) VALUES ($placeholders)";
        $params = [];
        $types = '';
        foreach ($campos_para_insert as $campo) {
            $params[] = &$datos[$campo];
            $types .= (strpos($campo, 'Cant_Lic_') === 0 || strpos($campo, 'En_Lista_Negra') !== false) ? 'i' : 's';
        }
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) { die("Error al preparar la sentencia: " . $conn->error); }
    
    // bind_param necesita referencias.
    $stmt->bind_param($types, ...array_values($params));

    if ($stmt->execute()) {
        // CAMBIO: Simplificado el mensaje de éxito
        $accionRealizada = $esEdicion ? "actualizado" : "creado";
        $_SESSION['mensaje_flash'] = "La empresa se ha {$accionRealizada} correctamente.";
        
        $rucFinal = $esEdicion ? $RucEdicion : $datos['RUC'];
        $_SESSION['Ruc'] = $rucFinal;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Administrar&Ruc=' . urlencode($rucFinal));
    } else {
        die("Error al ejecutar la operación: " . $stmt->error);
    }
    exit;
}

/**
 * Procesa la búsqueda en tiempo real de empresas.
 * Retorna resultados en formato JSON para AJAX.
 */
function procesarBusquedaLive(mysqli $conn): void {
    header('Content-Type: application/json');
    $terminoBusqueda = $_GET['q'] ?? '';
    if (strlen($terminoBusqueda) < 2) {
        echo json_encode([]);
        exit;
    }
    $empresas = [];
    $param = "%" . $terminoBusqueda . "%";
    $sql = "SELECT Nombre, RUC, Ciudad, Telefono, Alta, Fin_Suscripcion 
            FROM Empresas 
            WHERE Nombre LIKE ? OR RUC LIKE ?
            ORDER BY Nombre ASC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $param, $param);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['Alta'] = $row['Alta'] ? date('d/m/Y', strtotime($row['Alta'])) : 'N/A';
            $row['Fin_Suscripcion'] = $row['Fin_Suscripcion'] ? date('d/m/Y', strtotime($row['Fin_Suscripcion'])) : 'N/A';
            $empresas[] = $row;
        }
        $stmt->close();
    }
    echo json_encode($empresas);
    exit;
}

/**
 * Renderiza el widget de búsqueda de empresas con botón de nueva empresa.
 * Depende de: usuarioPuedeCrearEmpresa() (está en permissions.php)
 */
function renderizarWidgetBusquedaYComando(): string {
    $botonNuevaEmpresa = '';
    if (usuarioPuedeCrearEmpresa()) {
        $botonNuevaEmpresa = "<a href='?Accion=Alta_Empresa'
                                 style='background-color: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; white-space: nowrap;'>
                                 Nueva Empresa
                               </a>";
    }
 return <<<HTML
 <div class="widget-busqueda"
      style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ddd;">
   <div style="
        display: flex;
        justify-content: left;
        align-items: flex-end;
        gap: 10px;">
     <div>
       <label for="campo-busqueda-nombre"
              style="font-weight: bold; display: block; margin-bottom: 5px;">
         Buscar Empresa (por Nombre o RUC)
       </label>
       <input
         type="text"
         id="campo-busqueda-nombre"
         placeholder="Escriba el nombre o RUC de la empresa..."
         style="width: 400px; padding: 12px; font-size: 1.1em; border: 1px solid #ccc; border-radius: 4px;"
       >
     </div>
     {$botonNuevaEmpresa}
   </div>
   <div id="resultados-busqueda" style="margin-top: 15px;"></div>
 </div>
HTML;
}

/**
 * Renderiza el script JavaScript para la búsqueda en tiempo real.
 * Maneja debounce, AJAX y renderizado de resultados.
 */
function renderizarScriptBusqueda(): string {
    return <<<JS
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const campoBusqueda = document.getElementById('campo-busqueda-nombre');
            const contenedorResultados = document.getElementById('resultados-busqueda');
            let debounceTimer;
            campoBusqueda.addEventListener('keyup', function() {
                clearTimeout(debounceTimer);
                const termino = campoBusqueda.value.trim();
                if (termino.length < 2) {
                    contenedorResultados.innerHTML = '';
                    return;
                }
                debounceTimer = setTimeout(() => {
                    contenedorResultados.innerHTML = '<p>Buscando...</p>';
                    fetch(`?Accion=Buscar_Empresas_Live&q=\${encodeURIComponent(termino)}`)
                        .then(response => {
                            if (!response.ok) { throw new Error('Error en la red o en el servidor'); }
                            return response.json();
                        })
                        .then(empresas => {
                            contenedorResultados.innerHTML = '';
                            if (empresas.length === 0) {
                                contenedorResultados.innerHTML = '<p style="color: #6c757d;">No se encontraron empresas.</p>';
                                return;
                            }
                            const tabla = document.createElement('table');
                            tabla.style.width = '100%';
                            tabla.style.borderCollapse = 'collapse';
                            tabla.innerHTML = `
                                <thead style="background-color: #e9ecef;">
                                    <tr>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Nombre</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">RUC</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Ciudad</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Teléfono</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Alta</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Fin Suscripción</th>
                                        <th style="padding: 8px; border: 1px solid #dee2e6;">Acción</th>
                                    </tr>
                                </thead>`;
                            const tbody = document.createElement('tbody');
                            empresas.forEach(empresa => {
                                const fila = document.createElement('tr');
                                fila.innerHTML = `
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Nombre}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.RUC}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Ciudad || '-'}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Telefono || '-'}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Alta}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">\${empresa.Fin_Suscripcion}</td>
                                    <td style="padding: 8px; border: 1px solid #dee2e6;">
                                        <a href="?Accion=Administrar&Ruc=\${empresa.RUC}" style="background-color: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px;">Administrar</a>
                                    </td>`;
                                tbody.appendChild(fila);
                            });
                            tabla.appendChild(tbody);
                            contenedorResultados.appendChild(tabla);
                        })
                        .catch(error => {
                            console.error('Error en la búsqueda:', error);
                            contenedorResultados.innerHTML = '<p style="color: red;">Ocurrió un error al realizar la búsqueda.</p>';
                        });
                }, 300);
            });
        });
    </script>
JS;
}

/**
 * Procesa la eliminación de una empresa.
 * Verifica que no tenga licencias activas antes de eliminar.
 * Depende de: contarLicenciasUsadas() (que está en Consultar.php)
 */
function procesarEliminarEmpresa(mysqli $conn, string $Ruc): void {
    // 1. Verificar si la empresa tiene licencias activas
    $licenciasCount = contarLicenciasUsadas($conn, $Ruc);
    if ($licenciasCount > 0) {
        // No se puede eliminar, redirigir con mensaje de error
        $_SESSION['mensaje_flash_error'] = "No se puede eliminar la empresa porque tiene {$licenciasCount} licencia(s) registrada(s).";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Editar_Empresa&Ruc=' . urlencode($Ruc));
        exit;
    }

    // 2. Proceder con la eliminación
    $stmt = $conn->prepare("DELETE FROM Empresas WHERE Ruc = ?");
    $stmt->bind_param("s", $Ruc);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje_flash'] = "La empresa con RUC {$Ruc} ha sido eliminada correctamente.";
        unset($_SESSION['Ruc']); // Limpiar el RUC de la sesión
        header('Location: ' . $_SERVER['PHP_SELF']); // Redirigir al dashboard
    } else {
        $_SESSION['mensaje_flash_error'] = "Ocurrió un error al intentar eliminar la empresa: " . $stmt->error;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?Accion=Editar_Empresa&Ruc=' . urlencode($Ruc));
    }
    exit;
}

?> 