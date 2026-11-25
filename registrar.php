<?php
/**
 * registrar.php
 * Enrutador: recibe $_POST['Accion'] y con un switch decide qué método del
 * controlador llamar. Salida: SOLO json_encode (success, message, accion, errors).
 * No debe haber echo/print/var_dump ni espacios fuera de las etiquetas PHP.
 */
ob_start();                                   // atrapa cualquier "ruido" accidental
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/Modelo/conexion.php';
require_once __DIR__ . '/Modelo/SanitizarEntrada.php';
require_once __DIR__ . '/Modelo/Productos.php';
require_once __DIR__ . '/Controlador/ProductoController.php';

$Accion   = $_POST['Accion'] ?? '';
$response = [];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }

    // Instanciamos el controlador pasándole su dependencia (la conexión)
    $controller = new ProductoController(DB::getInstancia());

    // El enrutador solo decide qué método del controlador llamar
    switch ($Accion) {
        case 'Guardar':
            $response = $controller->guardar($_POST);
            break;

        case 'Modificar':
            $response = $controller->modificar($_POST);
            break;

        case 'Buscar':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
            $response = $controller->buscar($id);
            break;

        case 'Eliminar':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
            $response = $controller->eliminar($id);
            break;

        default:
            $response = [
                'success' => false,
                'message' => 'Acción no válida o no proporcionada',
                'accion'  => 'Desconocida',
                'errors'  => [],
            ];
            break;
    }
} catch (RuntimeException $e) {
    // Errores "esperados" (por ejemplo, método HTTP no permitido)
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'accion'  => 'Desconocida',
        'errors'  => [],
    ];
} catch (Throwable $e) {
    error_log('CRUD productos: ' . $e->getMessage());   // el detalle técnico queda solo en el log
    $response = [
        'success' => false,
        'message' => 'Error del servidor. Verifica que MySQL esté activo y que exista la base productosdb.',
        'accion'  => $Accion !== '' ? $Accion : 'Desconocida',
        'errors'  => [],
    ];
}

if (ob_get_length()) ob_clean();               // descarta lo que se haya impreso por error
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
