<?php
/**
 * CLIENTE PHP - consume el servicio SOAP de equipos.
 * No se conecta a MySQL: toda la información llega por SOAP.
 */
$config = require __DIR__ . '/config.php';

$alerta = null;          // ['tipo' => 'ok'|'error', 'texto' => ...]
$equipoConsultado = null;
$mantenimientos = null;
$equipos = [];

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

try {
    $soap = new SoapClient($config['wsdl'], ['cache_wsdl' => WSDL_CACHE_NONE, 'exceptions' => true, 'encoding' => 'UTF-8']);

    $accion = $_POST['accion'] ?? ($_GET['accion'] ?? '');

    if ($accion === 'registrar') {
        $r = $soap->registrarEquipo($_POST['codigo'] ?? '', $_POST['nombre'] ?? '', $_POST['tipo'] ?? '', $_POST['estado'] ?? 'ACTIVO');
        $alerta = ['tipo' => $r->exito ? 'ok' : 'error', 'texto' => $r->mensaje];
    }

    if ($accion === 'consultar') {
        $r = $soap->consultarEquipo($_GET['codigo'] ?? '');
        $r->exito ? $equipoConsultado = $r->equipo : $alerta = ['tipo' => 'error', 'texto' => $r->mensaje];
    }

    if ($accion === 'mantenimientos') {
        $r = $soap->consultarMantenimiento($_GET['codigoEquipo'] ?? '');
        $alerta = ['tipo' => $r->exito ? 'ok' : 'error', 'texto' => $r->mensaje];
        $mantenimientos = $r->mantenimientos ?? [];
    }

    // listarEquipos() se ejecuta siempre para mostrar la tabla
    $equipos = $soap->listarEquipos() ?? [];

} catch (SoapFault $f) {
    $alerta = ['tipo' => 'error', 'texto' => 'Error SOAP: ' . $f->getMessage()];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Control de Mantenimiento de Equipos</title>
<style>
  :root { --azul:#1f4e79; --gris:#f3f5f8; --borde:#d6dce4; --ok:#1e7b3a; --err:#b42318; }
  * { box-sizing: border-box; }
  body { margin:0; font-family: system-ui, Segoe UI, Arial, sans-serif; background: var(--gris); color:#1d2433; }
  header { background: var(--azul); color:#fff; padding:18px 24px; }
  header h1 { margin:0; font-size:20px; }
  header p  { margin:4px 0 0; opacity:.85; font-size:13px; }
  main { max-width:1100px; margin:20px auto; padding:0 16px; display:grid; gap:16px; grid-template-columns: 1fr 1fr; }
  section { background:#fff; border:1px solid var(--borde); border-radius:8px; padding:16px; }
  section.ancho { grid-column: 1 / -1; }
  h2 { margin:0 0 12px; font-size:16px; color: var(--azul); }
  label { display:block; font-size:13px; margin:8px 0 4px; }
  input, select { width:100%; padding:8px; border:1px solid var(--borde); border-radius:6px; font-size:14px; }
  button { margin-top:12px; background: var(--azul); color:#fff; border:0; padding:9px 16px; border-radius:6px; cursor:pointer; font-size:14px; }
  table { width:100%; border-collapse: collapse; font-size:14px; }
  th, td { text-align:left; padding:8px; border-bottom:1px solid var(--borde); }
  th { background: var(--gris); }
  .alerta { grid-column: 1 / -1; padding:12px 16px; border-radius:8px; font-size:14px; }
  .alerta.ok    { background:#e7f5ec; color: var(--ok);  border:1px solid #b7e1c4; }
  .alerta.error { background:#fdeceb; color: var(--err); border:1px solid #f5c2bd; }
  .ficha div { padding:4px 0; } .ficha b { display:inline-block; width:80px; }
  .fila { display:flex; gap:8px; align-items:flex-end; } .fila > div { flex:1; } .fila button { margin-top:0; }
  @media (max-width: 760px) { main { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<header>
  <h1>Sistema de control de mantenimiento de equipos</h1>
  <p>Cliente PHP · consume el servicio SOAP: <?= e($config['wsdl']) ?></p>
</header>

<main>
  <?php if ($alerta): ?>
    <div class="alerta <?= e($alerta['tipo']) ?>"><?= e($alerta['texto']) ?></div>
  <?php endif; ?>

  <!-- registrarEquipo() -->
  <section>
    <h2>Registrar equipo</h2>
    <form method="post">
      <input type="hidden" name="accion" value="registrar">
      <label>Código (único)</label>
      <input name="codigo" required maxlength="30" placeholder="EQ-001">
      <label>Nombre</label>
      <input name="nombre" required maxlength="100" placeholder="Computador Dell OptiPlex">
      <label>Tipo</label>
      <input name="tipo" required maxlength="50" placeholder="Computador">
      <label>Estado</label>
      <select name="estado">
        <option>ACTIVO</option><option>INACTIVO</option>
        <option>EN_MANTENIMIENTO</option><option>DADO_DE_BAJA</option>
      </select>
      <button type="submit">Registrar</button>
    </form>
  </section>

  <!-- consultarEquipo() -->
  <section>
    <h2>Consultar equipo</h2>
    <form method="get" class="fila">
      <input type="hidden" name="accion" value="consultar">
      <div><label>Código del equipo</label><input name="codigo" required value="<?= e($_GET['codigo'] ?? '') ?>"></div>
      <button type="submit">Consultar</button>
    </form>
    <?php if ($equipoConsultado): ?>
      <div class="ficha" style="margin-top:14px">
        <div><b>ID</b> <?= e($equipoConsultado->id) ?></div>
        <div><b>Código</b> <?= e($equipoConsultado->codigo) ?></div>
        <div><b>Nombre</b> <?= e($equipoConsultado->nombre) ?></div>
        <div><b>Tipo</b> <?= e($equipoConsultado->tipo) ?></div>
        <div><b>Estado</b> <?= e($equipoConsultado->estado) ?></div>
      </div>
    <?php endif; ?>

    <h2 style="margin-top:22px">Consultar mantenimientos</h2>
    <form method="get" class="fila">
      <input type="hidden" name="accion" value="mantenimientos">
      <div><label>Código del equipo (vacío = todos)</label><input name="codigoEquipo" value="<?= e($_GET['codigoEquipo'] ?? '') ?>"></div>
      <button type="submit">Consultar</button>
    </form>
  </section>

  <?php if ($mantenimientos !== null): ?>
  <!-- consultarMantenimiento() (SOAP -> REST) -->
  <section class="ancho">
    <h2>Mantenimientos</h2>
    <table>
      <tr><th>ID</th><th>Equipo</th><th>Técnico</th><th>Tipo</th><th>Fecha</th><th>Estado</th></tr>
      <?php foreach ($mantenimientos as $m): ?>
        <tr>
          <td><?= e($m->id) ?></td>
          <td><?= e($m->equipoCodigo) ?> – <?= e($m->equipoNombre) ?></td>
          <td><?= e($m->tecnicoNombre) ?></td>
          <td><?= e($m->tipoMantenimiento) ?></td>
          <td><?= e($m->fecha) ?></td>
          <td><?= e($m->estado) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$mantenimientos): ?><tr><td colspan="6">Sin mantenimientos.</td></tr><?php endif; ?>
    </table>
  </section>
  <?php endif; ?>

  <!-- listarEquipos() -->
  <section class="ancho">
    <h2>Equipos registrados (<?= count($equipos) ?>)</h2>
    <table>
      <tr><th>ID</th><th>Código</th><th>Nombre</th><th>Tipo</th><th>Estado</th></tr>
      <?php foreach ($equipos as $eq): ?>
        <tr>
          <td><?= e($eq->id) ?></td><td><?= e($eq->codigo) ?></td><td><?= e($eq->nombre) ?></td>
          <td><?= e($eq->tipo) ?></td><td><?= e($eq->estado) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$equipos): ?><tr><td colspan="5">No hay equipos registrados.</td></tr><?php endif; ?>
    </table>
  </section>
</main>
</body>
</html>
