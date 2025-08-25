<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log de debug
error_log("API Pedidos - Método: " . $_SERVER['REQUEST_METHOD']);
error_log("API Pedidos - Input: " . file_get_contents('php://input'));

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("API Pedidos - Conexión a BD exitosa");
} catch(PDOException $e) {
    error_log("API Pedidos - Error de conexión: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        error_log("API Pedidos - Error: Datos JSON no válidos");
        echo json_encode(['success' => false, 'message' => 'Datos JSON no válidos']);
        exit;
    }
    
    $action = $data['action'] ?? '';
    error_log("API Pedidos - Acción: " . $action);
    
    if ($action === 'crear_inicial') {
        crearPedidoInicial($pdo, $data);
    } elseif ($action === 'finalizar') {
        finalizarPedido($pdo, $data);
    } elseif ($action === 'agregar_producto_inmediato') {
        agregarProductoInmediato($pdo, $data);
    } elseif ($action === 'limpiar_sesion') {
        limpiarSesionPedido();
    } elseif ($action === 'marcar_registro_completado') {
        marcarRegistroCompletado();
    } elseif ($action === 'obtener_productos_pedido') {
        obtenerProductosPedido($pdo, $data);
    } elseif ($action === 'eliminar_producto_pedido') {
        eliminarProductoPedido($pdo, $data);
    } elseif ($action === 'actualizar_inventario') {
        actualizarInventario($pdo, $data);
    } elseif ($action === 'eliminar_pedido') {
        eliminarPedidoCompleto($pdo, $data);
    } else {
        error_log("API Pedidos - Acción no válida: " . $action);
        echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $action]);
    }
} elseif ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    error_log("API Pedidos - Acción GET: " . $action);
    
    if ($action === 'listar') {
        listarPedidos($pdo);
    } else {
        error_log("API Pedidos - Acción GET no válida: " . $action);
        echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $action]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}

