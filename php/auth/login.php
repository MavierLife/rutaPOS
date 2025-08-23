<?php
// Configurar zona horaria de El Salvador
date_default_timezone_set('America/El_Salvador');

session_start();
require '../config/database.php';  // Asume que tienes un archivo de conexión

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $clave = $_POST['clave'];

    $stmt = $pdo->prepare("SELECT * FROM tblregistrodeempleados WHERE IDEmpleado = ? AND ClaveAcceso = ? AND Estado = '1'");
    $stmt->execute([$usuario, $clave]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['IDEmpleado'];
        $_SESSION['user_type'] = $user['TipoEmpleado'];
        $_SESSION['user_name'] = $user['Nombres'] . ' ' . $user['Apellidos'];  // Almacena el nombre completo
        
        // Consultar equipo asignado en tblconfiguraciones
        $stmt_config = $pdo->prepare("SELECT EquipoAsigna FROM tblconfiguraciones WHERE UsuarioAsigna = ?");
        $stmt_config->execute([$user['IDEmpleado']]);
        $config = $stmt_config->fetch();
        
        if ($config && !empty($config['EquipoAsigna'])) {
            $_SESSION['equipo_asignado'] = $config['EquipoAsigna'];
        } else {
            $_SESSION['equipo_asignado'] = null;
        }
        
        header("Location: ../../dashboard.php");  // Cambiado a .php
        exit;
    } else {
        header("Location: ../../index.html?error=Credenciales incorrectas");
        exit;
    }
}
?>