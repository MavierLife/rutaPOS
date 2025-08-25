<?php
// test_exportar.php - Script de prueba para la exportación
session_start();

// Simular una sesión de usuario para las pruebas
$_SESSION['user_id'] = 231; // Usuario de prueba
$_SESSION['equipo_asignado'] = null; // Forzar consulta a tblconfiguraciones

echo "=== PRUEBA DE EXPORTACIÓN DIRECTA ===\n";
echo "Usuario ID: " . $_SESSION['user_id'] . "\n";
echo "Equipo asignado: " . ($_SESSION['equipo_asignado'] ?? 'null') . "\n\n";

try {
    // Incluir el archivo de configuración de base de datos
    require_once 'php/config/database.php';
    
    echo "Conexión a base de datos establecida\n";
    
    // Probar la función exportarPedidos directamente
    echo "Iniciando prueba de exportación...\n";
    
    // Obtener terminal de la sesión activa
    $terminalActiva = $_SESSION['equipo_asignado'] ?? null;
    
    // Si no hay terminal asignada en la sesión, consultar tblconfiguraciones
    if (!$terminalActiva) {
        echo "No hay terminal asignada en la sesión, consultando tblconfiguraciones...\n";
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new Exception('No se ha iniciado sesión correctamente. Falta información del usuario.');
        }
        
        $stmt_config = $pdo->prepare("SELECT EquipoAsigna FROM tblconfiguraciones WHERE UsuarioAsigna = ?");
        $stmt_config->execute([$userId]);
        $config = $stmt_config->fetch();
        
        if ($config && !empty($config['EquipoAsigna'])) {
            $terminalActiva = $config['EquipoAsigna'];
            $_SESSION['equipo_asignado'] = $terminalActiva;
            echo "Terminal obtenida de tblconfiguraciones: " . $terminalActiva . "\n";
        } else {
            throw new Exception('El usuario no tiene un equipo asignado en la configuración.');
        }
    }
    
    echo "Terminal activa: " . $terminalActiva . "\n";
    
    // Construir consulta SQL con filtro por terminal
    $sql = "
        SELECT 
            rp.CodigoSIN,
            rp.FechaIngreso,
            rp.HoraIngreso,
            rp.Terminal,
            rp.Condicion,
            rp.Plazo,
            rp.CodigoCli,
            rp.IDOperador as IDVendedor,
            rp.Ok,
            rp.Anulada,
            rp.Notas,
            dp.IDProducto,
            dp.CodigoProd,
            dp.TV,
            dp.Cantidad,
            dp.Bonificacion,
            dp.PrecioVenta,
            dp.Descuento,
            dp.AgregarOferta,
            dp.Observaciones
        FROM tblregistrodepedidos rp
        INNER JOIN tbldetalledepedido dp ON rp.CodigoSIN = dp.CodigoSIN
        WHERE rp.Ok = '1' AND rp.CodigoSIN LIKE :terminal
        ORDER BY rp.CodigoSIN, dp.CodigoProd
    ";
    
    echo "Ejecutando consulta SQL...\n";
    $stmt = $pdo->prepare($sql);
    $terminalPattern = $terminalActiva . '%';
    $stmt->bindParam(':terminal', $terminalPattern, PDO::PARAM_STR);
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Pedidos encontrados: " . count($pedidos) . "\n";
    
    if (empty($pedidos)) {
        echo "No se encontraron pedidos finalizados para exportar.\n";
    } else {
        echo "Primeros 3 pedidos encontrados:\n";
        for ($i = 0; $i < min(3, count($pedidos)); $i++) {
            echo "- CodigoSIN: " . $pedidos[$i]['CodigoSIN'] . ", CodigoProd: " . $pedidos[$i]['CodigoProd'] . "\n";
        }
    }
    
    echo "\n=== PRUEBA COMPLETADA EXITOSAMENTE ===\n";
    
} catch (Exception $e) {
    echo "\n=== ERROR ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

echo "\n=== FIN DE PRUEBA ===\n";
?>