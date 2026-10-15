# Proyecto 15 · Sistema de control de mantenimiento de equipos

Servicio **SOAP** + servicio **REST API** en PHP 8 con MySQL, y un **cliente PHP** que consume solo SOAP.

```
cliente (PHP) ──SOAP──▶ servicio SOAP ──────▶ MySQL
                          │  ▲
   consultarMantenimiento │  │ RegistrarEquipo (POST /api/equipos)
                          ▼  │
                       servicio REST ───────▶ MySQL
```

## Estructura

| Carpeta | Contenido |
|---|---|
| `database/schema.sql` | Base `mantenimiento_db`: tablas `equipos`, `tecnicos`, `mantenimientos` + 3 técnicos de ejemplo |
| `config/config.php` | Credenciales de MySQL y URLs de los servicios (solo para los servidores) |
| `lib/` | Conexión PDO y lógica de negocio (`EquipoService`, `MantenimientoService`) |
| `soap/` | `servidor.php` (SoapServer) y `servicio.wsdl` |
| `rest/` | `index.php` (router del REST API) y `.htaccess` para Apache |
| `cliente/` | Cliente web PHP. Solo tiene la URL del WSDL: **no se conecta a MySQL** |
| `tests/pruebas.php` | 35 pruebas de aceptación de punta a punta |

## Operaciones

### SOAP — `http://localhost:8001/servidor.php?wsdl`

| Operación | Parámetros | Respuesta |
|---|---|---|
| `registrarEquipo` | codigo, nombre, tipo, estado | `RespuestaEquipo {exito, mensaje, equipo}` |
| `consultarEquipo` | codigo | `RespuestaEquipo` |
| `listarEquipos` | — | `Equipo[]` |
| `consultarMantenimiento` | codigoEquipo (vacío = todos) | `RespuestaMantenimientos` — **llama a `GET /api/mantenimientos` del REST** |

### REST — `http://localhost:8002/api`

| Método y ruta | Operación |
|---|---|
| `PUT /equipos/{id}` | **actualizarEquipo** — envía `nombre`, `tipo` y/o `estado` |
| `POST /mantenimientos` | **registrarMantenimiento** — `equipoId, tecnicoId, tipoMantenimiento, fecha, estado` |
| `GET /mantenimientos` | **consultarMantenimientos** — filtros `?equipoId= &codigoEquipo= &tecnicoId=` |
| `GET /mantenimientos/{id}` | Un mantenimiento |
| `POST /equipos` | **RegistrarEquipo** — **ejecuta `registrarEquipo` del SOAP** |
| `GET /tecnicos` | Técnicos registrados (apoyo) |

Valores válidos:
- Estado de equipo: `ACTIVO`, `INACTIVO`, `EN_MANTENIMIENTO`, `DADO_DE_BAJA`
- Tipo de mantenimiento: `PREVENTIVO`, `CORRECTIVO`, `PREDICTIVO`
- Estado de mantenimiento: `PROGRAMADO`, `EN_PROCESO`, `FINALIZADO`, `CANCELADO`
- Fecha: `YYYY-MM-DD`

## Cómo ejecutarlo

Requisitos: PHP 8 con extensiones **soap**, **pdo_mysql** y **curl**; MySQL o MariaDB.
En XAMPP, habilita `extension=soap` en `php.ini` (quita el `;`) y reinicia Apache.

1. Crear la base de datos:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   (o importa `schema.sql` desde phpMyAdmin)

2. Revisar credenciales en `config/config.php` (por defecto `root` sin contraseña, como XAMPP).

3. Levantar los tres servidores, cada uno en su terminal, desde la carpeta del proyecto:
   ```bash
   php -S localhost:8001 -t soap
   php -S localhost:8002 -t rest rest/index.php
   php -S localhost:8000 -t cliente
   ```
   Deben ser tres procesos distintos: SOAP y REST se llaman entre sí y el servidor embebido de PHP atiende una petición a la vez.

4. Abrir el cliente: <http://localhost:8000>

5. Ejecutar las pruebas (con la BD recién cargada):
   ```bash
   php tests/pruebas.php
   ```

### Si usas Apache de XAMPP en lugar de `php -S`

Copia el proyecto a `htdocs/proyecto15` y ajusta las URLs:
- `config/config.php` → `soap_url` = `http://localhost/proyecto15/soap/servidor.php`, `rest_url` = `http://localhost/proyecto15/rest/api`
- `cliente/config.php` → `wsdl` = `http://localhost/proyecto15/soap/servidor.php?wsdl`

## Ejemplos con curl (o Postman)

```bash
# RegistrarEquipo por REST (internamente llama al SOAP)
curl -X POST http://localhost:8002/api/equipos -H "Content-Type: application/json" \
  -d '{"codigo":"EQ-001","nombre":"Computador Dell","tipo":"Computador","estado":"ACTIVO"}'

# actualizarEquipo
curl -X PUT http://localhost:8002/api/equipos/1 -H "Content-Type: application/json" \
  -d '{"estado":"EN_MANTENIMIENTO"}'

# registrarMantenimiento
curl -X POST http://localhost:8002/api/mantenimientos -H "Content-Type: application/json" \
  -d '{"equipoId":1,"tecnicoId":1,"tipoMantenimiento":"PREVENTIVO","fecha":"2026-10-15","estado":"PROGRAMADO"}'

# consultarMantenimientos
curl "http://localhost:8002/api/mantenimientos?codigoEquipo=EQ-001"
```

## Criterios de aceptación cubiertos

| Criterio | Dónde |
|---|---|
| Equipos en MySQL | `database/schema.sql`, `lib/EquipoService.php` |
| Código único | `UNIQUE (codigo)` + validación en `EquipoService::registrar` |
| Consultar un equipo / listar todos | SOAP `consultarEquipo`, `listarEquipos` |
| Actualizar nombre, tipo o estado | REST `PUT /equipos/{id}` (rechaza otros campos) |
| Mantenimiento solo para equipo existente y técnico registrado | Validación + `FOREIGN KEY` en `mantenimientos` |
| Tipo, fecha y estado obligatorios | `MantenimientoService::registrar` |
| Cliente PHP consume por SOAP y no toca MySQL | `cliente/` (solo `SoapClient` y la URL del WSDL) |
| REST `RegistrarEquipo` ejecuta el registro SOAP | `rest/index.php` → `SoapClient::registrarEquipo` |
| SOAP `consultarMantenimiento` llama al REST | `soap/servidor.php` → `curl GET /api/mantenimientos` |