function crearPedidoInicial($pdo, $data) {
    try {
        error_log("API Pedidos - Iniciando crearPedidoInicial");
        error_log("API Pedidos - Datos recibidos: " . json_encode($data));
        
        // Validar campos requeridos
        $required = ['codigoSIN', 'codigoCli', 'tipoDocumento', 'condicion'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                error_log("API Pedidos - Campo requerido faltante: $field");
                echo json_encode(['success' => false, 'message' => "Campo requerido: $field"]);
                return;
            }
        }
        
        // Formatear fecha y hora según especificaciones
        $fechaIngreso = date('j/n/Y'); // Formato: 21/8/2025
        $horaIngreso = date('g:i:s a'); // Formato: 9:19:21 a. m.
        
        error_log("API Pedidos - Fecha: $fechaIngreso, Hora: $horaIngreso");
        
        // Verificar si ya existe el pedido
        $checkSql = "SELECT CodigoSIN FROM tblregistrodepedidos WHERE CodigoSIN = :codigoSIN";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':codigoSIN' => $data['codigoSIN']]);
        
        if ($checkStmt->fetch()) {
            error_log("API Pedidos - Pedido ya existe: " . $data['codigoSIN']);
            echo json_encode(['success' => true, 'message' => 'Pedido ya registrado', 'codigoSIN' => $data['codigoSIN']]);
            return;
        }
        
        $sql = "INSERT INTO tblregistrodepedidos (
            CodigoSIN, FechaIngreso, HoraIngreso, HoraFinalizada, Terminal, 
            Condicion, Plazo, CodigoCli, IDOperador, ImporteTotal, 
            Ok, Anulada, Notas, TipoDocumento, Seleccion
        ) VALUES (
            :codigoSIN, :fechaIngreso, :horaIngreso, NULL, :terminal,
            :condicion, :plazo, :codigoCli, :idOperador, 0,
            0, 0, :notas, :tipoDocumento, 'FALSE'
        )";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            ':codigoSIN' => $data['codigoSIN'],
            ':fechaIngreso' => $fechaIngreso,
            ':horaIngreso' => $horaIngreso,
            ':terminal' => $_SESSION['equipo_asignado'] ?? 'EQ01',
            ':condicion' => $data['condicion'],
            ':plazo' => $data['plazo'] ?? 0,
            ':codigoCli' => $data['codigoCli'],
            ':idOperador' => $_SESSION['user_id'] ?? 1,
            ':notas' => $data['notas'] ?? '',
            ':tipoDocumento' => $data['tipoDocumento']
        ];
        
        error_log("API Pedidos - Parámetros SQL: " . json_encode($params));
        
        $result = $stmt->execute($params);
        
        if ($result) {
            error_log("API Pedidos - Insert exitoso");
            echo json_encode([
                'success' => true, 
                'message' => 'Pedido registrado inicialmente',
                'codigoSIN' => $data['codigoSIN'],
                'fechaIngreso' => $fechaIngreso,
                'horaIngreso' => $horaIngreso
            ]);
        } else {
            error_log("API Pedidos - Insert falló");
            echo json_encode(['success' => false, 'message' => 'Error al insertar pedido']);
        }
        
    } catch(PDOException $e) {
        error_log("API Pedidos - Error PDO en crearPedidoInicial: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al crear pedido: ' . $e->getMessage()]);
    } catch(Exception $e) {
        error_log("API Pedidos - Error general en crearPedidoInicial: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    }
}

function finalizarPedido($pdo, $data) {
    try {
        error_log("API Pedidos - Iniciando finalizarPedido");
        
        $pdo->beginTransaction();
        
        if (!isset($data['codigoSIN']) || !isset($data['productos'])) {
            error_log("API Pedidos - Datos incompletos para finalizar");
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }
        
        // Validar que el pedido existe y está en proceso (OK = 0)
        $checkSql = "SELECT CodigoSIN FROM tblregistrodepedidos WHERE CodigoSIN = :codigoSIN AND Ok = 0";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':codigoSIN' => $data['codigoSIN']]);
        
        if (!$checkStmt->fetch()) {
            error_log("API Pedidos - Pedido no encontrado o ya finalizado: " . $data['codigoSIN']);
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o ya finalizado']);
            return;
        }
        
        // Formatear hora de finalización
        $horaFinalizada = date('g:i:s a'); // Formato: 9:19:21 a. m.
        
        // Actualizar registro principal
        $updateSql = "UPDATE tblregistrodepedidos SET 
            HoraFinalizada = :horaFinalizada,
            ImporteTotal = :importeTotal,
            Ok = 1,
            Notas = :notas
            WHERE CodigoSIN = :codigoSIN";
        
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':horaFinalizada' => $horaFinalizada,
            ':importeTotal' => $data['importeTotal'],
            ':notas' => $data['notas'] ?? '',
            ':codigoSIN' => $data['codigoSIN']
        ]);
        
        // Los productos ya fueron insertados inmediatamente cuando se agregaron
        // Solo verificamos que existan productos en el detalle
        $countSql = "SELECT COUNT(*) as total FROM tbldetalledepedido WHERE CodigoSIN = :codigoSIN";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([':codigoSIN' => $data['codigoSIN']]);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($countResult['total'] == 0) {
            error_log("API Pedidos - No hay productos en el detalle del pedido: " . $data['codigoSIN']);
            echo json_encode(['success' => false, 'message' => 'No hay productos en el pedido']);
            return;
        }
        
        error_log("API Pedidos - Productos encontrados en detalle: " . $countResult['total']);
        
        $pdo->commit();
        
        error_log("API Pedidos - Pedido finalizado exitosamente: " . $data['codigoSIN']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Pedido finalizado exitosamente',
            'codigoSIN' => $data['codigoSIN'],
            'horaFinalizada' => $horaFinalizada,
            'total' => $data['importeTotal']
        ]);
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        error_log("API Pedidos - Error PDO en finalizarPedido: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al finalizar pedido: ' . $e->getMessage()]);
    } catch(Exception $e) {
        $pdo->rollBack();
        error_log("API Pedidos - Error general en finalizarPedido: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    }
}

function listarPedidos($pdo) {
    try {
        error_log("API Pedidos - Iniciando listarPedidos");
        
        // Obtener el equipo asignado de la sesión
        $equipoAsignado = $_SESSION['equipo_asignado'] ?? null;
        error_log("API Pedidos - Equipo asignado en sesión: " . ($equipoAsignado ?? 'NULL'));
        
        // Construir la consulta con filtro por terminal si está disponible
        $whereClause = "";
        $params = [];
        
        if (!empty($equipoAsignado)) {
            // Filtrar por los primeros 5 dígitos del codigoSIN y por el campo Terminal
            $whereClause = "WHERE SUBSTRING(p.CodigoSIN, 1, 5) = :equipoAsignado AND p.Terminal = :terminal";
            $params[':equipoAsignado'] = $equipoAsignado;
            $params[':terminal'] = $equipoAsignado;
        }
        
        // Consulta para obtener pedidos con información del cliente y monto calculado dinámicamente
        $sql = "SELECT 
            p.CodigoSIN,
            p.FechaIngreso,
            p.HoraIngreso,
            p.HoraFinalizada,
            p.Terminal,
            p.Condicion,
            p.Plazo,
            p.CodigoCli,
            p.IDOperador,
            COALESCE(d.ImporteCalculado, 0) as ImporteTotal,
            p.Ok,
            p.Anulada,
            p.Notas,
            p.TipoDocumento,
            p.Seleccion,
            c.Nombre as NombreCliente,
            c.Establecimiento as EstablecimientoCliente
        FROM tblregistrodepedidos p
        LEFT JOIN tblcatalogodeclientes c ON p.CodigoCli = c.CodigoCli
        LEFT JOIN (
            SELECT 
                CodigoSIN,
                SUM(PrecioVenta * CAST(Cantidad AS DECIMAL(10,2))) as ImporteCalculado
            FROM tbldetalledepedido 
            GROUP BY CodigoSIN
        ) d ON p.CodigoSIN = d.CodigoSIN
        $whereClause
        ORDER BY p.FechaIngreso DESC, p.HoraIngreso DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear datos para el frontend
        $pedidosFormateados = [];
        foreach ($pedidos as $pedido) {
            $nombreCompleto = trim(($pedido['NombreCliente'] ?? '') . ' ' . ($pedido['EstablecimientoCliente'] ?? ''));
            if (empty($nombreCompleto)) {
                $nombreCompleto = 'Cliente no especificado';
            }
            
            $pedidosFormateados[] = [
                'CodigoSIN' => $pedido['CodigoSIN'],
                'FechaIngreso' => $pedido['FechaIngreso'],
                'HoraIngreso' => $pedido['HoraIngreso'],
                'HoraFinalizada' => $pedido['HoraFinalizada'],
                'Terminal' => $pedido['Terminal'],
                'Condicion' => $pedido['Condicion'],
                'Plazo' => $pedido['Plazo'],
                'CodigoCli' => $pedido['CodigoCli'],
                'NombreCliente' => $nombreCompleto,
                'IDOperador' => $pedido['IDOperador'],
                'ImporteTotal' => $pedido['ImporteTotal'],
                'OK' => $pedido['Ok'],
                'Anulada' => $pedido['Anulada'],
                'Notas' => $pedido['Notas'],
                'TipoDocumento' => $pedido['TipoDocumento'],
                'Seleccion' => $pedido['Seleccion']
            ];
        }
        
        error_log("API Pedidos - Pedidos encontrados: " . count($pedidosFormateados));
        
        echo json_encode([
            'success' => true,
            'pedidos' => $pedidosFormateados,
            'total' => count($pedidosFormateados)
        ]);
        
    } catch(PDOException $e) {
        error_log("API Pedidos - Error PDO en listarPedidos: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener pedidos: ' . $e->getMessage()]);
    } catch(Exception $e) {
        error_log("API Pedidos - Error general en listarPedidos: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    }
}

function limpiarSesionPedido() {
    try {
        error_log("API Pedidos - Limpiando sesión del pedido");
        
        // Limpiar variables de sesión relacionadas con el pedido actual
        unset($_SESSION['pedido_id_actual']);
        unset($_SESSION['pedido_cliente_actual']);
        unset($_SESSION['pedido_registrado_inicialmente']);
        
        error_log("API Pedidos - Sesión limpiada exitosamente");
        
        echo json_encode([
            'success' => true,
            'message' => 'Sesión del pedido limpiada exitosamente'
        ]);
        
    } catch(Exception $e) {
        error_log("API Pedidos - Error al limpiar sesión: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al limpiar sesión: ' . $e->getMessage()]);
    }
}

function agregarProductoInmediato($pdo, $data) {
    try {
        error_log("API Pedidos - Iniciando agregarProductoInmediato");
        error_log("API Pedidos - Datos recibidos: " . json_encode($data));
        
        // Iniciar transacción para operación atómica
        $pdo->beginTransaction();
        
        // Validar campos requeridos
        $required = ['codigoSIN', 'codigoProd', 'cantidad', 'precioVenta'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                error_log("API Pedidos - Campo requerido faltante: $field");
                echo json_encode(['success' => false, 'message' => "Campo requerido: $field"]);
                return;
            }
        }
        
        // Obtener idimportacion de tblCatalogoDeProductos
        $productoSql = "SELECT idimportacion, CodigoProd FROM tblcatalogodeproductos WHERE CodigoProd = :codigoProd LIMIT 1";
        $productoStmt = $pdo->prepare($productoSql);
        $productoStmt->execute([':codigoProd' => $data['codigoProd']]);
        $producto = $productoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            error_log("API Pedidos - Producto no encontrado: " . $data['codigoProd']);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            return;
        }
        
        // Verificar si el pedido existe
        $pedidoSql = "SELECT CodigoSIN FROM tblregistrodepedidos WHERE CodigoSIN = :codigoSIN AND Ok = 0";
        $pedidoStmt = $pdo->prepare($pedidoSql);
        $pedidoStmt->execute([':codigoSIN' => $data['codigoSIN']]);
        
        if (!$pedidoStmt->fetch()) {
            error_log("API Pedidos - Pedido no encontrado o ya finalizado: " . $data['codigoSIN']);
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o ya finalizado']);
            return;
        }
        
        // Verificar si el producto ya existe en el detalle del pedido (considerando TV para distinguir fardo/unidad)
        $existeSql = "SELECT Cantidad FROM tbldetalledepedido WHERE CodigoSIN = :codigoSIN AND CodigoProd = :codigoProd AND TV = :tv";
        $existeStmt = $pdo->prepare($existeSql);
        $existeStmt->execute([
            ':codigoSIN' => $data['codigoSIN'],
            ':codigoProd' => $data['codigoProd'],
            ':tv' => $data['tv'] ?? ''
        ]);
        $productoExistente = $existeStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($productoExistente) {
            // Si existe, actualizar cantidad
            $nuevaCantidad = $productoExistente['Cantidad'] + $data['cantidad'];
            $updateSql = "UPDATE tbldetalledepedido SET 
                Cantidad = :cantidad,
                Bonificacion = :bonificacion,
                PrecioVenta = :precioVenta,
                Descuento = :descuento,
                AgregarOferta = :agregarOferta,
                Observaciones = :observaciones
                WHERE CodigoSIN = :codigoSIN AND CodigoProd = :codigoProd AND TV = :tv";
            
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':cantidad' => $nuevaCantidad,
                ':bonificacion' => $data['bonificacion'] ?? 0,
                ':precioVenta' => $data['precioVenta'],
                ':descuento' => $data['descuento'] ?? 0,
                ':agregarOferta' => $data['agregarOferta'] ?? 'FALSE',
                ':observaciones' => $data['observaciones'] ?? '',
                ':codigoSIN' => $data['codigoSIN'],
                ':codigoProd' => $data['codigoProd'],
                ':tv' => $data['tv'] ?? ''
            ]);
            
            error_log("API Pedidos - Producto actualizado en detalle: " . $data['codigoProd']);
        } else {
            // Si no existe, insertar nuevo registro
            $insertSql = "INSERT INTO tbldetalledepedido (
                CodigoSIN, IDProducto, CodigoProd, TV, Cantidad, 
                Bonificacion, PrecioVenta, Descuento, AgregarOferta, Observaciones
            ) VALUES (
                :codigoSIN, :idProducto, :codigoProd, :tv, :cantidad,
                :bonificacion, :precioVenta, :descuento, :agregarOferta, :observaciones
            )";
            
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                ':codigoSIN' => $data['codigoSIN'],
                ':idProducto' => $producto['idimportacion'], // Usar idimportacion como IDProducto
                ':codigoProd' => $data['codigoProd'], // Usar CodigoProd original del parámetro para preservar cero inicial
                ':tv' => $data['tv'] ?? '',
                ':cantidad' => $data['cantidad'],
                ':bonificacion' => $data['bonificacion'] ?? 0,
                ':precioVenta' => $data['precioVenta'],
                ':descuento' => $data['descuento'] ?? 0,
                ':agregarOferta' => $data['agregarOferta'] ?? 'FALSE',
                ':observaciones' => $data['observaciones'] ?? ''
            ]);
            
            error_log("API Pedidos - Producto insertado en detalle: " . $data['codigoProd']);
        }
        
        // Actualizar inventario restando las unidades vendidas
        try {
            $inventarioData = [
                'codigoProd' => $data['codigoProd'],
                'cantidad' => $data['cantidad'], // Cantidad ya está en unidades individuales
                'operacion' => 'restar'
            ];
            
            actualizarInventarioInterno($pdo, $inventarioData);
            error_log("API Pedidos - Inventario actualizado: -" . $data['cantidad'] . " unidades para " . $data['codigoProd']);
            
        } catch (Exception $e) {
            error_log("API Pedidos - Error al actualizar inventario: " . $e->getMessage());
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al actualizar inventario: ' . $e->getMessage()]);
            return;
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto agregado al detalle del pedido e inventario actualizado',
            'codigoSIN' => $data['codigoSIN'],
            'codigoProd' => $data['codigoProd'],
            'idProducto' => $producto['idimportacion']
        ]);
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        error_log("API Pedidos - Error PDO en agregarProductoInmediato: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al agregar producto: ' . $e->getMessage()]);
    } catch(Exception $e) {
        $pdo->rollBack();
        error_log("API Pedidos - Error general en agregarProductoInmediato: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    }
}

function marcarRegistroCompletado() {
    try {
        error_log("API Pedidos - Marcando registro como completado");
        
        // Marcar que el registro inicial fue completado exitosamente
        $_SESSION['pedido_registrado_inicialmente'] = true;
        
        error_log("API Pedidos - Registro marcado como completado");
        
        echo json_encode([
            'success' => true,
            'message' => 'Registro marcado como completado'
        ]);
        
    } catch(Exception $e) {
        error_log("API Pedidos - Error al marcar registro: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al marcar registro: ' . $e->getMessage()]);
    }
}

function obtenerProductosPedido($pdo, $data) {
    try {
        error_log("API Pedidos - Iniciando obtenerProductosPedido");
        error_log("API Pedidos - Datos recibidos: " . json_encode($data));
        
        // Validar campo requerido
        if (!isset($data['codigoSIN']) || $data['codigoSIN'] === '') {
            error_log("API Pedidos - CodigoSIN requerido");
            echo json_encode(['success' => false, 'message' => 'CodigoSIN es requerido']);
            return;
        }
        
        // Consultar productos del pedido con JOIN a tblcatalogodeproductos
        $sql = "SELECT 
            d.IDDetalle,
            d.CodigoSIN,
            d.IDProducto,
            d.CodigoProd,
            d.TV,
            d.Cantidad,
            d.Bonificacion,
            d.PrecioVenta,
            d.Descuento,
            d.AgregarOferta,
            d.Observaciones,
            p.descripcion,
            p.unidades,
            p.tipoproducto,
            p.contenido1,
            p.contenido2
        FROM tbldetalledepedido d
        INNER JOIN tblcatalogodeproductos p ON d.CodigoProd = p.CodigoProd
        WHERE d.CodigoSIN = :codigoSIN
        ORDER BY d.IDDetalle";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':codigoSIN' => $data['codigoSIN']]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("API Pedidos - Productos encontrados: " . count($productos));
        
        // Formatear productos para el frontend
        $productosFormateados = [];
        foreach ($productos as $producto) {
            // Convertir cantidad de base de datos a cantidad de visualización
            $cantidadDB = (float)$producto['Cantidad']; // Cantidad en unidades individuales desde DB
            $unidadesPorFardo = (int)$producto['unidades'];
            $tv = $producto['TV'];
            
            // Determinar cantidad para mostrar en UI
            if ($tv === '' || $tv === null) {
                // Es fardo: convertir unidades individuales a fardos
                $cantidadDisplay = $unidadesPorFardo > 0 ? $cantidadDB / $unidadesPorFardo : $cantidadDB;
            } else {
                // Es unidad: usar cantidad directamente
                $cantidadDisplay = $cantidadDB;
            }
            
            $productosFormateados[] = [
                'idDetalle' => (int)$producto['IDDetalle'], // ID único del detalle
                'id' => $producto['IDProducto'],
                'codigoProd' => $producto['CodigoProd'],
                'descripcion' => $producto['descripcion'],
                'cantidad' => $cantidadDisplay, // Cantidad convertida para visualización
                'precio' => (float)$producto['PrecioVenta'],
                'importe' => (float)$producto['Cantidad'] * (float)$producto['PrecioVenta'],
                'bonificacion' => (float)$producto['Bonificacion'],
                'descuento' => (float)$producto['Descuento'],
                'oferta' => $producto['AgregarOferta'],
                'autorizacion' => $producto['Observaciones'],
                'unidades' => $unidadesPorFardo,
                'tipoproducto' => (int)$producto['tipoproducto'],
                'contenido1' => $producto['contenido1'],
                'contenido2' => $producto['contenido2'],
                'tv' => $tv // Agregar campo TV de la base de datos
            ];
        }
        
        echo json_encode([
            'success' => true,
            'productos' => $productosFormateados,
            'total' => count($productosFormateados)
        ]);
        
    } catch(PDOException $e) {
        error_log("API Pedidos - Error PDO en obtenerProductosPedido: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener productos: ' . $e->getMessage()]);
    } catch(Exception $e) {
        error_log("API Pedidos - Error general en obtenerProductosPedido: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    }
}

function eliminarProductoPedido($pdo, $data) {
    try {
        error_log("API Pedidos - Iniciando eliminarProductoPedido");
        error_log("API Pedidos - Datos recibidos: " . json_encode($data));
        
        // Iniciar transacción para operación atómica
        $pdo->beginTransaction();
        
        // Validar campos requeridos
        $required = ['codigoSIN', 'codigoProd'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                error_log("API Pedidos - Campo requerido faltante: $field");
                echo json_encode(['success' => false, 'message' => "Campo requerido: $field"]);
                return;
            }
        }
        
        // El campo TV es opcional pero necesario para distinguir fardo/unidad
        if (!isset($data['tv'])) {
            $data['tv'] = '';
        }
        
        // Verificar si el pedido existe y está en proceso
        $pedidoSql = "SELECT CodigoSIN FROM tblregistrodepedidos WHERE CodigoSIN = :codigoSIN AND Ok = 0";
        $pedidoStmt = $pdo->prepare($pedidoSql);
        $pedidoStmt->execute([':codigoSIN' => $data['codigoSIN']]);
        
        if (!$pedidoStmt->fetch()) {
            error_log("API Pedidos - Pedido no encontrado o ya finalizado: " . $data['codigoSIN']);
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o ya finalizado']);
            return;
        }
        
        // Obtener información del producto antes de eliminarlo (incluyendo cantidad para reversión de inventario)
        $existeSql = "SELECT CodigoProd, Cantidad FROM tbldetalledepedido WHERE CodigoSIN = :codigoSIN AND CodigoProd = :codigoProd AND TV = :tv";
        $existeStmt = $pdo->prepare($existeSql);
        $existeStmt->execute([
            ':codigoSIN' => $data['codigoSIN'],
            ':codigoProd' => $data['codigoProd'],
            ':tv' => $data['tv'] ?? ''
        ]);
        
        $productoDetalle = $existeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$productoDetalle) {
            error_log("API Pedidos - Producto no encontrado en el detalle: " . $data['codigoProd']);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado en el pedido']);
            return;
        }
        
        // Guardar la cantidad para reversión de inventario
        $cantidadADevolver = (int)$productoDetalle['Cantidad']; // Cantidad en unidades individuales
        
        // Eliminar el producto del detalle del pedido (incluyendo TV para distinguir fardo/unidad)
        $deleteSql = "DELETE FROM tbldetalledepedido WHERE CodigoSIN = :codigoSIN AND CodigoProd = :codigoProd AND TV = :tv";
        $deleteStmt = $pdo->prepare($deleteSql);
        $result = $deleteStmt->execute([
            ':codigoSIN' => $data['codigoSIN'],
            ':codigoProd' => $data['codigoProd'],
            ':tv' => $data['tv'] ?? ''
        ]);
        
        if ($result) {
            error_log("API Pedidos - Producto eliminado del detalle: " . $data['codigoProd']);
            
            // Devolver las unidades al inventario
            try {
                $inventarioData = [
                    'codigoProd' => $data['codigoProd'],
                    'cantidad' => $cantidadADevolver,
                    'operacion' => 'sumar'
                ];
                
                // Llamar a la función actualizarInventario internamente
                actualizarInventarioInterno($pdo, $inventarioData);
                
                error_log("API Pedidos - Inventario restaurado: " . $cantidadADevolver . " unidades devueltas para " . $data['codigoProd']);
                
            } catch (Exception $e) {
                error_log("API Pedidos - Error al restaurar inventario: " . $e->getMessage());
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error al restaurar inventario: ' . $e->getMessage()]);
                return;
            }
            
            // Confirmar transacción
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Producto eliminado del pedido e inventario restaurado',
                'codigoSIN' => $data['codigoSIN'],
                'codigoProd' => $data['codigoProd'],
                'cantidadDevuelta' => $cantidadADevolver
            ]);
        } else {
            error_log("API Pedidos - Error al eliminar producto: " . $data['codigoProd']);
            echo json_encode(['success' => false, 'message' => 'Error al eliminar producto']);
        }
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        error_log("API Pedidos - Error PDO en eliminarProductoPedido: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar producto: ' . $e->getMessage()]);
    } catch(Exception $e) {
        $pdo->rollBack();
        error_log("API Pedidos - Error general en eliminarProductoPedido: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    }
}

function actualizarInventario($pdo, $data) {
    try {
        error_log("API Pedidos - Iniciando actualizarInventario");
        error_log("API Pedidos - Datos recibidos: " . json_encode($data));
        
        // Validar campos requeridos
        $required = ['codigoProd', 'cantidad', 'operacion'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                error_log("API Pedidos - Campo requerido faltante: $field");
                echo json_encode(['success' => false, 'message' => "Campo requerido: $field"]);
                return;
            }
        }
        
        // Validar que la operación sea válida ('restar' o 'sumar')
        if (!in_array($data['operacion'], ['restar', 'sumar'])) {
            error_log("API Pedidos - Operación no válida: " . $data['operacion']);
            echo json_encode(['success' => false, 'message' => 'Operación no válida. Use: restar o sumar']);
            return;
        }
        
        // Obtener información actual del producto
        $productoSql = "SELECT existencia, unidades, descripcion FROM tblcatalogodeproductos WHERE CodigoProd = :codigoProd";
        $productoStmt = $pdo->prepare($productoSql);
        $productoStmt->execute([':codigoProd' => $data['codigoProd']]);
        $producto = $productoStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$producto) {
            error_log("API Pedidos - Producto no encontrado: " . $data['codigoProd']);
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            return;
        }
        
        $existenciaActual = (int)$producto['existencia'];
        $cantidadCambio = (int)$data['cantidad'];
        
        // Calcular nueva existencia según la operación
        if ($data['operacion'] === 'restar') {
            $nuevaExistencia = $existenciaActual - $cantidadCambio;
            
            // Validar que no quede en negativo
            if ($nuevaExistencia < 0) {
                error_log("API Pedidos - Operación resultaría en stock negativo");
                echo json_encode([
                    'success' => false, 
                    'message' => 'Stock insuficiente. Disponible: ' . $existenciaActual . ', Solicitado: ' . $cantidadCambio
                ]);
                return;
            }
        } else { // sumar
            $nuevaExistencia = $existenciaActual + $cantidadCambio;
        }
        
        // Actualizar existencia en la base de datos
        $updateSql = "UPDATE tblcatalogodeproductos SET existencia = :nuevaExistencia WHERE CodigoProd = :codigoProd";
        $updateStmt = $pdo->prepare($updateSql);
        $result = $updateStmt->execute([
            ':nuevaExistencia' => $nuevaExistencia,
            ':codigoProd' => $data['codigoProd']
        ]);
        
        if ($result) {
            error_log("API Pedidos - Inventario actualizado: " . $data['codigoProd'] . " de " . $existenciaActual . " a " . $nuevaExistencia);
            
            echo json_encode([
                'success' => true,
                'message' => 'Inventario actualizado correctamente',
                'codigoProd' => $data['codigoProd'],
                'existenciaAnterior' => $existenciaActual,
                'existenciaNueva' => $nuevaExistencia,
                'operacion' => $data['operacion'],
                'cantidad' => $cantidadCambio
            ]);
        } else {
            error_log("API Pedidos - Error al actualizar inventario: " . $data['codigoProd']);
            echo json_encode(['success' => false, 'message' => 'Error al actualizar inventario']);
        }
        
    } catch(PDOException $e) {
        error_log("API Pedidos - Error PDO en actualizarInventario: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al actualizar inventario: ' . $e->getMessage()]);
    } catch(Exception $e) {
        error_log("API Pedidos - Error general en actualizarInventario: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    }
}

// Función interna para actualizar inventario sin enviar respuesta JSON
function actualizarInventarioInterno($pdo, $data) {
    // Validar campos requeridos
    $required = ['codigoProd', 'cantidad', 'operacion'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    // Validar que la operación sea válida ('restar' o 'sumar')
    if (!in_array($data['operacion'], ['restar', 'sumar'])) {
        throw new Exception('Operación no válida. Use: restar o sumar');
    }
    
    // Obtener información actual del producto
    $productoSql = "SELECT existencia, unidades, descripcion FROM tblcatalogodeproductos WHERE CodigoProd = :codigoProd";
    $productoStmt = $pdo->prepare($productoSql);
    $productoStmt->execute([':codigoProd' => $data['codigoProd']]);
    $producto = $productoStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        throw new Exception('Producto no encontrado: ' . $data['codigoProd']);
    }
    
    $existenciaActual = (int)$producto['existencia'];
    $cantidadCambio = (int)$data['cantidad'];
    
    // Calcular nueva existencia según la operación
    if ($data['operacion'] === 'restar') {
        $nuevaExistencia = $existenciaActual - $cantidadCambio;
        
        // Validar que no quede en negativo
        if ($nuevaExistencia < 0) {
            throw new Exception('Stock insuficiente. Disponible: ' . $existenciaActual . ', Solicitado: ' . $cantidadCambio);
        }
    } else { // sumar
        $nuevaExistencia = $existenciaActual + $cantidadCambio;
    }
    
    // Actualizar existencia en la base de datos
    $updateSql = "UPDATE tblcatalogodeproductos SET existencia = :nuevaExistencia WHERE CodigoProd = :codigoProd";
    $updateStmt = $pdo->prepare($updateSql);
    $result = $updateStmt->execute([
        ':nuevaExistencia' => $nuevaExistencia,
        ':codigoProd' => $data['codigoProd']
    ]);
    
    if (!$result) {
        throw new Exception('Error al actualizar inventario en la base de datos');
    }
    
    return [
        'existenciaAnterior' => $existenciaActual,
        'existenciaNueva' => $nuevaExistencia,
        'operacion' => $data['operacion'],
        'cantidad' => $cantidadCambio
    ];
}

function eliminarPedidoCompleto($pdo, $data) {
    try {
        error_log("API Pedidos - Iniciando eliminarPedidoCompleto");
        error_log("API Pedidos - Datos recibidos: " . json_encode($data));
        
        // Validar campo requerido
        if (!isset($data['codigoSIN']) || $data['codigoSIN'] === '') {
            error_log("API Pedidos - CodigoSIN requerido");
            echo json_encode(['success' => false, 'message' => 'CodigoSIN es requerido']);
            return;
        }
        
        // Iniciar transacción para operación atómica
        $pdo->beginTransaction();
        
        // Verificar que el pedido existe
        $checkSql = "SELECT CodigoSIN FROM tblregistrodepedidos WHERE CodigoSIN = :codigoSIN";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':codigoSIN' => $data['codigoSIN']]);
        
        if (!$checkStmt->fetch()) {
            error_log("API Pedidos - Pedido no encontrado: " . $data['codigoSIN']);
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
            return;
        }
        
        // Primero eliminar todos los detalles del pedido
        $deleteDetallesSql = "DELETE FROM tbldetalledepedido WHERE CodigoSIN = :codigoSIN";
        $deleteDetallesStmt = $pdo->prepare($deleteDetallesSql);
        $resultDetalles = $deleteDetallesStmt->execute([':codigoSIN' => $data['codigoSIN']]);
        
        if (!$resultDetalles) {
            $pdo->rollBack();
            error_log("API Pedidos - Error al eliminar detalles del pedido: " . $data['codigoSIN']);
            echo json_encode(['success' => false, 'message' => 'Error al eliminar detalles del pedido']);
            return;
        }
        
        // Luego eliminar el registro principal del pedido
        $deletePedidoSql = "DELETE FROM tblregistrodepedidos WHERE CodigoSIN = :codigoSIN";
        $deletePedidoStmt = $pdo->prepare($deletePedidoSql);
        $resultPedido = $deletePedidoStmt->execute([':codigoSIN' => $data['codigoSIN']]);
        
        if (!$resultPedido) {
            $pdo->rollBack();
            error_log("API Pedidos - Error al eliminar pedido: " . $data['codigoSIN']);
            echo json_encode(['success' => false, 'message' => 'Error al eliminar pedido']);
            return;
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        error_log("API Pedidos - Pedido eliminado exitosamente: " . $data['codigoSIN']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Pedido eliminado exitosamente',
            'codigoSIN' => $data['codigoSIN']
        ]);
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        error_log("API Pedidos - Error PDO en eliminarPedidoCompleto: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar pedido: ' . $e->getMessage()]);
    } catch(Exception $e) {
        $pdo->rollBack();
        error_log("API Pedidos - Error general en eliminarPedidoCompleto: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error inesperado: ' . $e->getMessage()]);
    }
}
?>