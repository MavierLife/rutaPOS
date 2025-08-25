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
    
    // Debug: logging de la petición recibida (comentado para evitar interferencia con JSON)
    // error_log('=== DEBUG PETICIÓN RECIBIDA ===');
    // error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
    // error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'No definido'));
    // error_log('Raw input: ' . file_get_contents('php://input'));
    // error_log('Parsed input: ' . json_encode($input));
    // error_log('JSON decode error: ' . json_last_error_msg());
    
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
        // error_log('=== OBTENIENDO ESTADÍSTICAS ===');
        
        // Contar pedidos finalizados
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tblregistrodepedidos WHERE Ok = '1'");
        $stmt->execute();
        $pedidosFinalizados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // error_log('Pedidos finalizados encontrados: ' . $pedidosFinalizados);
        
        // Obtener fecha de última exportación
        $ultimaExportacion = 'Nunca';
        $archivoLog = '../../exports/ultima_exportacion.log';
        if (file_exists($archivoLog)) {
            $ultimaExportacion = date('d/m/Y H:i', filemtime($archivoLog));
            // error_log('Última exportación: ' . $ultimaExportacion);
        } else {
            // error_log('No existe archivo de log de exportación');
        }
        
        $resultado = [
            'success' => true,
            'data' => [
                'pedidos_finalizados' => $pedidosFinalizados,
                'ultima_exportacion' => $ultimaExportacion
            ]
        ];
        
        // error_log('Estadísticas obtenidas correctamente: ' . json_encode($resultado));
        return $resultado;
        
    } catch (Exception $e) {
        // error_log('Error al obtener estadísticas: ' . $e->getMessage());
        throw new Exception('Error al obtener estadísticas: ' . $e->getMessage());
    }
}

