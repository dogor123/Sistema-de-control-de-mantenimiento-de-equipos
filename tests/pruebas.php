<?php
/**
 * Pruebas de aceptación de punta a punta.
 * Requiere los tres servidores levantados (SOAP :8001, REST :8002, cliente :8000)
 * y la base de datos recién cargada con database/schema.sql.
 *
 *   php tests/pruebas.php
 */
$SOAP = getenv('SOAP_URL') ?: 'http://localhost:8001/servidor.php';
$REST = getenv('REST_URL') ?: 'http://localhost:8002/api';
$CLI  = getenv('CLI_URL')  ?: 'http://localhost:8000/index.php';

$ok = 0; $fallos = 0;
function check(string $nombre, bool $cond, string $detalle = ''): void
{
    global $ok, $fallos;
    $cond ? $ok++ : $fallos++;
    echo ($cond ? "  [OK]   " : "  [FALLA] ") . $nombre . ($cond || !$detalle ? '' : "  -> $detalle") . PHP_EOL;
}
function http(string $metodo, string $url, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode($res, true), $res];
}

$soap = new SoapClient("$SOAP?wsdl", ['cache_wsdl' => WSDL_CACHE_NONE, 'exceptions' => true]);
$sufijo = date('His');

echo "\n== SOAP: registrarEquipo / consultarEquipo / listarEquipos ==\n";
$r = $soap->registrarEquipo("EQ-A$sufijo", 'Computador Dell OptiPlex', 'Computador', 'ACTIVO');
check('registrarEquipo registra y devuelve el equipo con id', $r->exito && $r->equipo->id > 0, $r->mensaje);
$idA = $r->equipo->id ?? 0;

$r = $soap->registrarEquipo("EQ-A$sufijo", 'Duplicado', 'Impresora', 'ACTIVO');
check('El código del equipo es único (rechaza duplicado)', !$r->exito && str_contains($r->mensaje, 'Ya existe'), $r->mensaje);

$r = $soap->registrarEquipo("EQ-X$sufijo", 'Equipo raro', 'Otro', 'ROTO');
check('Rechaza estado inválido', !$r->exito, $r->mensaje);

$r = $soap->registrarEquipo('', 'Sin código', 'Otro', 'ACTIVO');
check('Rechaza código vacío', !$r->exito, $r->mensaje);

$r = $soap->consultarEquipo("EQ-A$sufijo");
check('consultarEquipo devuelve un equipo individual', $r->exito && $r->equipo->nombre === 'Computador Dell OptiPlex', $r->mensaje);

$r = $soap->consultarEquipo('NO-EXISTE');
check('consultarEquipo informa cuando no existe', !$r->exito, $r->mensaje);

$soap->registrarEquipo("EQ-B$sufijo", 'Impresora HP LaserJet', 'Impresora', 'ACTIVO');
$lista = $soap->listarEquipos();
$codigos = array_map(fn($e) => $e->codigo, $lista);
check('listarEquipos lista todos los equipos', in_array("EQ-A$sufijo", $codigos) && in_array("EQ-B$sufijo", $codigos), implode(',', $codigos));

echo "\n== REST: actualizarEquipo (PUT /equipos/{id}) ==\n";
[$c, $j] = http('PUT', "$REST/equipos/$idA", ['nombre' => 'Computador Dell OptiPlex 7090']);
check('Actualiza solo el nombre', $c === 200 && $j['datos']['nombre'] === 'Computador Dell OptiPlex 7090' && $j['datos']['tipo'] === 'Computador', "HTTP $c");
[$c, $j] = http('PUT', "$REST/equipos/$idA", ['tipo' => 'Computador de escritorio', 'estado' => 'en_mantenimiento']);
check('Actualiza tipo y estado', $c === 200 && $j['datos']['tipo'] === 'Computador de escritorio' && $j['datos']['estado'] === 'EN_MANTENIMIENTO', "HTTP $c");
[$c, $j] = http('PUT', "$REST/equipos/$idA", ['codigo' => 'HACK']);
check('No permite actualizar otros campos (código)', $c === 400, "HTTP $c");
[$c, $j] = http('PUT', "$REST/equipos/$idA", ['estado' => 'VOLANDO']);
check('Rechaza estado inválido al actualizar', $c === 400, "HTTP $c");
[$c, $j] = http('PUT', "$REST/equipos/999999", ['nombre' => 'X']);
check('404 al actualizar equipo inexistente', $c === 404, "HTTP $c");
$r = $soap->consultarEquipo("EQ-A$sufijo");
check('El cambio se refleja al consultar por SOAP (misma BD)', $r->equipo->estado === 'EN_MANTENIMIENTO');

echo "\n== REST: registrarMantenimiento (POST /mantenimientos) ==\n";
$base = ['equipoId' => $idA, 'tecnicoId' => 1, 'tipoMantenimiento' => 'PREVENTIVO', 'fecha' => '2026-10-15', 'estado' => 'PROGRAMADO'];
[$c, $j] = http('POST', "$REST/mantenimientos", $base);
check('Registra mantenimiento con tipo, fecha y estado', $c === 201 && $j['datos']['tipoMantenimiento'] === 'PREVENTIVO'
    && $j['datos']['fecha'] === '2026-10-15' && $j['datos']['estado'] === 'PROGRAMADO', "HTTP $c " . ($j['mensaje'] ?? ''));
