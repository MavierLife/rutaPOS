<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar Pedidos - RutaPOS</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/exportar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <button class="btn-back" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h1>Exportar Pedidos</h1>
            </div>
            <div class="header-right">
                <span class="user-info"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></span>
                <button class="btn-logout" onclick="window.location.href='php/auth/logout.php'">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Stats Card -->
            <div class="stats-card">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" id="pedidosFinalizados">-</div>
                        <div class="stat-label">Pedidos Finalizados</div>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" id="ultimaExportacion">-</div>
                        <div class="stat-label">Última Exportación</div>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <div class="export-card">
                <div class="card-header">
                    <h2><i class="fas fa-download"></i> Exportar Pedidos</h2>
                </div>
                <div class="card-body">
                    <div class="export-option single-option">
                        <div class="option-info">
                            <h3>Exportar Todos los Pedidos Finalizados</h3>
                            <p>Genera un archivo .txt con todos los pedidos marcados como finalizados (OK = 1)</p>
                        </div>
                        <button class="btn-export btn-export-large" id="btnExportarTodos" onclick="exportarPedidos()">
                            <i class="fas fa-file-export"></i>
                            Exportar Pedidos
                        </button>
                    </div>
                </div>
            </div>

            <!-- Progress Card -->
            <div class="progress-card" id="progressCard" style="display: none;">
                <div class="card-header">
                    <h2><i class="fas fa-spinner fa-spin"></i> Procesando Exportación</h2>
                </div>
                <div class="card-body">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text" id="progressText">Iniciando exportación...</div>
                </div>
            </div>

            <!-- Results Card -->
            <div class="results-card" id="resultsCard" style="display: none;">
                <div class="card-header">
                    <h2><i class="fas fa-check-circle"></i> Exportación Completada</h2>
                </div>
                <div class="card-body">
                    <div class="result-info">
                        <p><strong>Archivo generado:</strong> <span id="nombreArchivo"></span></p>
                        <p><strong>Registros exportados:</strong> <span id="totalRegistros"></span></p>
                        <p><strong>Tamaño del archivo:</strong> <span id="tamanoArchivo"></span></p>
                    </div>
                    <div class="result-actions">
                        <button class="btn-download" id="btnDescargar">
                            <i class="fas fa-download"></i>
                            Descargar Archivo
                        </button>
                        <button class="btn-secondary" onclick="resetExportacion()">
                            <i class="fas fa-redo"></i>
                            Nueva Exportación
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Cargando estadísticas...</p>
        </div>
    </div>

    <script src="js/exportar.js?v=<?php echo time(); ?>"></script>
</body>
</html>