// Función principal de exportación
function exportarPedidos() {
    global $pdo;
    
    try {
        // error_log('=== INICIANDO EXPORTACIÓN DE PEDIDOS ===');
        
        // Obtener terminal de la sesión activa
        $terminalActiva = $_SESSION['equipo_asignado'] ?? null;
        
        // Si no hay terminal asignada en la sesión, consultar tblconfiguraciones
        if (!$terminalActiva) {
            // error_log('No hay terminal asignada en la sesión, consultando tblconfiguraciones...');
            
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                throw new Exception('No se ha iniciado sesión correctamente. Falta información del usuario.');
            }
            
            $stmt_config = $pdo->prepare("SELECT EquipoAsigna FROM tblconfiguraciones WHERE UsuarioAsigna = ?");
            $stmt_config->execute([$userId]);
            $config = $stmt_config->fetch();
            
            if ($config && !empty($config['EquipoAsigna'])) {
                $terminalActiva = $config['EquipoAsigna'];
                $_SESSION['equipo_asignado'] = $terminalActiva; // Actualizar sesión para futuras consultas
                // error_log('Terminal obtenida de tblconfiguraciones: ' . $terminalActiva);
            } else {
                throw new Exception('El usuario no tiene un equipo asignado en la configuración. Por favor, contacte al administrador para asignar un equipo.');
            }
        }
        
        // error_log('Terminal activa: ' . $terminalActiva);
        
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
            WHERE rp.Ok = '1' AND LEFT(rp.CodigoSIN, 5) = :terminal
            ORDER BY rp.CodigoSIN, dp.CodigoProd
        ";
        
        // error_log('Ejecutando consulta SQL con filtro de terminal...');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':terminal', $terminalActiva, PDO::PARAM_STR);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // error_log('Pedidos encontrados: ' . count($pedidos));
        
        if (empty($pedidos)) {
            error_log('No se encontraron pedidos finalizados');
            throw new Exception('No se encontraron pedidos finalizados para exportar');
        }
        
        // Generar archivo de exportación
        error_log('Generando archivo de exportación...');
        $archivoGenerado = generarArchivoExportacion($pedidos);
        
        error_log('Archivo generado exitosamente: ' . json_encode($archivoGenerado));
        
        // Eliminar registros exportados de la base de datos
        error_log('Eliminando registros exportados de la base de datos...');
        $codigosSINExportados = array_unique(array_column($pedidos, 'CodigoSIN'));
        $codigosSINExportados = array_values($codigosSINExportados);
        
        // Validar que hay códigos SIN para eliminar
        if (empty($codigosSINExportados)) {
            error_log('No hay códigos SIN para eliminar');
            throw new Exception('No se encontraron códigos SIN válidos para eliminar');
        }
        
        // Agregar logging para debug
        error_log('Códigos SIN a eliminar: ' . implode(', ', $codigosSINExportados));
        
        eliminarRegistrosExportados($pdo, $codigosSINExportados, $terminalActiva);
        
        error_log('Registros eliminados exitosamente. Total CodigoSIN eliminados: ' . count($codigosSINExportados));
        
        $resultado = [
            'success' => true,
            'data' => array_merge($archivoGenerado, [
                'registros_eliminados' => count($codigosSINExportados)
            ])
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
    global $pdo;
    
    try {
        // Crear directorio de exportaciones si no existe
        $dirExports = '../../exports';
        if (!file_exists($dirExports)) {
            mkdir($dirExports, 0755, true);
        }
        
        // Obtener terminal para el nombre del archivo
        $terminal = $_SESSION['equipo_asignado'] ?? null;
        
        // Si no hay terminal en la sesión, consultar tblconfiguraciones
        if (!$terminal) {
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $stmt_config = $pdo->prepare("SELECT EquipoAsigna FROM tblconfiguraciones WHERE UsuarioAsigna = ?");
                $stmt_config->execute([$userId]);
                $config = $stmt_config->fetch();
                
                if ($config && !empty($config['EquipoAsigna'])) {
                    $terminal = $config['EquipoAsigna'];
                } else {
                    throw new Exception('No se pudo determinar el equipo para generar el nombre del archivo.');
                }
            } else {
                throw new Exception('No se pudo determinar el equipo para generar el nombre del archivo.');
            }
        }
        
        $timestamp = date('ymd_His'); // Formato: YYMMDD_HHMMSS
        $nombreArchivo = "{$terminal}-POSPedidos-{$timestamp}.txt";
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
    if (!preg_match('/^EQP\d+-POSPedidos-\d{6}_\d{6}\.txt$/', $nombreArchivo)) {
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

// Función para eliminar registros exportados de la base de datos
function eliminarRegistrosExportados($pdo, $codigosSIN, $terminal) {
    try {
        // error_log('=== INICIANDO ELIMINACIÓN DE REGISTROS EXPORTADOS ===');
        // error_log('CodigosSIN a eliminar: ' . implode(', ', $codigosSIN));
        // error_log('Terminal: ' . $terminal);
        
        // Verificar que hay códigos para eliminar
        if (empty($codigosSIN)) {
            // error_log('No hay códigos SIN para eliminar');
            return ['registros_principales' => 0, 'registros_detalle' => 0];
        }

        // Reindexar el array para asegurar índices secuenciales para PDO
        $codigosSIN = array_values($codigosSIN);
        
        // Validar que los CodigosSIN existen antes de eliminar
        $placeholders = str_repeat('?,', count($codigosSIN) - 1) . '?';
        $sqlVerificar = "SELECT CodigoSIN FROM tblregistrodepedidos WHERE CodigoSIN IN ($placeholders) AND Ok = '1'";
        $stmtVerificar = $pdo->prepare($sqlVerificar);
        $stmtVerificar->execute($codigosSIN);
        $registrosExistentes = $stmtVerificar->fetchAll(PDO::FETCH_COLUMN);
        
        // error_log('Registros existentes a eliminar: ' . implode(', ', $registrosExistentes));
        
        if (empty($registrosExistentes)) {
            // error_log('No se encontraron registros finalizados para eliminar');
            return ['registros_principales' => 0, 'registros_detalle' => 0];
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        // error_log('Transacción iniciada');
        
        // Primero eliminar de tbldetalledepedido (tabla hija)
        $sqlDetalle = "DELETE FROM tbldetalledepedido WHERE CodigoSIN IN ($placeholders)";
        // error_log('Ejecutando SQL detalle: ' . $sqlDetalle);
        // error_log('Parámetros detalle: ' . implode(', ', $codigosSIN));
        
        $stmtDetalle = $pdo->prepare($sqlDetalle);
        $stmtDetalle->execute($codigosSIN);
        $registrosDetalleEliminados = $stmtDetalle->rowCount();
        
        // error_log('Registros eliminados de tbldetalledepedido: ' . $registrosDetalleEliminados);
        
        // Luego eliminar de tblregistrodepedidos (tabla padre)
        $sqlRegistro = "DELETE FROM tblregistrodepedidos WHERE CodigoSIN IN ($placeholders) AND Ok = '1'";
        // error_log('Ejecutando SQL registro: ' . $sqlRegistro);
        // error_log('Parámetros registro: ' . implode(', ', $codigosSIN));
        
        $stmtRegistro = $pdo->prepare($sqlRegistro);
        $stmtRegistro->execute($codigosSIN);
        $registrosPrincipalesEliminados = $stmtRegistro->rowCount();
        
        // error_log('Registros eliminados de tblregistrodepedidos: ' . $registrosPrincipalesEliminados);
        
        // Confirmar transacción
        $pdo->commit();
        // error_log('Transacción confirmada');
        
        // error_log('=== ELIMINACIÓN COMPLETADA EXITOSAMENTE ===');
        // error_log('Total registros principales eliminados: ' . $registrosPrincipalesEliminados);
        // error_log('Total registros de detalle eliminados: ' . $registrosDetalleEliminados);
        
        return [
            'registros_principales' => $registrosPrincipalesEliminados,
            'registros_detalle' => $registrosDetalleEliminados
        ];
        
    } catch (PDOException $e) {
        // Revertir transacción en caso de error de base de datos
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            // error_log('Transacción revertida debido a error de PDO');
        }
        
        // error_log('=== ERROR PDO EN ELIMINACIÓN DE REGISTROS ===');
        // error_log('Error PDO: ' . $e->getMessage());
        // error_log('Código de error: ' . $e->getCode());
        // error_log('Trace: ' . $e->getTraceAsString());
        
        throw new Exception('Error de base de datos al eliminar registros: ' . $e->getMessage());
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error general
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            // error_log('Transacción revertida debido a error general');
        }
        
        // error_log('=== ERROR GENERAL EN ELIMINACIÓN DE REGISTROS ===');
        // error_log('Error: ' . $e->getMessage());
        // error_log('Trace: ' . $e->getTraceAsString());
        
        throw new Exception('Error al eliminar registros exportados: ' . $e->getMessage());
    }
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