<?php
/**
 * listar.php
 * Devuelve las filas <tr> de la tabla (HTML). Recibe por POST el texto a buscar
 * en "producto" (vacío = todos). script.js las inserta dentro de <tbody id="resultado">.
 */
require_once __DIR__ . '/Modelo/conexion.php';
require_once __DIR__ . '/Modelo/SanitizarEntrada.php';
require_once __DIR__ . '/Modelo/Productos.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $termino = SanitizarEntrada::texto($_POST['producto'] ?? '');
    $filas   = (new Producto(DB::getInstancia()))->listar($termino);

    if (empty($filas)) {
        echo '<tr><td colspan="7" class="text-center text-muted py-4">No se encontraron productos.</td></tr>';
        exit;
    }

    foreach ($filas as $fila) {
        $id       = (int)$fila['id'];
        $codigo   = htmlspecialchars($fila['codigo'], ENT_QUOTES, 'UTF-8');
        $producto = htmlspecialchars($fila['producto'], ENT_QUOTES, 'UTF-8');
        $precio   = number_format((float)$fila['precio'], 2);
        $cantidad = (int)$fila['cantidad'];
        $textoCantidad = $cantidad === 0 ? '<span class="sin-stock">Agotado</span>' : $cantidad;

        echo "<tr>";
        echo "<td>{$id}</td>";
        echo "<td>{$codigo}</td>";
        echo "<td>{$producto}</td>";
        echo "<td>{$precio}</td>";
        echo "<td>{$textoCantidad}</td>";
        echo "<td><button name='Editar' type='button' class='btn btn-success btn-sm' data-id='{$id}'>Editar</button></td>";
        echo "<td><button name='Eliminar' type='button' class='btn btn-danger btn-sm' data-id='{$id}'>Eliminar</button></td>";
        echo "</tr>";
    }
} catch (Throwable $e) {
    error_log('CRUD productos (listar): ' . $e->getMessage());
    http_response_code(500);                   // script.js lo detecta con response.ok
    echo 'Error al listar productos.';
}
