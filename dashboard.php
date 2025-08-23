<?php
// Configurar zona horaria de El Salvador
date_default_timezone_set('America/El_Salvador');

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}
// Establecer la configuración regional a español para el nombre del día
setlocale(LC_TIME, 'es_ES.UTF-8', 'Spanish_Spain', 'Spanish');
$dayOfWeek = strtoupper(strftime('%A'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelenSystem Preventa - [ <?php echo isset($_SESSION['equipo_asignado']) && !empty($_SESSION['equipo_asignado']) ? htmlspecialchars($_SESSION['equipo_asignado']) : 'SISTEMA'; ?> ]</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="header dashboard-header">
        <span>HelenSystem Preventa - [ <?php echo isset($_SESSION['equipo_asignado']) && !empty($_SESSION['equipo_asignado']) ? htmlspecialchars($_SESSION['equipo_asignado']) : 'SISTEMA'; ?> ]</span>
        <span class="hamburger-menu"><i class="fas fa-bars"></i></span>
    </header>
    <main class="main-container">
        <div class="dashboard-container">

            <div class="logo dashboard-logo">
                <img src="assets/logo/logo.png" alt="RutaPOS Logo" class="dashboard-logo-img">
            </div>

            <div class="info-box operator">
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
            <div class="info-box day">
                <?php echo $dayOfWeek; ?>
            </div>

            <div class="menu-grid">
                <button class="menu-button" onclick="window.location.href='rutas.php'"><i class="icon fas fa-route"></i> Rutas</button>
                <button class="menu-button" onclick="window.location.href='clientes.php'"><i class="icon fas fa-users"></i> Clientes</button>
                <button class="menu-button" onclick="window.location.href='lista-pedidos.php'"><i class="icon fas fa-shopping-basket"></i> Pedidos</button>
                <button class="menu-button"><i class="icon fas fa-dollar-sign"></i> Cuentas</button>
                <button class="menu-button"><i class="icon fas fa-box-open"></i> Productos</button>
                <button class="menu-button"><i class="icon fas fa-paper-plane"></i> Enviar</button>
            </div>

            <div class="sync-section">
                <button class="sync-button"><i class="icon fas fa-sync-alt"></i> Sincronizar</button>
                <p class="last-sync">Ultima Sincronización<br>7/5/2024 7:12:01 a. m.</p>
            </div>

            <div class="power-button">
                <!-- Reemplaza esta URL con la ruta a tu icono local -->
                <a href="php/auth/logout.php"><img src="https://i.imgur.com/E55w4O4.png" alt="Salir"></a>
            </div>
        </div>
    </main>
</body>
</html>