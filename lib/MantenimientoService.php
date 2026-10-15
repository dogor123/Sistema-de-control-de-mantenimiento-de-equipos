<?php
require_once __DIR__ . '/Database.php';

/**
 * Lógica de negocio de Mantenimientos (acceso a MySQL).
 */
class MantenimientoService
{
    public const TIPOS   = ['PREVENTIVO', 'CORRECTIVO', 'PREDICTIVO'];
    public const ESTADOS = ['PROGRAMADO', 'EN_PROCESO', 'FINALIZADO', 'CANCELADO'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /**
     * Registra un mantenimiento.
     * - El equipo debe existir.
     * - El técnico debe estar registrado.
     * - Tipo, fecha y estado son obligatorios.
     */
    public function registrar(array $d): array
    {
        $equipoId  = filter_var($d['equipoId']  ?? null, FILTER_VALIDATE_INT);
        $tecnicoId = filter_var($d['tecnicoId'] ?? null, FILTER_VALIDATE_INT);
        $tipo   = strtoupper(trim((string)($d['tipoMantenimiento'] ?? '')));
        $fecha  = trim((string)($d['fecha'] ?? ''));
        $estado = strtoupper(trim((string)($d['estado'] ?? '')));

        if ($equipoId === false || $equipoId === null) {
            throw new ValidacionException('equipoId es obligatorio y debe ser numérico.');
        }
        if ($tecnicoId === false || $tecnicoId === null) {
            throw new ValidacionException('tecnicoId es obligatorio y debe ser numérico.');
        }
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new ValidacionException('tipoMantenimiento inválido. Valores: ' . implode(', ', self::TIPOS));
        }
        $f = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$f || $f->format('Y-m-d') !== $fecha) {
            throw new ValidacionException('fecha inválida. Formato requerido: YYYY-MM-DD.');
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new ValidacionException('estado inválido. Valores: ' . implode(', ', self::ESTADOS));
        }

        if (!$this->existe('equipos', $equipoId)) {
            throw new ValidacionException("No existe el equipo con id $equipoId. Solo se registran mantenimientos de equipos existentes.", 404);
        }
        if (!$this->existe('tecnicos', $tecnicoId)) {
            throw new ValidacionException("No existe el técnico con id $tecnicoId. El mantenimiento debe asociarse a un técnico registrado.", 404);
        }

        $st = $this->db->prepare(
            'INSERT INTO mantenimientos (equipo_id, tecnico_id, tipo_mantenimiento, fecha, estado) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$equipoId, $tecnicoId, $tipo, $fecha, $estado]);

        return $this->consultar(['id' => (int)$this->db->lastInsertId()])[0];
    }

    /**
     * Consulta mantenimientos con filtros opcionales: id, equipoId, codigoEquipo, tecnicoId.
     */
    public function consultar(array $filtros = []): array
    {
        $sql = 'SELECT m.id, m.equipo_id, e.codigo AS equipo_codigo, e.nombre AS equipo_nombre,
                       m.tecnico_id, t.nombre AS tecnico_nombre, t.documento AS tecnico_documento,
                       m.tipo_mantenimiento, m.fecha, m.estado
                FROM mantenimientos m
                JOIN equipos  e ON e.id = m.equipo_id
                JOIN tecnicos t ON t.id = m.tecnico_id
                WHERE 1 = 1';
        $p = [];
        if (!empty($filtros['id']))           { $sql .= ' AND m.id = ?';         $p[] = (int)$filtros['id']; }
        if (!empty($filtros['equipoId']))     { $sql .= ' AND m.equipo_id = ?';  $p[] = (int)$filtros['equipoId']; }
        if (!empty($filtros['codigoEquipo'])) { $sql .= ' AND e.codigo = ?';     $p[] = strtoupper(trim($filtros['codigoEquipo'])); }
        if (!empty($filtros['tecnicoId']))    { $sql .= ' AND m.tecnico_id = ?'; $p[] = (int)$filtros['tecnicoId']; }
        $sql .= ' ORDER BY m.fecha DESC, m.id DESC';

        $st = $this->db->prepare($sql);
        $st->execute($p);

        return array_map(fn($r) => [
            'id'                => (int)$r['id'],
            'equipoId'          => (int)$r['equipo_id'],
            'equipoCodigo'      => $r['equipo_codigo'],
            'equipoNombre'      => $r['equipo_nombre'],
            'tecnicoId'         => (int)$r['tecnico_id'],
            'tecnicoNombre'     => $r['tecnico_nombre'],
            'tecnicoDocumento'  => $r['tecnico_documento'],
            'tipoMantenimiento' => $r['tipo_mantenimiento'],
            'fecha'             => $r['fecha'],
            'estado'            => $r['estado'],
        ], $st->fetchAll());
    }

    public function listarTecnicos(): array
    {
        $filas = $this->db->query('SELECT id, documento, nombre, especialidad FROM tecnicos ORDER BY id')->fetchAll();
        foreach ($filas as &$f) { $f['id'] = (int)$f['id']; }
        return $filas;
    }

    private function existe(string $tabla, int $id): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM $tabla WHERE id = ?");
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }
}
