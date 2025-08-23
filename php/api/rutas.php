<?php
// Configurar zona horaria de El Salvador
date_default_timezone_set('America/El_Salvador');

header('Content-Type: application/json');
require '../config/database.php';

try {
    $empleado = $_GET['empleado'] ?? null;
    $dia = $_GET['dia'] ?? null;
    
    if (!$empleado || !$dia) {
        echo json_encode([
            'success' => false,
            'message' => 'Parámetros empleado y dia son requeridos'
        ]);
        exit;
    }
    
    // Obtener las rutas asignadas al empleado para el día específico
    $stmt = $pdo->prepare("
        SELECT IDRuta 
        FROM tblrutas 
        WHERE IDEmpleado = ? AND IDDia = ?
    ");
    $stmt->execute([$empleado, $dia]);
    $rutas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($rutas)) {
        echo json_encode([
            'success' => true,
            'clientes' => [],
            'message' => 'No hay rutas asignadas para este empleado en este día'
        ]);
        exit;
    }
    
    // Crear placeholders para la consulta IN
    $placeholders = str_repeat('?,', count($rutas) - 1) . '?';
    
    // Obtener clientes de las zonas correspondientes a las rutas
    $stmt = $pdo->prepare("
        SELECT 
            CodigoCli,
            Nombre,
            Establecimiento,
            Direccion,
            IDMunicipio,
            IDZona,
            UltimoPedido,
            FechaDeVisita,
            HoraDeVisita
        FROM tblcatalogodeclientes 
        WHERE IDZona IN ($placeholders)
        ORDER BY Nombre ASC
    ");
    $stmt->execute($rutas);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'clientes' => $clientes,
        'rutas' => $rutas,
        'total' => count($clientes)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>