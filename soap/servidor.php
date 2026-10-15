<?php
/**
 * SERVICIO SOAP - Equipos
 *
 * Operaciones:
 *   registrarEquipo(codigo, nombre, tipo, estado)
 *   consultarEquipo(codigo)
 *   listarEquipos()
 *   consultarMantenimiento(codigoEquipo)  -> delega en el REST API (GET /mantenimientos)
 *
 * WSDL: http://localhost:8001/servidor.php?wsdl
 */
require_once __DIR__ . '/../lib/EquipoService.php';

$config = require __DIR__ . '/../config/config.php';

// Genera el WSDL con la URL configurada
$wsdl = str_replace('{{SOAP_URL}}', htmlspecialchars($config['soap_url']), file_get_contents(__DIR__ . '/servicio.wsdl'));

if (isset($_GET['wsdl'])) {
    header('Content-Type: text/xml; charset=utf-8');
    echo $wsdl;
    exit;
}

class EquiposSoapHandler
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function registrarEquipo($codigo, $nombre, $tipo, $estado = 'ACTIVO')
    {
        try {
            $equipo = (new EquipoService())->registrar((string)$codigo, (string)$nombre, (string)$tipo, (string)($estado ?: 'ACTIVO'));
            return $this->respuesta(true, 'Equipo registrado correctamente.', $equipo);
        } catch (ValidacionException $e) {
            return $this->respuesta(false, $e->getMessage());
        } catch (Throwable $e) {
            throw new SoapFault('Server', 'Error interno: ' . $e->getMessage());
        }
    }

    public function consultarEquipo($codigo)
    {
        try {
            $equipo = (new EquipoService())->buscarPorCodigo((string)$codigo);
            return $equipo
                ? $this->respuesta(true, 'Equipo encontrado.', $equipo)
                : $this->respuesta(false, "No existe un equipo con el código '$codigo'.");
        } catch (Throwable $e) {
            throw new SoapFault('Server', 'Error interno: ' . $e->getMessage());
        }
    }

    public function listarEquipos()
    {
        try {
            return (new EquipoService())->listar();
        } catch (Throwable $e) {
            throw new SoapFault('Server', 'Error interno: ' . $e->getMessage());
        }
    }

    /**
     * Llama al REST API (GET /mantenimientos) que ejecuta la funcionalidad.
     * Si codigoEquipo viene vacío, trae todos los mantenimientos.
     */
    public function consultarMantenimiento($codigoEquipo = '')
    {
        $url = rtrim($this->config['rest_url'], '/') . '/mantenimientos';
        if (trim((string)$codigoEquipo) !== '') {
            $url .= '?codigoEquipo=' . urlencode(trim((string)$codigoEquipo));
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $cuerpo = curl_exec($ch);
        $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false) {
            throw new SoapFault('Server', "No se pudo conectar con el REST API: $error");
        }
        $json = json_decode($cuerpo, true);
        if ($http !== 200 || !is_array($json)) {
            return [
                'exito'          => false,
                'mensaje'        => $json['mensaje'] ?? "El REST API respondió HTTP $http.",
                'mantenimientos' => [],
            ];
        }
        $lista = $json['datos'] ?? [];
        return [
            'exito'          => true,
            'mensaje'        => count($lista) . ' mantenimiento(s) encontrado(s) (vía REST API).',
            'mantenimientos' => $lista,
        ];
    }

    private function respuesta(bool $exito, string $mensaje, ?array $equipo = null): array
    {
        return ['exito' => $exito, 'mensaje' => $mensaje, 'equipo' => $equipo];
    }
}

// SoapServer necesita el WSDL en un archivo: se guarda una copia con la URL ya resuelta
$wsdlArchivo = sys_get_temp_dir() . '/equipos_' . md5($wsdl) . '.wsdl';
if (!file_exists($wsdlArchivo)) {
    file_put_contents($wsdlArchivo, $wsdl);
}

ini_set('soap.wsdl_cache_enabled', '0');
$server = new SoapServer($wsdlArchivo, ['encoding' => 'UTF-8', 'cache_wsdl' => WSDL_CACHE_NONE]);
$server->setObject(new EquiposSoapHandler($config));
$server->handle();
