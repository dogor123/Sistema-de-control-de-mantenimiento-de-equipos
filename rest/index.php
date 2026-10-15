<?php
/**
 * SERVICIO REST API - Equipos y Mantenimientos
 *
 *   PUT    /api/equipos/{id}          actualizarEquipo()        (nombre, tipo y/o estado)
 *   POST   /api/mantenimientos        registrarMantenimiento()
 *   GET    /api/mantenimientos        consultarMantenimientos() (?equipoId= &codigoEquipo= &tecnicoId=)
 *   GET    /api/mantenimientos/{id}   consultar un mantenimiento
 *   POST   /api/equipos               RegistrarEquipo()  -> ejecuta registrarEquipo del servicio SOAP
 *   GET    /api/tecnicos              (apoyo) técnicos registrados
 *
 * Respuesta JSON: { "exito": bool, "mensaje": string, "datos": ... }
 */
require_once __DIR__ . '/../lib/EquipoService.php';
require_once __DIR__ . '/../lib/MantenimientoService.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$config = require __DIR__ . '/../config/config.php';
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Ruta: todo lo que va después de /api ---
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pos  = strpos($ruta, '/api');
$ruta = $pos === false ? '' : substr($ruta, $pos + 4);
$partes = array_values(array_filter(explode('/', $ruta), 'strlen'));   // ej: ['equipos', '5']

$recurso = $partes[0] ?? '';
$id      = $partes[1] ?? null;

function responder(int $codigo, bool $exito, string $mensaje, $datos = null): void
{
    http_response_code($codigo);
    echo json_encode(['exito' => $exito, 'mensaje' => $mensaje, 'datos' => $datos], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Lee el cuerpo como JSON (o como formulario). */
function cuerpo(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    parse_str($raw, $form);
    return $form ?: $_POST;
}

try {
    // ------------------------------------------------------------- EQUIPOS
    if ($recurso === 'equipos') {

        // actualizarEquipo()
        if (in_array($metodo, ['PUT', 'PATCH'], true) && $id !== null) {
            if (!ctype_digit($id)) {
                responder(400, false, 'El id del equipo debe ser numérico.');
            }
            $equipo = (new EquipoService())->actualizar((int)$id, cuerpo());
            responder(200, true, 'Equipo actualizado correctamente.', $equipo);
        }

        // RegistrarEquipo() -> ejecuta el método registrarEquipo del servicio SOAP
        if ($metodo === 'POST' && $id === null) {
            $d = cuerpo();
            $cliente = new SoapClient($config['soap_url'] . '?wsdl', [
                'cache_wsdl' => WSDL_CACHE_NONE,
                'exceptions' => true,
                'encoding'   => 'UTF-8',
            ]);
            $r = $cliente->registrarEquipo(
                $d['codigo'] ?? '',
                $d['nombre'] ?? '',
                $d['tipo']   ?? '',
                $d['estado'] ?? 'ACTIVO'
            );
            if ($r->exito) {
                responder(201, true, $r->mensaje . ' (vía SOAP)', (array)$r->equipo);
            }
            $codigo = str_contains($r->mensaje, 'Ya existe') ? 409 : 400;
            responder($codigo, false, $r->mensaje);
        }
    }

    // ------------------------------------------------------ MANTENIMIENTOS
    if ($recurso === 'mantenimientos') {
        $svc = new MantenimientoService();

        // registrarMantenimiento()
        if ($metodo === 'POST' && $id === null) {
            $m = $svc->registrar(cuerpo());
            responder(201, true, 'Mantenimiento registrado correctamente.', $m);
        }

        // consultarMantenimientos()
        if ($metodo === 'GET') {
            if ($id !== null) {
                $lista = $svc->consultar(['id' => (int)$id]);
                $lista
                    ? responder(200, true, 'Mantenimiento encontrado.', $lista[0])
                    : responder(404, false, "No existe el mantenimiento con id $id.");
            }
            $lista = $svc->consultar([
                'equipoId'     => $_GET['equipoId']     ?? null,
                'codigoEquipo' => $_GET['codigoEquipo'] ?? null,
                'tecnicoId'    => $_GET['tecnicoId']    ?? null,
            ]);
            responder(200, true, count($lista) . ' mantenimiento(s) encontrado(s).', $lista);
        }
    }

    // ------------------------------------------------------------ TÉCNICOS
    if ($recurso === 'tecnicos' && $metodo === 'GET') {
        responder(200, true, 'Técnicos registrados.', (new MantenimientoService())->listarTecnicos());
    }

    responder(404, false, "Ruta no encontrada: $metodo /api$ruta");

} catch (ValidacionException $e) {
    responder($e->getCode() === 404 ? 404 : 400, false, $e->getMessage());
} catch (SoapFault $e) {
    responder(502, false, 'Error al llamar al servicio SOAP: ' . $e->getMessage());
} catch (Throwable $e) {
    responder(500, false, 'Error interno: ' . $e->getMessage());
}