[$c, $j] = http('POST', "$REST/mantenimientos", ['tecnicoId' => 2, 'tipoMantenimiento' => 'CORRECTIVO', 'fecha' => '2026-10-20', 'estado' => 'FINALIZADO'] + ['equipoId' => $idA]);
check('Registra un segundo mantenimiento', $c === 201, "HTTP $c");
[$c, $j] = http('POST', "$REST/mantenimientos", ['equipoId' => 999999] + $base);
check('Solo para equipos existentes (404)', $c === 404 && str_contains($j['mensaje'], 'equipo'), "HTTP $c " . ($j['mensaje'] ?? ''));
[$c, $j] = http('POST', "$REST/mantenimientos", ['tecnicoId' => 999] + $base);
check('Debe asociarse a técnico registrado (404)', $c === 404 && str_contains($j['mensaje'], 'técnico'), "HTTP $c " . ($j['mensaje'] ?? ''));
[$c, $j] = http('POST', "$REST/mantenimientos", ['fecha' => '2026-02-30'] + $base);
check('Rechaza fecha inválida', $c === 400, "HTTP $c");
[$c, $j] = http('POST', "$REST/mantenimientos", ['tipoMantenimiento' => ''] + $base);
check('Rechaza sin tipo de mantenimiento', $c === 400, "HTTP $c");
[$c, $j] = http('POST', "$REST/mantenimientos", ['estado' => ''] + $base);
check('Rechaza sin estado', $c === 400, "HTTP $c");

echo "\n== REST: consultarMantenimientos (GET /mantenimientos) ==\n";
[$c, $j] = http('GET', "$REST/mantenimientos?equipoId=$idA");
check('Consulta por equipoId', $c === 200 && count($j['datos']) === 2, "HTTP $c, " . count($j['datos'] ?? []));
[$c, $j] = http('GET', "$REST/mantenimientos?codigoEquipo=EQ-A$sufijo");
check('Consulta por código de equipo', $c === 200 && count($j['datos']) === 2);
[$c, $j] = http('GET', "$REST/mantenimientos?tecnicoId=2&equipoId=$idA");
check('Consulta por técnico', $c === 200 && count($j['datos']) === 1 && $j['datos'][0]['tecnicoNombre'] === 'Laura Gómez');

echo "\n== Integración: REST RegistrarEquipo -> SOAP registrarEquipo ==\n";
[$c, $j] = http('POST', "$REST/equipos", ['codigo' => "EQ-R$sufijo", 'nombre' => 'Router Cisco', 'tipo' => 'Red', 'estado' => 'ACTIVO']);
check('POST /api/equipos registra vía SOAP', $c === 201 && str_contains($j['mensaje'], 'vía SOAP'), "HTTP $c " . ($j['mensaje'] ?? ''));
$r = $soap->consultarEquipo("EQ-R$sufijo");
check('El equipo registrado por REST existe en SOAP', $r->exito);
[$c, $j] = http('POST', "$REST/equipos", ['codigo' => "EQ-R$sufijo", 'nombre' => 'Otro', 'tipo' => 'Red']);
check('REST respeta código único del SOAP (409)', $c === 409, "HTTP $c");

echo "\n== Integración: SOAP consultarMantenimiento -> REST GET /mantenimientos ==\n";
$r = $soap->consultarMantenimiento("EQ-A$sufijo");
check('consultarMantenimiento devuelve los datos del REST', $r->exito && count($r->mantenimientos) === 2, $r->mensaje);
check('Datos completos (técnico, tipo, fecha, estado)', isset($r->mantenimientos[0]->tecnicoNombre, $r->mantenimientos[0]->fecha, $r->mantenimientos[0]->estado));
$r = $soap->consultarMantenimiento("EQ-B$sufijo");
check('Equipo sin mantenimientos devuelve lista vacía', $r->exito && count($r->mantenimientos) === 0);
check('La respuesta indica que viene del REST API', str_contains($r->mensaje, 'vía REST API'), $r->mensaje);

echo "\n== Cliente PHP ==\n";
$html = file_get_contents($CLI);
check('El cliente lista los equipos vía SOAP', str_contains($html, "EQ-A$sufijo") && str_contains($html, 'Router Cisco'));
$ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded',
    'content' => http_build_query(['accion' => 'registrar', 'codigo' => "EQ-C$sufijo", 'nombre' => 'Escáner Epson', 'tipo' => 'Escáner', 'estado' => 'ACTIVO'])]]);
$html = file_get_contents($CLI, false, $ctx);
check('El cliente registra un equipo vía SOAP', str_contains($html, 'Equipo registrado correctamente') && str_contains($html, "EQ-C$sufijo"));
$html = file_get_contents("$CLI?accion=consultar&codigo=EQ-A$sufijo");
check('El cliente consulta un equipo vía SOAP', str_contains($html, 'Computador Dell OptiPlex 7090'));
$html = file_get_contents("$CLI?accion=mantenimientos&codigoEquipo=EQ-A$sufijo");
check('El cliente consulta mantenimientos vía SOAP', str_contains($html, 'Laura Gómez') && str_contains($html, 'PREVENTIVO'));
$fuente = implode('', array_map('file_get_contents', glob(__DIR__ . '/../cliente/*.php')));
check('El cliente NO se conecta a MySQL (sin PDO/mysqli/credenciales)', !preg_match('/new\s+PDO|mysqli|mysql:host|Database::|DB_USER|DB_PASS/i', $fuente));

echo "\nResultado: $ok OK, $fallos fallas\n";
exit($fallos ? 1 : 0);
