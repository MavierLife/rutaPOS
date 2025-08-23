<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

// Inicializar variables
$clienteCodigo = '';
$clienteNombre = '';
$clienteEstablecimiento = '';
$clienteTipoDocumento = '';
$clienteCreditoAutorizado = false;
$clientePlazoEstablecido = 0;
$clienteVenderPrecioEspecial = 0;
$pedidoId = '';
$modoEdicion = false;

require_once 'php/config/database.php';

// Verificar si se está editando un pedido existente
if (isset($_GET['editar']) && !empty($_GET['editar'])) {
    $modoEdicion = true;
    $pedidoId = $_GET['editar'];
    
    try {
        // Buscar el pedido en la base de datos
        $stmt = $pdo->prepare("SELECT CodigoCli FROM tblregistrodepedidos WHERE CodigoSIN = ?");
        $stmt->execute([$pedidoId]);
        $pedidoData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pedidoData) {
            $clienteCodigo = $pedidoData['CodigoCli'];
            
            // Obtener datos completos del cliente
            $stmt = $pdo->prepare("SELECT Nombre, Establecimiento, TipoDocumentoEstablecido, CreditoAutorizado, PlazoEstablecido, venderprecioespecial FROM tblcatalogodeclientes WHERE CodigoCli = ?");
            $stmt->execute([$clienteCodigo]);
            $clienteData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clienteData) {
                $clienteNombre = $clienteData['Nombre'] ?? '';
                $clienteEstablecimiento = $clienteData['Establecimiento'] ?? '';
                $clienteTipoDocumento = $clienteData['TipoDocumentoEstablecido'] ?? '';
                $clienteCreditoAutorizado = ($clienteData['CreditoAutorizado'] === 'TRUE' || $clienteData['CreditoAutorizado'] === '1' || $clienteData['CreditoAutorizado'] === 1);
                $clientePlazoEstablecido = (int)($clienteData['PlazoEstablecido'] ?? 0);
                $clienteVenderPrecioEspecial = (int)($clienteData['venderprecioespecial'] ?? 0);
            }
        }
    } catch (PDOException $e) {
        error_log("Error al obtener datos del pedido para edición: " . $e->getMessage());
    }
    
    // En modo edición, el pedido ya existe
    $yaExisteSesion = true;
} else {
    // Modo creación: obtener datos del cliente desde parámetros GET
    $clienteCodigo = $_GET['codigo'] ?? '';
    $clienteNombre = $_GET['nombre'] ?? '';
    $clienteEstablecimiento = $_GET['establecimiento'] ?? '';
    
    // Obtener datos adicionales del cliente desde la base de datos
    if (!empty($clienteCodigo)) {
        try {
            $stmt = $pdo->prepare("SELECT TipoDocumentoEstablecido, CreditoAutorizado, PlazoEstablecido, venderprecioespecial FROM tblcatalogodeclientes WHERE CodigoCli = ?");
            $stmt->execute([$clienteCodigo]);
            $clienteData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clienteData) {
                $clienteTipoDocumento = $clienteData['TipoDocumentoEstablecido'] ?? '';
                $clienteCreditoAutorizado = ($clienteData['CreditoAutorizado'] === 'TRUE' || $clienteData['CreditoAutorizado'] === '1' || $clienteData['CreditoAutorizado'] === 1);
                $clientePlazoEstablecido = (int)($clienteData['PlazoEstablecido'] ?? 0);
                $clienteVenderPrecioEspecial = (int)($clienteData['venderprecioespecial'] ?? 0);
            }
        } catch (PDOException $e) {
            error_log("Error al obtener datos del cliente: " . $e->getMessage());
        }
    }
    
    // Control de sesión para evitar duplicación de pedidos
    // Detectar si es un nuevo pedido
    $esNuevoPedido = isset($_GET['nuevo']) && $_GET['nuevo'] == '1';
    $clienteCambio = isset($_SESSION['pedido_cliente_actual']) && $_SESSION['pedido_cliente_actual'] != $clienteCodigo;
    
    // Debug logging
    error_log("=== DEBUG PEDIDOS ===");

    error_log("esNuevoPedido: " . ($esNuevoPedido ? 'true' : 'false'));
    error_log("clienteCambio: " . ($clienteCambio ? 'true' : 'false'));
    error_log("pedido_id_actual en sesión: " . (isset($_SESSION['pedido_id_actual']) ? $_SESSION['pedido_id_actual'] : 'NO EXISTE'));
    error_log("pedido_cliente_actual en sesión: " . (isset($_SESSION['pedido_cliente_actual']) ? $_SESSION['pedido_cliente_actual'] : 'NO EXISTE'));
    error_log("clienteCodigo actual: " . $clienteCodigo);
    
    // NUEVA LÓGICA: Solo generar nuevo ID si 'nuevo=1' está explícitamente presente
    // o si el cliente cambió. Si no hay sesión y no es nuevo=1, redirigir.
    // EXCEPCIÓN: No redirigir si se está accediendo con parámetros de cliente válidos desde 'Rutas'
    $tieneParametrosCliente = !empty($clienteCodigo) && !empty($clienteNombre);
    
    if (!$esNuevoPedido && !isset($_SESSION['pedido_id_actual']) && !$tieneParametrosCliente) {
        // No es un pedido nuevo, no hay sesión previa y no tiene parámetros de cliente - redirigir
        error_log("❌ ACCESO INVÁLIDO: No es nuevo pedido, no hay sesión previa y no hay parámetros de cliente");
        header('Location: clientes.php');
        exit();
    }
    
    // Verificar si necesitamos generar un nuevo ID
    // Solo generar nuevo ID si es nuevo pedido Y no existe sesión previa, o si cambió el cliente
    $necesitaNuevoId = ($esNuevoPedido && !isset($_SESSION['pedido_id_actual'])) || $clienteCambio;
    
    if ($necesitaNuevoId) {
        // Generar CodigoSIN siguiendo el formato original del sistema VBA
        // Formato: NoTerminal + Año(4) + Mes(00) + Día(00) + Hora + Minuto + Segundo
        $noTerminal = isset($_SESSION['equipo_asignado']) && !empty($_SESSION['equipo_asignado']) ? $_SESSION['equipo_asignado'] : 'EQ01';
        $pedidoId = $noTerminal . date('Y') . date('m') . date('d') . date('H') . date('i') . date('s');
        
        // Guardar en sesión para evitar regeneración
        $_SESSION['pedido_id_actual'] = $pedidoId;
        $_SESSION['pedido_cliente_actual'] = $clienteCodigo;
        
        error_log("GENERANDO NUEVO ID: " . $pedidoId);
    } else {
        // Usar el ID existente de la sesión
        $pedidoId = $_SESSION['pedido_id_actual'];
        error_log("USANDO ID EXISTENTE: " . $pedidoId);
    }
    
    error_log("=== FIN DEBUG PEDIDOS ===");
    
    // Variable para pasar al JavaScript - distinguir entre sesión nueva y recarga
    // Considerar que "ya existe sesión" si hay un pedido_id_actual Y no es un nuevo pedido
    $yaExisteSesion = isset($_SESSION['pedido_id_actual']) && !$esNuevoPedido;
    
    // Si es un nuevo pedido, limpiar el flag de registro inicial para permitir el registro
    if ($esNuevoPedido) {
        unset($_SESSION['pedido_registrado_inicialmente']);
        $yaExisteSesion = false; // Permitir registro para pedidos nuevos
        error_log("NUEVO PEDIDO: Limpiando flag de registro inicial");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelenSystem Preventa - Registro de Pedidos</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/pedidos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/pedidos-modal.css">
    <link rel="stylesheet" href="css/inventory-styles.css">
</head>
<body>
    <header class="header">
        <span>MovilPOS Preventa</span>
    </header>
    
    <main class="main-container">
        <div class="pedidos-container">
            <!-- Header del Registro de Pedidos -->
            <div class="pedidos-header">
                <h2>REGISTRO DE PEDIDOS</h2>
            </div>
            
            <!-- Información del Pedido y Cliente -->
            <div class="pedido-info">
                <div class="pedido-id-section">
                    <div class="id-container">
                        <span class="id-label">ID:</span>
                        <span class="id-value" id="pedidoId"><?php echo $pedidoId; ?></span>
                    </div>
                    <div class="pedido-actions">
                        <button class="btn-finalizar" onclick="finalizarPedido()">Finalizar</button>
                        <button class="btn-cerrar" onclick="window.history.back();">×</button>
                    </div>
                </div>
                
                <div class="cliente-section">
                    <div class="cliente-header">
                        <div class="cliente-info">
                            <span class="cliente-label">Cliente:</span>
                            <span class="cliente-codigo" id="clienteCodigo"><?php echo htmlspecialchars($clienteCodigo); ?></span>
                        </div>
                    </div>
                    <div class="cliente-nombre" id="clienteNombre"><?php echo htmlspecialchars($clienteNombre . ' ' . $clienteEstablecimiento); ?></div>
                </div>
                
                <div class="tipo-pago-section">
                    <div class="tipo-pago-tabs">
                        <button class="btn-tipo-pago active" data-tipo="contado" onclick="cambiarTipoPago('contado')">Contado</button>
                        <button class="btn-tipo-pago<?php echo !$clienteCreditoAutorizado ? ' disabled' : ''; ?>" data-tipo="credito" onclick="cambiarTipoPago('credito')"<?php echo !$clienteCreditoAutorizado ? ' disabled' : ''; ?>>Crédito</button>
                    </div>
                    <input type="number" id="diasCredito" placeholder="Días (máx: <?php echo $clientePlazoEstablecido; ?>)" class="dias-credito" style="display: none;" max="<?php echo $clientePlazoEstablecido; ?>">
                </div>
                
                <div class="total-section">
                    <div class="total-container">
                        <div class="total-display">
                            <span class="total-value" id="totalPedido">0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Productos -->
            <div class="productos-section">
                <div class="productos-header">
                    <div class="col-descripcion">DESCRIPCION</div>
                    <div class="col-cantidad">CAN</div>
                    <div class="col-precio">PRECIO</div>
                    <div class="col-importe">IMPORTE</div>
                </div>
                
                <div class="productos-list" id="productosList">
                    <!-- Los productos se agregarán dinámicamente aquí -->
                </div>
                
                <div class="add-producto-section">
                    <button class="btn-add-producto" onclick="mostrarAgregarProducto()">+ Agregar Producto</button>
                </div>
            </div>
            
            <!-- Campo de Autorización -->
            <div class="autorizacion-section">
                <label for="autorizacion">Autorización:</label>
                <input type="text" id="autorizacion" class="autorizacion-input" placeholder="Ingrese autorización...">
            </div>
            
            <!-- Área de comentarios/notas -->
            <div class="notas-section">
                <textarea id="notas" class="notas-textarea" placeholder="Notas adicionales..."></textarea>
            </div>
        </div>
    </main>
    
    <!-- Modal de Selección de Productos -->
    <div id="productosModal" class="productos-modal-overlay" style="display: none;">
        <div class="productos-modal-content">
            <!-- Header del Modal -->
            <div class="productos-modal-header">
                <div class="productos-header-content">
                    <i class="fas fa-boxes productos-icon"></i>
                    <h2>Productos</h2>
                </div>
                <button class="btn-close-modal" onclick="cerrarModalProductos()">✕</button>
            </div>
            
            <!-- Barra de Búsqueda -->
            <div class="productos-search-section">
                <input type="text" id="productosSearch" placeholder="Buscar producto...">
                <button class="btn-buscar" onclick="buscarProductos()">Buscar</button>
            </div>
            
            <!-- Pestañas -->
            <div class="productos-tabs">
                <button class="tab-btn active" data-filter="productos">Productos</button>
                <button class="tab-btn" data-filter="ofertas">Ofertas</button>
            </div>
            
            <!-- Contenido Principal -->
            <div class="productos-main-content">
                <!-- Lista de Productos -->
                <div class="productos-list-container full-width">
                    <div id="productosListModal" class="productos-list-modal">
                        <div class="loading">Cargando productos...</div>
                    </div>
                </div>
            </div>
            
            <!-- Footer del Modal -->
            <div class="productos-modal-footer">
                <div class="registro-count">
                    <span>Registro(s): <span id="productosCount">0</span></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para Agregar Producto -->
    <div id="agregarProductoModal" class="modal" style="display: none;">
        <div class="modal-content-agregar">
            <div class="modal-header-agregar">
                <h3>AGREGAR</h3>
            </div>
            
            <div class="producto-nombre-header" id="productoTitulo">
                PRODUCTO SELECCIONADO
            </div>
            <div class="modal-body-agregar">
                <!-- Columna izquierda: Botones de precio -->
                <div class="precio-buttons-column">
                    <button type="button" class="precio-btn" data-tipo="especial" id="btnEspecial">ESPECIAL</button>
                    <button type="button" class="precio-btn precio-btn-active" data-tipo="mayoreo">MAYOREO</button>
                    <button type="button" class="precio-btn" data-tipo="detalle">DETALLE</button>
                    <button type="button" class="precio-btn modo-toggle" data-tipo="unidad" id="btnModoUnidad">UNIDAD</button>
                </div>
                
                <!-- Columna derecha: Información del producto -->
                <div class="producto-info-column">
                    <div class="info-row">
                        <label>STOCK:</label>
                        <div class="stock-info">
                            <span class="unidades-minimas" id="unidadesMinimas">1</span>
                            <span class="stock-separator">|</span>
                            <span class="stock-minimo" id="stockMinimo">14</span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <label>PRECIO:</label>
                        <div class="precio-valor" id="precioActual">4.250</div>
                    </div>
                    
                    <div class="info-row">
                        <label>CANTIDAD:</label>
                        <div class="cantidad-controls">
                            <button type="button" class="cantidad-btn" onclick="cambiarCantidad(-1)">-</button>
                            <input type="number" id="cantidadInput" value="1" min="1" onchange="validarYCalcularTotal()">
                            <button type="button" class="cantidad-btn" onclick="cambiarCantidad(1)">+</button>
                        </div>
                    </div>
                    
                    <div class="info-row modo-indicator">
                        <label>MODO:</label>
                        <div class="modo-texto" id="modoTexto">Fardos</div>
                    </div>
                    

                    
                    <div class="info-row total-row">
                        <label>TOTAL:</label>
                        <div class="total-valor" id="totalModal">4.25</div>
                    </div>
                </div>
            </div>
            

            
            <!-- Botones de acción -->
            <div class="modal-actions">
                <button type="button" class="btn-confirmar" onclick="confirmarAgregarProducto()">✓</button>
                <button type="button" class="btn-cancelar" onclick="cerrarModalAgregar()">✗</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Confirmación para Eliminar Producto -->
    <div id="eliminarProductoModal" class="modal" style="display: none;">
        <div class="modal-content-eliminar">
            <div class="modal-header-eliminar">
                <h3>CONFIRMAR ELIMINACIÓN</h3>
            </div>
            
            <div class="modal-body-eliminar">
                <div class="eliminar-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="eliminar-mensaje">
                    <p>¿Está seguro que desea eliminar este producto?</p>
                    <p class="producto-eliminar-nombre" id="productoEliminarNombre"></p>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="modal-actions-eliminar">
                <button type="button" class="btn-confirmar-eliminar" onclick="confirmarEliminarProducto()">SÍ</button>
                <button type="button" class="btn-cancelar-eliminar" onclick="cerrarModalEliminar()">NO</button>
            </div>
        </div>
    </div>


    
    <script>
        // Inicializar datos del cliente desde PHP
        clienteData = {
            codigo: '<?php echo htmlspecialchars($clienteCodigo); ?>',
            nombre: '<?php echo htmlspecialchars($clienteNombre); ?>',
            establecimiento: '<?php echo htmlspecialchars($clienteEstablecimiento); ?>',
            tipoDocumento: '<?php echo htmlspecialchars($clienteTipoDocumento); ?>',
            creditoAutorizado: <?php echo $clienteCreditoAutorizado ? 'true' : 'false'; ?>,
            plazoEstablecido: <?php echo $clientePlazoEstablecido; ?>,
            venderprecioespecial: <?php echo isset($clienteVenderPrecioEspecial) ? $clienteVenderPrecioEspecial : 0; ?>
        };
        
        pedidoId = '<?php echo $pedidoId; ?>';
        
        // Variables de modo de edición
        window.modoEdicion = <?php echo $modoEdicion ? 'true' : 'false'; ?>;
    </script>
    
    <!-- Archivos JavaScript separados -->
    <script src="js/pedidos.js"></script>
    <script src="js/pedidos-swipe.js"></script>
    <script src="js/pedidos-finalizacion.js"></script>
    
    <script>
        // Cargar productos al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== DOM CONTENT LOADED ===');
            console.log('clienteData disponible:', clienteData);
            console.log('modoEdicion:', <?php echo $modoEdicion ? 'true' : 'false'; ?>);
            
            // Verificar si es un nuevo pedido basado en los parámetros de la URL
            const urlParams = new URLSearchParams(window.location.search);
            const esNuevoPedido = urlParams.get('nuevo') === '1';
            const yaExisteSesion = <?php echo $yaExisteSesion ? 'true' : 'false'; ?>;
            
            console.log('esNuevoPedido desde URL:', esNuevoPedido);
            console.log('yaExisteSesion desde PHP:', yaExisteSesion);
            
            // Solo registrar pedido inicial si:
            // 1. No estamos en modo edición
            // 2. Es un nuevo pedido (nuevo=1) 
            // 3. El registro inicial NO ha sido completado aún (evita duplicados en F5)
            if (!<?php echo $modoEdicion ? 'true' : 'false'; ?> && esNuevoPedido && !yaExisteSesion) {
                if (clienteData && clienteData.codigo) {
                    console.log('✅ Iniciando registro automático del pedido NUEVO...');
                    registrarPedidoInicial();
                } else {
                    console.error('❌ No se puede registrar pedido: clienteData no válido');
                    console.log('clienteData:', clienteData);
                }
            } else if (<?php echo $modoEdicion ? 'true' : 'false'; ?>) {
                console.log('✅ Modo edición: cargando productos existentes');
                window.pedidoRegistrado = true; // Marcar como registrado porque ya existe
                // Cargar productos existentes del pedido
                cargarProductosExistentes(pedidoId);
            } else if (yaExisteSesion) {
                console.log('✅ Registro inicial ya completado: evita duplicados en F5');
                window.pedidoRegistrado = true; // Marcar como registrado porque ya existe
                // AGREGAR: Cargar productos existentes si hay un pedido en sesión
                if (pedidoId) {
                    console.log('✅ Cargando productos existentes de sesión:', pedidoId);
                    cargarProductosExistentes(pedidoId);
                }
            } else {
                console.log('✅ Recarga de página: no se registra pedido inicial');
                window.pedidoRegistrado = true; // Marcar como registrado para evitar errores
            }
            
            // === REDIRECCIÓN AUTOMÁTICA AL DASHBOARD DESDE RUTAS ===
            // Solo aplicar si se accede desde 'Rutas' (nuevo=1)
            if (esNuevoPedido) {
                console.log('🔄 Activando redirección automática al dashboard para pedido desde Rutas');
                
                // Variables globales para controlar la redirección
                window.redirigirAlDashboard = true;
                window.pedidoFinalizado = false;
                
                // Event listener para detectar recarga y redirigir inmediatamente
                window.addEventListener('beforeunload', function(e) {
                    // Solo redirigir si:
                    // 1. La redirección está activa
                    // 2. Hay productos en el pedido
                    // 3. El pedido no ha sido finalizado
                    if (window.redirigirAlDashboard && productos.length > 0 && !window.pedidoFinalizado) {
                        // Redirección inmediata sin mostrar modal ni advertencias
                        setTimeout(() => {
                            window.location.href = 'dashboard.php?pedido_flotante=1';
                        }, 0);
                    }
                });
                
                // Detectar teclas de recarga para desktop
                document.addEventListener('keydown', function(e) {
                    if (window.redirigirAlDashboard && productos.length > 0 && !window.pedidoFinalizado) {
                        // F5 o Ctrl+R o Ctrl+F5
                        if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.key === 'F5')) {
                            e.preventDefault();
                            window.location.href = 'dashboard.php?pedido_flotante=1';
                        }
                    }
                });
                
                // Función global para desactivar la redirección
                window.desactivarRedireccionDashboard = function() {
                    console.log('🔓 Desactivando redirección al dashboard');
                    window.redirigirAlDashboard = false;
                };
                
                // Función global para marcar pedido como finalizado
                window.marcarPedidoFinalizado = function() {
                    console.log('✅ Pedido marcado como finalizado - desactivando redirección');
                    window.pedidoFinalizado = true;
                    window.redirigirAlDashboard = false;
                };
            }

         });
         

    </script>
</body>
</html>