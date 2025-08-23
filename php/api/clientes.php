<?php
session_start();
date_default_timezone_set('America/El_Salvador');
require '../config/database.php';

header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

try {
    // Consultar TODOS los clientes de la base de datos
    $stmt = $pdo->prepare("
        SELECT 
            CodigoCli,
            Nombre,
            Establecimiento,
            Direccion,
            IDZona,
            IDMunicipio,
            UltimoPedido,
            TelMovil,
            DUI,
            NIT,
            GiroComercial,
            Inactivo
        FROM tblcatalogodeclientes 
        ORDER BY Nombre ASC
    ");
    
    $stmt->execute();
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Limpiar datos nulos y formatear
    foreach ($clientes as &$cliente) {
        $cliente['Nombre'] = $cliente['Nombre'] ?? '';
        $cliente['Establecimiento'] = $cliente['Establecimiento'] ?? 'TIENDA';
        $cliente['Direccion'] = $cliente['Direccion'] ?? '';
        $cliente['IDZona'] = $cliente['IDZona'] ?? 0;
        $cliente['IDMunicipio'] = $cliente['IDMunicipio'] ?? 0;
        $cliente['UltimoPedido'] = $cliente['UltimoPedido'] ?? '';
        $cliente['TelMovil'] = $cliente['TelMovil'] ?? '';
        $cliente['DUI'] = $cliente['DUI'] ?? '';
        $cliente['NIT'] = $cliente['NIT'] ?? '';
        $cliente['GiroComercial'] = $cliente['GiroComercial'] ?? '';
        $cliente['Inactivo'] = $cliente['Inactivo'] ?? 'FALSE';
    }
    
    echo json_encode([
        'success' => true,
        'clientes' => $clientes,
        'total' => count($clientes)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>