<?php
/**
 * Modelo/conexion.php
 * Clase DB (Singleton) con conexión PDO a MySQL.
 * Expone: insertSeguro(), updateSeguro() y query().
 * IMPORTANTE: este archivo NO debe imprimir nada (ni echo ni espacios fuera de PHP).
 */
class DB
{
    private static ?DB $instancia = null;
    private PDO $pdo;

    // Ajusta estos datos si tu MySQL es distinto (WAMP: root sin clave)
    private const HOST = 'localhost';
    private const BD   = 'productosdb';
    private const USER = 'root';
    private const PASS = '';

    // Constructor privado: solo se crea con DB::getInstancia()
    private function __construct()
    {
        $dsn = 'mysql:host=' . self::HOST . ';dbname=' . self::BD . ';charset=utf8mb4';
        $this->pdo = new PDO($dsn, self::USER, self::PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // errores como excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // filas como arreglo asociativo
            PDO::ATTR_EMULATE_PREPARES   => false,                   // sentencias preparadas reales
        ]);
    }

    public static function getInstancia(): DB
    {
        if (self::$instancia === null) {
            self::$instancia = new DB();
        }
        return self::$instancia;
    }

    // Valida nombres de tabla/columna (PDO no permite parametrizarlos)
    private function identificador(string $nombre): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $nombre)) {
            throw new InvalidArgumentException("Identificador no válido: $nombre");
        }
        return "`$nombre`";
    }

    /**
     * INSERT con sentencia preparada. Devuelve el ID insertado.
     * Ej: insertSeguro('productos', ['codigo' => 'A1', 'producto' => 'Lápiz', ...]);
     */
    public function insertSeguro(string $tabla, array $datos): int
    {
        $columnas = array_map([$this, 'identificador'], array_keys($datos));
        $marcas   = array_map(fn($c) => ':' . $c, array_keys($datos));

        $sql = 'INSERT INTO ' . $this->identificador($tabla)
             . ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $marcas) . ')';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($datos);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * UPDATE con sentencia preparada. Devuelve las filas afectadas.
     * Ej: updateSeguro('productos', ['precio' => 5], 'id', 3);
     */
    public function updateSeguro(string $tabla, array $datos, string $campoId, $valorId): int
    {
        $sets = [];
        foreach (array_keys($datos) as $col) {
            $sets[] = $this->identificador($col) . ' = :' . $col;
        }

        $sql = 'UPDATE ' . $this->identificador($tabla)
             . ' SET ' . implode(', ', $sets)
             . ' WHERE ' . $this->identificador($campoId) . ' = :__id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($datos + ['__id' => $valorId]);
        return $stmt->rowCount();
    }

    /**
     * Ejecuta cualquier consulta preparada. Si es SELECT devuelve las filas.
     * Ej: query('SELECT * FROM productos WHERE id = :id', [':id' => 1]);
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->columnCount() > 0 ? $stmt->fetchAll() : [];
    }
}
