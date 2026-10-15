<?php
require_once __DIR__ . '/Database.php';

/**
 * Lógica de negocio de Equipos (acceso a MySQL).
 */
class EquipoService
{
    public const ESTADOS = ['ACTIVO', 'INACTIVO', 'EN_MANTENIMIENTO', 'DADO_DE_BAJA'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /** Registra un equipo. El código debe ser único. */
    public function registrar(string $codigo, string $nombre, string $tipo, string $estado = 'ACTIVO'): array
    {
        $codigo = strtoupper(trim($codigo));
        $nombre = trim($nombre);
        $tipo   = trim($tipo);
        $estado = strtoupper(trim($estado)) ?: 'ACTIVO';

        if ($codigo === '' || $nombre === '' || $tipo === '') {
            throw new ValidacionException('Código, nombre y tipo son obligatorios.');
        }
        $this->validarEstado($estado);

        if ($this->buscarPorCodigo($codigo)) {
            throw new ValidacionException("Ya existe un equipo con el código '$codigo'.");
        }

        try {
            $st = $this->db->prepare('INSERT INTO equipos (codigo, nombre, tipo, estado) VALUES (?, ?, ?, ?)');
            $st->execute([$codigo, $nombre, $tipo, $estado]);
        } catch (PDOException $e) {
            // 23000 = violación de restricción UNIQUE (por si hay concurrencia)
            if ($e->getCode() === '23000') {
                throw new ValidacionException("Ya existe un equipo con el código '$codigo'.");
            }
            throw $e;
        }
        return $this->buscarPorId((int)$this->db->lastInsertId());
    }

    public function buscarPorId(int $id): ?array
    {
        $st = $this->db->prepare('SELECT id, codigo, nombre, tipo, estado FROM equipos WHERE id = ?');
        $st->execute([$id]);
        return $this->normalizar($st->fetch());
    }

    public function buscarPorCodigo(string $codigo): ?array
    {
        $st = $this->db->prepare('SELECT id, codigo, nombre, tipo, estado FROM equipos WHERE codigo = ?');
        $st->execute([strtoupper(trim($codigo))]);
        return $this->normalizar($st->fetch());
    }

    public function listar(): array
    {
        $filas = $this->db->query('SELECT id, codigo, nombre, tipo, estado FROM equipos ORDER BY id')->fetchAll();
        return array_map([$this, 'normalizar'], $filas);
    }

    /** Actualiza nombre, tipo y/o estado (solo los campos enviados). */
    public function actualizar(int $id, array $datos): array
    {
        if (!$this->buscarPorId($id)) {
            throw new ValidacionException("No existe el equipo con id $id.", 404);
        }

        $permitidos = ['nombre', 'tipo', 'estado'];
        $sets = [];
        $valores = [];
        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }
            $valor = trim((string)$datos[$campo]);
            if ($valor === '') {
                throw new ValidacionException("El campo '$campo' no puede estar vacío.");
            }
            if ($campo === 'estado') {
                $valor = strtoupper($valor);
                $this->validarEstado($valor);
            }
            $sets[] = "$campo = ?";
            $valores[] = $valor;
        }

        if (!$sets) {
            throw new ValidacionException('Debe enviar al menos uno de estos campos: nombre, tipo, estado.');
        }

        $valores[] = $id;
        $this->db->prepare('UPDATE equipos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($valores);
        return $this->buscarPorId($id);
    }

    private function validarEstado(string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new ValidacionException('Estado inválido. Valores permitidos: ' . implode(', ', self::ESTADOS));
        }
    }

    private function normalizar($fila): ?array
    {
        if (!$fila) {
            return null;
        }
        $fila['id'] = (int)$fila['id'];
        return $fila;
    }
}
