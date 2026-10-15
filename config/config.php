<?php
/**
 * Configuración central del proyecto.
 * Los valores por defecto sirven para XAMPP (root sin contraseña).
 * Se pueden sobrescribir con variables de entorno.
 */
return [
    // --- Base de datos (SOLO la usan los servicios SOAP y REST, nunca el cliente) ---
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'mantenimiento_db',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    ],

    // --- URLs de los servicios ---
    // Servicio SOAP (endpoint). El WSDL se obtiene con ?wsdl
    'soap_url' => getenv('SOAP_URL') ?: 'http://localhost:8001/servidor.php',
    // Servicio REST (base de la API)
    'rest_url' => getenv('REST_URL') ?: 'http://localhost:8002/api',
];
