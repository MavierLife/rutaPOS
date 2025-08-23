<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

// Configurar zona horaria de El Salvador
date_default_timezone_set('America/El_Salvador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MovilPOS Preventa - Lista de Pedidos</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/pedidos.css">
    <link rel="stylesheet" href="css/lista-pedidos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <header class="header">
        <span>MovilPOS Preventa</span>
    </header>
    
    <main class="main-container">
        <div class="lista-pedidos-container">
            <!-- Sección Pedidos -->
            <div class="pedidos-section">
                <i class="fas fa-shopping-basket"></i>
                <h2>Pedidos</h2>
            </div>
            
            <!-- Operador y Vendedor -->
            <div class="operador-vendedor">
                <p class="operador-text">OPERADOR</p>
                <p class="vendedor-text"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'VENDEDOR 001'); ?></p>
            </div>
            
            <!-- Botones de Totales -->
            <div class="botones-totales">
                <button class="btn-total" id="btnContado">
                    <span class="total-label">Contado</span>
                    <span class="total-amount" id="totalContado">0.00</span>
                </button>
                <button class="btn-total" id="btnCredito">
                    <span class="total-label">Crédito</span>
                    <span class="total-amount" id="totalCredito">0.00</span>
                </button>
                <button class="btn-total active" id="btnPreventa">
                    <span class="total-label">Preventa</span>
                    <span class="total-amount" id="totalPreventa">0.00</span>
                </button>
            </div>
            
            <!-- Tabla de Pedidos -->
            <div class="tabla-pedidos">
                <div class="tabla-header">
                    <div class="col-cliente">CLIENTE</div>
                    <div class="col-contado">CONTADO</div>
                    <div class="col-credito">CRÉDITO</div>
                    <div class="col-opciones">Opciones</div>
                </div>
                
                <div id="listaPedidos" class="loading">
                    Cargando pedidos...
                </div>
            </div>
            
            <!-- Contador de Registros -->
            <div class="contador-registros">
                Registro(s): <span id="contadorRegistros">0</span>
            </div>
            
            <!-- Botones Inferiores -->
            <div class="botones-inferiores">
                <button class="btn-enviar" onclick="enviarPedidosSeleccionados()">
                    Enviar pedidos Seleccionados
                </button>
                <button class="btn-backup" onclick="backupPedidos()">
                    Backup de Pedidos
                </button>
            </div>
            
            <button class="btn-cerrar" onclick="window.location.href='dashboard.php'">Cerrar</button>
        </div>
    </main>
    
    <script src="js/lista-pedidos.js"></script>
</body>
</html>