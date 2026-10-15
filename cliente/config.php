<?php
// El cliente SOLO conoce la URL del servicio SOAP. No tiene credenciales de MySQL.
return [
    'wsdl' => getenv('SOAP_WSDL') ?: 'http://localhost:8001/servidor.php?wsdl',
];
