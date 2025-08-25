<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once '../config/database.php';

try {
    // Manejar descarga de archivos
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'descargar') {
        descargarArchivo();
        exit();
    }
    
    // Obtener datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Acción no especificada');
    }
    
    $action = $input['action'];
    
    switch ($action) {
        case 'obtener_estadisticas':
            $result = obtenerEstadisticas();
            break;
            
        case 'exportar_pedidos':
            $result = exportarPedidos();
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Función para obtener estadísticas
function obtenerEstadisticas() {
    global $pdo;
    
    try {
        error_log('=== OBTENIENDO ESTADÍSTICAS ===');
        
        // Contar pedidos finalizados
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tblregistrodepedidos WHERE Ok = '1'");
        $stmt->execute();
        $pedidosFinalizados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        error_log('Pedidos finalizados encontrados: ' . $pedidosFinalizados);
        
        // Obtener fecha de última exportación
        $ultimaExportacion = 'Nunca';
        $archivoLog = '../../exports/ultima_exportacion.log';
        if (file_exists($archivoLog)) {
            $ultimaExportacion = date('d/m/Y H:i', filemtime($archivoLog));
            error_log('Última exportación: ' . $ultimaExportacion);
        } else {
            error_log('No existe archivo de log de exportación');
        }
        
        $resultado = [
            'success' => true,
            'data' => [
                'pedidos_finalizados' => $pedidosFinalizados,
                'ultima_exportacion' => $ultimaExportacion
            ]
        ];
        
        error_log('Estadísticas obtenidas correctamente: ' . json_encode($resultado));
        return $resultado;
        
    } catch (Exception $e) {
        error_log('Error al obtener estadísticas: ' . $e->getMessage());
        throw new Exception('Error al obtener estadísticas: ' . $e->getMessage());
    }
}

// Función principal de exportación
function exportarPedidos() {
    global $pdo;
    
    try {
        error_log('=== INICIANDO EXPORTACIÓN DE PEDIDOS ===');
        
        // Construir consulta SQL
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
                dp.Observaciones,
                c.TipoDeCliente,
                c.Nombre as NombreCliente,
                c.Establecimiento,
                c.Direccion,
                c.IDMunicipio,
                c.IDDepartamento,
                c.UbicacionGPS,
                c.IDempleado,
                c.IDZona,
                c.TelFijo,
                c.TelMovil,
                c.DUI,
                c.NIT,
                c.NRC,
                c.GiroComercial,
                c.TipoDocumentoEstablecido,
                c.CondicionEstablecida
            FROM tblregistrodepedidos rp
            INNER JOIN tbldetalledepedido dp ON rp.CodigoSIN = dp.CodigoSIN
            INNER JOIN tblcatalogodeclientes c ON rp.CodigoCli = c.CodigoCli
            WHERE rp.Ok = '1'
            ORDER BY rp.CodigoSIN, dp.CodigoProd
        ";
        
        error_log('Ejecutando consulta SQL...');
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('Pedidos encontrados: ' . count($pedidos));
        
        if (empty($pedidos)) {
            error_log('No se encontraron pedidos finalizados');
            throw new Exception('No se encontraron pedidos finalizados para exportar');
        }
        
        // Generar archivo de exportación
        error_log('Generando archivo de exportación...');
        $archivoGenerado = generarArchivoExportacion($pedidos);
        
        error_log('Archivo generado exitosamente: ' . json_encode($archivoGenerado));
        
        $resultado = [
            'success' => true,
            'data' => $archivoGenerado
        ];
        
        error_log('=== EXPORTACIÓN COMPLETADA ===');
        return $resultado;
        
    } catch (Exception $e) {
        error_log('=== ERROR EN EXPORTACIÓN ===');
        error_log('Error: ' . $e->getMessage());
        error_log('Trace: ' . $e->getTraceAsString());
        throw new Exception('Error al exportar pedidos: ' . $e->getMessage());
    }
}

// Función para generar el archivo de exportación
function generarArchivoExportacion($pedidos) {
    try {
        // Crear directorio de exportaciones si no existe
        $dirExports = '../../exports';
        if (!file_exists($dirExports)) {
            mkdir($dirExports, 0755, true);
        }
        
        // Generar nombre de archivo único
        $timestamp = date('YmdHis');
        $nombreArchivo = "pedidos_export_{$timestamp}.txt";
        $rutaArchivo = $dirExports . '/' . $nombreArchivo;
        
        // Abrir archivo para escritura
        $archivo = fopen($rutaArchivo, 'w');
        if (!$archivo) {
            throw new Exception('No se pudo crear el archivo de exportación');
        }
        
        // Escribir encabezados
        $encabezados = [
            'CodigoSIN', 'FechaIngreso', 'HoraIngreso', 'Terminal', 'Condicion', 'Plazo',
            'CodigoCli', 'IDVendedor', 'Ok', 'Anulada', 'Notas', 'IDProducto', 'CodigoProd',
            'TV', 'Cantidad', 'Bonificacion', 'PrecioVenta', 'Descuento', 'AgregarOferta',
            'Observaciones', 'TipoDeCliente', 'NombreCliente', 'Establecimiento', 'Direccion',
            'IDMunicipio', 'IDDepartamento', 'UbicacionGPS', 'IDempleado', 'IDZona',
            'TelFijo', 'TelMovil', 'DUI', 'NIT', 'NRC', 'GiroComercial',
            'TipoDocumentoEstablecido', 'CondicionEstablecida'
        ];
        
        // Escribir línea de encabezados (opcional, comentar si no se desea)
        fwrite($archivo, '"' . implode('","', $encabezados) . '"' . "\n");
        
        // Definir campos que NO deben llevar comillas (campos numéricos y fechas)
        $camposSinComillas = [
            'FechaIngreso', 'HoraIngreso', 'Condicion', 'Plazo', 'IDVendedor', 'Ok', 'Anulada', 
            'Cantidad', 'Bonificacion', 'Descuento', 'AgregarOferta', 'TipoDeCliente', 'IDMunicipio', 'IDDepartamento', 
            'IDempleado', 'IDZona', 'TipoDocumentoEstablecido', 'CondicionEstablecida'
        ];
        
        // Escribir datos
        foreach ($pedidos as $pedido) {
            $linea = [];
            
            foreach ($encabezados as $campo) {
                $valor = $pedido[$campo] ?? '';
                
                // Formatear valores especiales
                if ($campo === 'FechaIngreso' && $valor) {
                    // La fecha viene en formato d/m/Y desde la BD, necesitamos convertirla
                    try {
                        // Intentar parsear con el formato esperado d/m/Y
                        $fecha = DateTime::createFromFormat('d/m/Y', $valor);
                        if ($fecha !== false) {
                            // Formatear como d/m/Y H:i:s con hora 0:00:00
                            $valor = $fecha->format('j/n/Y') . ' 0:00:00';
                        } else {
                            // Fallback: intentar con otros formatos comunes
                            $timestamp = strtotime($valor);
                            if ($timestamp !== false) {
                                $valor = date('j/n/Y', $timestamp) . ' 0:00:00';
                            } else {
                                // Si todo falla, usar fecha actual
                                $valor = date('j/n/Y') . ' 0:00:00';
                            }
                        }
                    } catch (Exception $e) {
                        // En caso de excepción, usar fecha actual
                        $valor = date('j/n/Y') . ' 0:00:00';
                    }
                } elseif ($campo === 'HoraIngreso' && $valor) {
                    $valor = date('j/n/Y G:i:s', strtotime('1899-12-30 ' . $valor));
                } elseif ($campo === 'PrecioVenta' && is_numeric($valor)) {
                    $valor = number_format($valor, 4, '.', '');
                } elseif ($campo === 'Descuento' && is_numeric($valor)) {
                    $valor = number_format($valor, 2, '.', '');
                } elseif ($campo === 'UbicacionGPS' && $valor) {
                    // Mantener formato de URL de Google Maps
                    $valor = trim($valor);
                } elseif ($campo === 'AgregarOferta') {
                    // Convertir TRUE/FALSE a 1/0
                    if (strtoupper($valor) === 'TRUE') {
                        $valor = '1';
                    } elseif (strtoupper($valor) === 'FALSE') {
                        $valor = '0';
                    }
                }
                
                // Manejar campos vacíos: convertir a vacío real (no "")
                if ($valor === null || $valor === '' || $valor === 'NULL') {
                    $valor = '';
                }
                
                // Aplicar comillas según el tipo de campo
                if (in_array($campo, $camposSinComillas)) {
                    // Campos numéricos y fechas sin comillas
                    // Si está vacío, agregar sin comillas
                    $linea[] = $valor === '' ? '' : $valor;
                } else {
                    // Campos de texto con comillas
                    if ($valor === '') {
                        // Campo vacío sin comillas
                        $linea[] = '';
                    } else {
                        // Escapar comillas en el valor
                        $valor = str_replace('"', '""', $valor);
                        $linea[] = '"' . $valor . '"';
                    }
                }
            }
            
            fwrite($archivo, implode(',', $linea) . "\n");
        }
        
        fclose($archivo);
        
        // Obtener información del archivo
        $tamanoArchivo = filesize($rutaArchivo);
        $tamanoFormateado = formatearTamano($tamanoArchivo);
        
        // Actualizar log de última exportación
        file_put_contents($dirExports . '/ultima_exportacion.log', date('Y-m-d H:i:s'));
        
        return [
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $nombreArchivo, // Solo el nombre para seguridad
            'total_registros' => count($pedidos),
            'tamano_archivo' => $tamanoFormateado,
            'fecha_generacion' => date('d/m/Y H:i:s')
        ];
        
    } catch (Exception $e) {
        throw new Exception('Error al generar archivo: ' . $e->getMessage());
    }
}

// Función para descargar archivo
function descargarArchivo() {
    if (!isset($_GET['archivo'])) {
        http_response_code(400);
        echo 'Archivo no especificado';
        return;
    }
    
    $nombreArchivo = basename($_GET['archivo']); // Seguridad: solo nombre de archivo
    $rutaArchivo = '../../exports/' . $nombreArchivo;
    
    if (!file_exists($rutaArchivo)) {
        http_response_code(404);
        echo 'Archivo no encontrado';
        return;
    }
    
    // Verificar que sea un archivo de exportación válido
    if (!preg_match('/^pedidos_export_.*\.txt$/', $nombreArchivo)) {
        http_response_code(403);
        echo 'Archivo no autorizado';
        return;
    }
    
    // Configurar headers para descarga
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . filesize($rutaArchivo));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Enviar archivo
    readfile($rutaArchivo);
}

// Función auxiliar para formatear tamaño de archivo
function formatearTamano($bytes) {
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($unidades) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $unidades[$i];
}

// Función auxiliar para limpiar datos
function limpiarDato($valor) {
    if ($valor === null || $valor === '') {
        return '';
    }
    
    // Convertir a string y limpiar
    $valor = (string) $valor;
    $valor = trim($valor);
    
    // Escapar caracteres especiales para CSV
    if (strpos($valor, '"') !== false || strpos($valor, ',') !== false || strpos($valor, "\n") !== false) {
        $valor = '"' . str_replace('"', '""', $valor) . '"';
    }
    
    return $valor;
}

?>