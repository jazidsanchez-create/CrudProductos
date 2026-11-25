<?php
/**
 * Modelo/Productos.php
 * Clase Producto: recibe datos, valida y habla con la BD usando la clase DB.
 * Métodos principales: guardar(), editar(), buscarPorId(), listar(), eliminar().
 */
class Producto
{
    private DB $db;
    private array $datos = ['id' => 0, 'codigo' => '', 'producto' => '', 'precio' => null, 'cantidad' => null];
    private array $requeridos = [];
    private array $errores = [];

    public function __construct(DB $db)
    {
        $this->db = $db;
    }

    // Recibe $_POST (los inputs del formulario empiezan con mayúscula; el id viaja como "idp")
    public function recibirDatos(array $post): void
    {
        $this->datos = [
            'id'       => (int)($post['idp'] ?? 0),
            'codigo'   => SanitizarEntrada::texto($post['Codigo'] ?? ''),
            'producto' => SanitizarEntrada::texto($post['Producto'] ?? ''),
            'precio'   => isset($post['Precio'])   ? trim((string)$post['Precio'])   : null,
            'cantidad' => isset($post['Cantidad']) ? trim((string)$post['Cantidad']) : null,
        ];
    }

    // Campos que no pueden venir vacíos
    public function setRequiredFields(array $campos): void
    {
        $this->requeridos = $campos;
    }

    /**
     * Validación en el servidor (nunca confiar solo en el navegador).
     * Regla de negocio: al Guardar la cantidad mínima es 1; al Modificar puede ser 0 (agotado).
     */
    public function validar(string $accion): bool
    {
        $this->errores = [];

        if ($accion === 'Modificar' && $this->datos['id'] <= 0) {
            $this->errores['idp'] = 'No se indicó el producto a modificar.';
        }

        // Campos obligatorios
        $mapa = ['Codigo' => 'codigo', 'Producto' => 'producto', 'Precio' => 'precio', 'Cantidad' => 'cantidad'];
        foreach ($this->requeridos as $campo) {
            $valor = $this->datos[$mapa[$campo]] ?? null;
            if ($valor === null || $valor === '') {
                $this->errores[$campo] = "El campo $campo es obligatorio.";
            }
        }

        // Formato y longitud (solo si el campo no tiene ya un error)
        if (!isset($this->errores['Codigo']) && $this->largo($this->datos['codigo']) > 20) {
            $this->errores['Codigo'] = 'El código no puede superar 20 caracteres.';
        }
        if (!isset($this->errores['Producto']) && $this->largo($this->datos['producto']) > 100) {
            $this->errores['Producto'] = 'El producto no puede superar 100 caracteres.';
        }
        if (!isset($this->errores['Precio']) && (!is_numeric($this->datos['precio']) || (float)$this->datos['precio'] <= 0)) {
            $this->errores['Precio'] = 'El precio debe ser un número mayor que 0.';
        }

        $minimo = ($accion === 'Guardar') ? 1 : 0;
        if (!isset($this->errores['Cantidad'])
            && (filter_var($this->datos['cantidad'], FILTER_VALIDATE_INT) === false || (int)$this->datos['cantidad'] < $minimo)) {
            $this->errores['Cantidad'] = "La cantidad debe ser un entero mayor o igual a $minimo.";
        }

        // Código repetido (solo si el código ya es válido)
        if (!isset($this->errores['Codigo']) && $this->codigoExiste($this->datos['codigo'], $this->datos['id'])) {
            $this->errores['Codigo'] = 'Ya existe otro producto con ese código.';
        }

        return empty($this->errores);
    }

    public function getErrores(): array
    {
        return $this->errores;
    }

    // ¿Existe ese código en otro producto? ($excluirId = el producto que se está editando)
    public function codigoExiste(string $codigo, int $excluirId = 0): bool
    {
        $filas = $this->db->query(
            'SELECT id FROM productos WHERE codigo = :codigo AND id <> :id',
            [':codigo' => $codigo, ':id' => $excluirId]
        );
        return !empty($filas);
    }

    /** INSERT. Devuelve el id nuevo. */
    public function guardar(): int
    {
        return $this->db->insertSeguro('productos', $this->columnas());
    }

    /** UPDATE del producto cuyo id se recibió. */
    public function editar(): void
    {
        $this->db->updateSeguro('productos', $this->columnas(), 'id', $this->datos['id']);
    }

    /** SELECT de un solo producto (para cargarlo en el formulario). */
    public function buscarPorId(int $id): ?array
    {
        $filas = $this->db->query(
            'SELECT id, codigo, producto, precio, cantidad FROM productos WHERE id = :id',
            [':id' => $id]
        );
        return $filas[0] ?? null;
    }

    /** SELECT con búsqueda por nombre o código. Texto vacío = todos. */
    public function listar(string $termino = ''): array
    {
        $like = '%' . $termino . '%';
        return $this->db->query(
            'SELECT id, codigo, producto, precio, cantidad
               FROM productos
              WHERE producto LIKE :t1 OR codigo LIKE :t2
              ORDER BY id DESC',
            [':t1' => $like, ':t2' => $like]
        );
    }

    /** DELETE (reto extra de la guía). */
    public function eliminar(int $id): void
    {
        $this->db->query('DELETE FROM productos WHERE id = :id', [':id' => $id]);
    }

    // Largo en caracteres (usa mbstring si está activo; si no, cuenta bytes)
    private function largo(string $texto): int
    {
        return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
    }

    // Columnas listas para insertSeguro / updateSeguro
    private function columnas(): array
    {
        return [
            'codigo'   => $this->datos['codigo'],
            'producto' => $this->datos['producto'],
            'precio'   => (float)$this->datos['precio'],
            'cantidad' => (int)$this->datos['cantidad'],
        ];
    }
}
