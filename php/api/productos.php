<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener parámetros de búsqueda
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'productos'; // productos u ofertas
    $letter = isset($_GET['letter']) ? $_GET['letter'] : '';
    
    // Construir la consulta base
    $sql = "SELECT 
                CodigoProd,
                descripcion,
                existencia,
                preciodetalle,
                preciomayoreo,
                precioespecial,
                preciopublico,
                unidades,
                uminimamayoreo,
                contenido1,
                contenido2,
                EstaOfertado,
                suspendido
            FROM tblcatalogodeproductos 
            WHERE suspendido = 'FALSE'";
    
    $params = [];
    
    // Filtro por tipo (productos u ofertas)
    if ($filter === 'ofertas') {
        $sql .= " AND EstaOfertado = '1'";
    } else {
        $sql .= " AND (EstaOfertado = '0' OR EstaOfertado IS NULL)";
    }
    
    // Filtro por búsqueda
    if (!empty($search)) {
        $sql .= " AND (descripcion LIKE :search OR CodigoProd LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    // Filtro por letra
    if (!empty($letter)) {
        $sql .= " AND descripcion LIKE :letter";
        $params[':letter'] = $letter . '%';
    }
    
    // Ordenar por descripción
    $sql .= " ORDER BY descripcion ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los datos
    $productosFormateados = [];
    foreach ($productos as $producto) {
        $existencia = (int)$producto['existencia'];
        $unidadesPorFardo = (int)$producto['unidades'];
        $precioDetalle = (float)$producto['preciodetalle'];
        $precioMayoreo = (float)$producto['preciomayoreo'];
        $precioEspecial = (float)$producto['precioespecial'];
        $precioPublico = (float)$producto['preciopublico'];
        $uMinimaMAyoreo = (int)$producto['uminimamayoreo'];
        
        // Calcular fardos y unidades sueltas
        if ($unidadesPorFardo > 0) {
            $fardos = floor($existencia / $unidadesPorFardo);
            $unidadesSueltas = $existencia % $unidadesPorFardo;
            
            if ($fardos > 0 && $unidadesSueltas > 0) {
                $stockDisplay = $fardos . ' | ' . $unidadesSueltas;
            } elseif ($fardos > 0) {
                $stockDisplay = $fardos . ' | 0';
            } else {
                $stockDisplay = '0 | ' . $unidadesSueltas;
            }
        } else {
            $stockDisplay = $existencia > 0 ? $existencia . ' | 0' : '0 | 0';
        }
        
        if ($existencia == 0) {
            $stockDisplay = '0 | 0';
        }
        
        // Calcular precios según la lógica del usuario
        // M:$ = PrecioMayoreo (directo)
        $precioMayoreoFardo = $precioMayoreo;
        
        // D:$ = PrecioDetalle ÷ Unidades
        $precioDetalleUnitario = $unidadesPorFardo > 0 ? $precioDetalle / $unidadesPorFardo : $precioDetalle;
        
        // Unitario Mayoreo = PrecioMayoreo ÷ Unidades
        $precioMayoreoUnitario = $unidadesPorFardo > 0 ? $precioMayoreo / $unidadesPorFardo : $precioMayoreo;
        
        // Precio Especial Unitario
        $precioEspecialUnitario = $unidadesPorFardo > 0 ? $precioEspecial / $unidadesPorFardo : $precioEspecial;
        
        $productosFormateados[] = [
            'CodigoProd' => $producto['CodigoProd'],
            'descripcion' => $producto['descripcion'],
            'existencia' => $existencia,
            'stockDisplay' => $stockDisplay,
            'contenido1' => $producto['contenido1'] ?? '',
            'contenido2' => $producto['contenido2'] ?? '',
            'unidades' => $unidadesPorFardo,
            'uminimamayoreo' => $uMinimaMAyoreo,
            // Precios originales
            'preciodetalle' => $precioDetalle,
            'preciomayoreo' => $precioMayoreo,
            'precioespecial' => $precioEspecial,
            'preciopublico' => $precioPublico,
            // Precios calculados para mostrar
            'precioMayoreoFardo' => number_format($precioMayoreoFardo, 2),
            'precioDetalleUnitario' => number_format($precioDetalleUnitario, 2),
            'precioMayoreoUnitario' => number_format($precioMayoreoUnitario, 2),
            'precioEspecialUnitario' => number_format($precioEspecialUnitario, 2),
            'EstaOfertado' => $producto['EstaOfertado'] === '1'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'productos' => $productosFormateados,
        'total' => count($productosFormateados)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>