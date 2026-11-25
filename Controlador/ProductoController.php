<?php
/**
 * Controlador/ProductoController.php
 * Patrón Controlador: registrar.php solo decide QUÉ método llamar (switch);
 * este controlador decide CÓMO responder. Siempre devuelve un arreglo con:
 * success, message, accion y errors.
 */
class ProductoController
{
    private Producto $producto;

    public function __construct(DB $db)
    {
        $this->producto = new Producto($db);
    }

    public function guardar(array $datosPost): array
    {
        $this->producto->recibirDatos($datosPost);
        $this->producto->setRequiredFields(['Codigo', 'Producto', 'Precio', 'Cantidad']);

        if (!$this->producto->validar('Guardar')) {
            return [
                'success' => false,
                'message' => 'Producto no creado',
                'errors'  => $this->producto->getErrores(),
                'accion'  => 'Guardar',
            ];
        }

        $nuevoId = $this->producto->guardar();
        return [
            'success' => true,
            'message' => 'Producto creado correctamente',
            'accion'  => 'Guardar',
            'id'      => $nuevoId,
            'errors'  => [],
        ];
    }

    public function modificar(array $datosPost): array
    {
        $this->producto->recibirDatos($datosPost);
        $this->producto->setRequiredFields(['Codigo', 'Producto', 'Precio', 'Cantidad']);

        if (!$this->producto->validar('Modificar')) {
            return [
                'success' => false,
                'message' => 'Producto no modificado',
                'errors'  => $this->producto->getErrores(),
                'accion'  => 'Modificar',
            ];
        }

        // Verificar que el producto exista antes de actualizar
        if ($this->producto->buscarPorId((int)$datosPost['idp']) === null) {
            return [
                'success' => false,
                'message' => 'El producto que intentas modificar ya no existe',
                'errors'  => ['idp' => 'Producto no encontrado.'],
                'accion'  => 'Modificar',
            ];
        }

        $this->producto->editar();
        return [
            'success' => true,
            'message' => 'Producto modificado correctamente',
            'accion'  => 'Modificar',
            'errors'  => [],
        ];
    }

    // Busca UN producto por id y devuelve sus campos (id, codigo, producto, precio, cantidad)
    public function buscar(?int $id): array
    {
        if ($id === null || $id <= 0) {
            return ['success' => false, 'message' => 'ID de producto no válido', 'errors' => ['id' => 'ID no válido.'], 'accion' => 'Buscar'];
        }

        $fila = $this->producto->buscarPorId($id);
        if ($fila === null) {
            return ['success' => false, 'message' => 'Producto no encontrado', 'errors' => ['id' => 'No existe ese producto.'], 'accion' => 'Buscar'];
        }

        return array_merge(
            ['success' => true, 'message' => 'Producto encontrado', 'accion' => 'Buscar', 'errors' => []],
            $fila
        );
    }

    public function eliminar(?int $id): array
    {
        if ($id === null || $id <= 0 || $this->producto->buscarPorId($id) === null) {
            return ['success' => false, 'message' => 'Producto no encontrado', 'errors' => ['id' => 'No existe ese producto.'], 'accion' => 'Eliminar'];
        }

        $this->producto->eliminar($id);
        return ['success' => true, 'message' => 'Producto eliminado correctamente', 'accion' => 'Eliminar', 'errors' => []];
    }
}
