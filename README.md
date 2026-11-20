# Parchís online

Parchís para 2–6 jugadores, en tiempo real, desde el navegador y sin login. Cada
jugador elige apodo y color; el primero crea la sala y es el host. El servidor
es la única autoridad: tira el dado, valida movimientos, resuelve capturas y
decide el ganador. El cliente solo dibuja el estado que recibe y envía
intenciones (`roll`, `move`).

Stack: **Laravel 12/13 + Inertia 3 + Vue 3 + TypeScript + Tailwind 4 + SVG**,
con **Laravel Reverb / Echo** para WebSocket y SQLite local / MySQL o PostgreSQL
en producción.

> La fuente de verdad de reglas y arquitectura es [`AGENTS.md`](AGENTS.md).

---

## 1. Reglas resumidas

- 2–3 jugadores → tablero de **3 brazos**; 4 → **4 brazos**; 5–6 → **6 brazos en estrella**.
- El tablero se genera con una única fábrica paramétrica (`BoardFactory::forArms()`);
  los brazos vacíos se dibujan, pero su salida es una casilla normal.
- 4 fichas por jugador. `progress = null` es casa; `0..M-1` es anillo;
  `M..M+5` es columna de casa; `M+6` es meta.
- Con **5** se puede sacar una ficha de casa (a `progress = 0`) o mover 5 pasos
  una ficha desplegada. Con cualquier otro valor solo se mueven fichas desplegadas.
- Entrar en columna **no** exige tirada exacta; llegar a meta **sí**. Pasarse de meta es ilegal.
- Captura solo al caer, solo en anillo y solo en casilla no segura. Hay 2
  casillas seguras por brazo ocupado (salida y punto medio).
- Un **6** da turno extra solo si se ejecutó un movimiento legal. El tercer 6
  consecutivo se consume sin mover. Victoria antes que turno extra.
- Empieza el host; después se sigue el `seat` (orden de llegada), saltando
  jugadores desconectados. No hay pase voluntario si existe algún movimiento legal.
- Gana quien coloca sus 4 fichas en meta. No hay barreras, bonus de captura,
  bonus de llegada ni temporizador de turno.

---

## 2. Requisitos

### Local

- PHP 8.3+ con extensiones `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip`, `gd`.
- Composer 2.
- Node.js 20+ y npm.
- SQLite (incluido en la instalación local).

### Producción

- PHP 8.3+ en Laravel Cloud Starter (o similar).
- MySQL/PostgreSQL gestionado.
- Almacenamiento de sesiones y caché (`database` o Valkey/Redis).
- Cola gestionada de Laravel Cloud (o `database`/`redis` en self-host).
- Clúster WebSocket de Laravel Reverb gestionado (o Pusher externo).
- HTTPS en todo el dominio; el cliente conecta por **WSS** sin contenido mixto.

---

## 3. Instalación local

```powershell
composer setup
composer run dev
```

`composer setup` ya hace:

1. `composer install`.
2. Copiar `.env.example` a `.env` si no existe.
3. Crear `database/database.sqlite` si no existe.
4. `php artisan key:generate`.
5. `php artisan migrate --force` y `php artisan db:seed --force`.
6. `npm install` y `npm run build`.

`composer run dev` levanta en paralelo:

| Proceso | Comando                              | Puerto |
| ------- | ------------------------------------ | ------ |
| Web     | `php artisan serve`                  | 8000   |
| Cola    | `php artisan queue:listen --tries=1` | —      |
| Reverb  | `php artisan reverb:start`           | 8082   |
| Vite    | `npm run dev`                        | 5173   |

Abre <http://localhost:8000>. El seeder crea una partida terminada de
demostración con código `DEMO` y tokens `demo-token-1` … `demo-token-4`.

Instalación manual equivalente:

```powershell
composer install
copy .env.example .env
php artisan key:generate
if (-not (Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite }
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
composer run dev
```

---

## 4. Desarrollo y comprobaciones

```powershell
composer run dev          # único comando de desarrollo

php artisan test --compact
composer lint:check
npm run types:check
npm run build
npm run test:a11y
```

También disponibles:

| Script                                                   | Uso                                  |
| -------------------------------------------------------- | ------------------------------------ |
| `composer test`                                          | config:clear + Pint + PHPStan + Pest |
| `composer ci:check`                                      | `npm run check` + tipos + tests      |
| `composer lint` / `composer lint:check`                  | Pint / comprobación de estilo        |
| `composer types:check`                                   | PHPStan (Larastan)                   |
| `npm run check` / `npm run check:fix`                    | formato y lint de frontend           |
| `npm run build:ssr`                                      | build cliente + servidor             |
| `php artisan parchis:cleanup-abandoned-rooms`            | borrar salas `waiting` abandonadas   |
| `php artisan parchis:cleanup-abandoned-rooms --hours=12` | mismo comando con umbral propio      |

El seeder es idempotente: si ya existe `DEMO`, no vuelve a crearla.

Estado de las comprobaciones en la última entrega:

| Comprobación                              | Resultado                                                                                        |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `php artisan test --compact`              | 38/38 tests, 312 assertions                                                                      |
| `composer lint:check`                     | OK                                                                                               |
| `npm run types:check`                     | OK                                                                                               |
| `npm run build`                           | OK                                                                                               |
| `npm run test:a11y`                       | 14/14 tests                                                                                      |
| `php artisan schedule:list`               | limpieza horaria registrada                                                                      |
| `php artisan optimize` / `optimize:clear` | OK                                                                                               |
| `composer types:check`                    | 31 errores heredados de tipado en archivos previos; ninguno en los archivos nuevos de despliegue |

---

## 5. Estructura

```text
app/
  Application/Game/       Comandos, handlers, DTOs, queries y servicios de aplicación
  Console/Commands/       Limpieza de salas abandonadas
  Domain/Game/            Enums, motor puro, validador, capturas, ganador, dado
  Http/Controllers/       Salas, partidas, vistas Inertia y resultados
  Infrastructure/         Persistencia Eloquent, broadcast y dado aleatorio
  Providers/              Rate limiting, HTTPS forzado y bindings
  Support/                PlayerSession (token sin login)
bootstrap/app.php           Middleware web/api y ResolvePlayerToken
config/parchis.php          Parámetros del tablero y reglas
database/migrations/        games, players, game_events, game_results, jobs, cache…
database/seeders/           DemoGameSeeder
resources/js/components/    Tablero SVG, dado, fichas, listas y paneles
resources/js/lib/           echo.ts, api.ts, playerSession.ts, tema, sonido, a11y
resources/js/pages/         Welcome, Waiting, Board, Result, historial
routes/                     web.php, api.php, channels.php, console.php
tests/Unit/                 Motor de juego
tests/Feature/              Salas, partidas, resultados y despliegue
```

El frontend es deliberadamente tonto: recibe `BoardConfig` + `GameSnapshot` y
solo renderiza. La configuración de Echo está centralizada en
`resources/js/lib/echo.ts`; admite Reverb (por defecto) y Pusher según
`VITE_BROADCAST_CONNECTION`.

---

## 6. API REST

Todas las acciones requieren la cabecera `X-Player-Token`, salvo crear sala,
unirse y las consultas públicas de historial.

| Método | Ruta                              | Descripción                                            |
| ------ | --------------------------------- | ------------------------------------------------------ |
| POST   | `/api/rooms`                      | Crear sala `{nickname, color}` → `{code, playerToken}` |
| GET    | `/api/rooms/{code}`               | Estado de la sala de espera                            |
| POST   | `/api/rooms/{code}/join`          | Unirse `{nickname, color}` → `{playerToken}`           |
| POST   | `/api/rooms/{code}/leave`         | Salir de la sala                                       |
| POST   | `/api/rooms/{code}/start`         | Iniciar (solo host, 2–6 jugadores)                     |
| GET    | `/api/games/{code}`               | Snapshot autoritativo actual                           |
| POST   | `/api/games/{code}/roll`          | Tirar dado (servidor)                                  |
| POST   | `/api/games/{code}/moves`         | Mover ficha `{pieceId}`                                |
| GET    | `/api/games/{code}/result`        | Resultado (solo jugadores; token)                      |
| GET    | `/api/partidas/{code}/resultado`  | Alias en español del resultado                         |
| POST   | `/api/games/{code}/rematch`       | Clonar sala (solo host)                                |
| POST   | `/api/games/{code}/rematch/token` | Token reutilizable en la sala clonada                  |

Rutas web Inertia: `/`, `/rooms/{code}`, `/games/{code}`, `/partidas` y
`/partidas/{code}`.

Canales WebSocket efectivos:

| Canal                   | Uso                                                 |
| ----------------------- | --------------------------------------------------- |
| `presence-room.{code}`  | Lista de jugadores, host, inicio y revancha         |
| `private-game.{gameId}` | Snapshot completo y eventos del contrato de partida |

El cliente ignora eventos con `version` menor a la ya aplicada. Todas las
acciones van por HTTP POST: los eventos WebSocket nunca aceptan acciones del
cliente.

---

## 7. Despliegue en Laravel Cloud Starter

La opción recomendada es **Laravel Cloud Starter + WebSockets gestionados
(Reverb)**. Es la más sencilla para que otra persona despliegue y mantenga:
incluye base de datos, SSL/CDN, cola, scheduler y Reverb sin servidores propios.

### 7.1 Preparación previa

1. Sube este repositorio a GitHub/GitLab.
2. Revisa que no haya `.env`, claves ni tokens en el repositorio: solo se
   versiona `.env.example`.
3. En Cloud, crea la aplicación desde el repositorio y elige la región.

### 7.2 Comandos de build y release

En la configuración debuild de Cloud:

```sh
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
composer run deploy:build
```

`composer run deploy:build` ejecuta `npm install --no-audit --no-fund`, `npm run build` y
`php artisan optimize` (config/rutas/vistas en caché). Ejecútalo en **build**,
no como comando de release, para que los archivos cacheados se empaqueten con el
release.

Comando de release / deploy:

```sh
php artisan migrate --force
```

No ejecutes `db:seed` en producción salvo que quieras exponer la partida demo
con tokens públicos: es solo para pruebas.

Tras cada deploy, Cloud reinicia automáticamente web, cola y WebSockets. Si
cambias variables de entorno, haz un redeploy para regenerar la caché de
configuración.

### 7.3 Recursos a añadir en el panel

| Recurso       | Acción en Cloud                                      | Observación                                                                  |
| ------------- | ---------------------------------------------------- | ---------------------------------------------------------------------------- |
| Base de datos | **Add resource → Database** (MySQL o PostgreSQL)     | Cloud inyecta `DB_*`/`DB_URL`                                                |
| WebSockets    | **Resources → WebSockets → + New WebSocket cluster** | Elegir concurrent connections; adjuntar la app y redesplegar                 |
| Cola          | **Add compute → Managed queue**                      | Cloud pone `QUEUE_CONNECTION=cloud`; requiere `aws/aws-sdk-php`, ya incluido |
| Scheduler     | Activar **Scheduler** en el App cluster              | Ejecuta `schedule:run`; limpieza horaria configurada en `routes/console.php` |
| Dominio/TLS   | Añadir dominio y SSL                                 | `APP_URL` debe ser `https://…` exacto                                        |

La cola gestionada es la opción por defecto. En esta aplicación los broadcast
se envían con `afterCommit`, por lo que una caída temporal de Reverb no rompe la
acción del usuario: se registra en logs. La cola queda preparada para trabajos
futuros y para el procesamiento diferido.

### 7.4 Variables de entorno de producción

Configura estos valores en el panel de Cloud (nunca en el repositorio):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com
APP_FORCE_HTTPS=true
APP_LOCALE=es
APP_FALLBACK_LOCALE=es

LOG_CHANNEL=stack
LOG_LEVEL=info

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database

BROADCAST_CONNECTION=reverb
REVERB_VERIFY_SSL=true
VITE_BROADCAST_CONNECTION=reverb

PARCHIS_ABANDONED_ROOM_HOURS=24
```

Cloud inyecta automáticamente `APP_KEY`, credenciales de base de datos y, al
adjuntar WebSockets:

```dotenv
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=ws-xxxx-reverb.laravel.cloud
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_APP_KEY=...
VITE_REVERB_HOST=...
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

`REVERB_APP_SECRET` debe permanecer solo en el servidor. El navegador recibe
únicamente la clave pública y el host. No hace falta configurar un proxy
inverso propio: Cloud termina TLS y el cliente conecta a
`wss://REVERB_HOST:443`.

Si usas Valkey/Redis en lugar de `database`:

```dotenv
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=...
REDIS_PASSWORD=...
REDIS_PORT=6379
```

### 7.5 Orígenes WebSocket

En Cloud, los orígenes permitidos se configuran en la pestaña del clúster
WebSocket (**Allowed origins**). Cloud permite automáticamente el dominio de
los entornos adjuntos; si el frontend estuviera en otro dominio, añádelo allí.

`REVERB_ALLOWED_ORIGINS` en `config/reverb.php` aplica cuando **tú** ejecutas el
servidor Reverb (self-host). Por defecto solo permite `localhost:8000` y
`127.0.0.1:8000`; en producción self-host debes sustituirlo por tu dominio,
nunca dejar `*`.

Referencia de proxy inverso solo para self-host (no necesario en Cloud):

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 3600s;
}
```

### 7.6 Si se usa Pusher en lugar de Reverb

El código ya soporta ambos broadcast drivers. En Cloud:

1. Crear la app en Pusher y obtener `PUSHER_APP_ID`, `PUSHER_APP_KEY`,
   `PUSHER_APP_SECRET` y cluster.
2. Configurar en el panel:

```dotenv
BROADCAST_CONNECTION=pusher
VITE_BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=mt1
PUSHER_HOST=api-mt1.pusher.com
PUSHER_PORT=443
PUSHER_SCHEME=https
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
```

3. Añadir el dominio a **Allowed origins** en Pusher.
4. No hace falta redesplegar solo por cambiar de Reverb a Pusher.

`config/broadcasting.php` decide el driver en servidor y
`resources/views/app.blade.php` inyecta la configuración pública en
`window.__PARCHIS_BROADCAST__`. `resources/js/lib/echo.ts` lee ese objeto y
solamente usa `VITE_*` como fallback. No hace falta tocar los componentes Vue ni
reconstruir los assets al cambiar de entorno.

### 7.7 Procesos permanentes y reinicio

En Cloud no hay que mantener processes manuales:

- **Web**: Cloud gestiona PHP-FPM y reinicia al desplegar.
- **Cola**: managed queue con autoscaling. No ejecutar `queue:work` a mano.
- **Scheduler**: activado en el panel; ejecuta `php artisan schedule:run` cada minuto.
- **Reverb**: clúster gestionado; se reconecta solo tras deploy.

En self-host, `composer run dev` solo es para desarrollo. Producción necesita
`php artisan queue:work --tries=3`, un cron con `* * * * * php artisan schedule:run`
y `php artisan reverb:start` detrás de HTTPS, todos con supervisor/systemd.

### 7.8 Alternativa gratuita: Wasmer Edge + Pusher Sandbox

Wasmer Edge puede ejecutar el HTTP de Laravel, pero **no puede alojar Reverb**:
TCP/UDP está en alpha y los _persistent workloads_ aún están en desarrollo. La
combinación gratuita es:

```text
Wasmer Edge (HTTP + PostgreSQL gestionada de 100 MB)
Pusher Sandbox (WebSockets: 200.000 mensajes/día, 100 conexiones concurrentes)
QUEUE_CONNECTION=sync  →  sin worker permanente
PARCHIS_AUTO_MIGRATE=true  →  migra en el primer arranque (sin cron jobs)
```

Límites del plan Hobby de Wasmer: 100.000 peticiones/mes, 6 horas de cómputo,
150 GB de ancho de banda y 100 MB de BD. Es suficiente para probar y para
partidas ocasionales, no para un servidor con partidas 24/7. Los _cron jobs_ y
SSH son funciones del plan Pro, así que la limpieza de salas abandonadas se
ejecuta manualmente (o se omite: no afecta a las partidas terminadas).

El repositorio incluye `wasmer.toml` y `app.yaml` preparados. Pasos:

1. Crear una app gratuita en **Pusher Channels** (plan **Sandbox**), no en
   Pusher Beams: Beams son notificaciones push y no sirve para los WebSockets
   del juego. Copia `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET` y el
   cluster. Si tu cluster no es `mt1`, cambia `PUSHER_APP_CLUSTER` y
   `PUSHER_HOST` en `app.yaml`.
2. Instalar el CLI en Windows (PowerShell):

    ```powershell
    iwr https://win.wasmer.io -useb | iex
    ```

    En macOS/Linux: `curl https://get.wasmer.io -sSfL | sh`.

3. Hacer `wasmer login`.
4. `app.yaml` ya declara `capabilities.database.engine: postgres` en la región
   `fr-roub1`, así que Wasmer crea la BD y inyecta `DB_HOST`, `DB_PORT`,
   `DB_NAME`, `DB_USERNAME` y `DB_PASSWORD`. El código acepta `DB_NAME` como
   fallback de `DB_DATABASE`. Ajusta `APP_URL` al dominio real que te asigne
   Wasmer. Si `parchis-online` está ocupado, cambia también `name` y `package`
   en `app.yaml` y haz commit/push antes de continuar.
5. Añadir el dominio `*.wasmer.app` (o el tuyo) a **Allowed origins** en Pusher.
6. Crear la app con el primer despliegue (te pedirá dueño y nombre):

    ```sh
    npm install
    npm run build
    wasmer deploy
    ```

    `public/build` se versiona en esta rama porque Wasmer no ejecuta `npm run
   build` por Laravel. Tras cambiar frontend, vuelve a ejecutar el build antes
    de `wasmer deploy`.

7. Ahora sí, guarda los secretos y redespliega. El CLI los asocia a la app
   leyendo `app.yaml`:

    ```sh
    wasmer app secrets create APP_KEY "$(php artisan key:generate --show)"
    wasmer app secrets create PUSHER_APP_ID TU_APP_ID
    wasmer app secrets create PUSHER_APP_KEY TU_APP_KEY
    wasmer app secrets create PUSHER_APP_SECRET TU_APP_SECRET
    wasmer deploy
    ```

    El primer arranque sin secretos puede devolver 500; es normal, se resuelve
    con este redespliegue. También puedes crear las variables desde
    _Settings → Environment Vars_ del panel de Wasmer.

8. En el primer arranque con secretos, `PARCHIS_AUTO_MIGRATE=true` crea las
   tablas automáticamente. Para limpiar salas abandonadas en el plan Pro puedes
   descomentar el bloque `jobs` de `app.yaml`; en Hobby, las salas antiguas
   permanecen hasta que las borres desde la BD.

La configuración pública de Pusher viaja en el HTML, así que no hay que
reconstruir los assets cuando cambian las credenciales. El secreto de Pusher
nunca se expone al navegador.

---

## 8. Seguridad

- Sin login: token aleatorio por pestaña en `sessionStorage`; solo su hash SHA-256
  en `players.token_hash`. La cabecera `X-Player-Token` gana sobre la cookie.
- Autorización de canales en `routes/channels.php` con ese token.
- Rate limiting nombrado: `room-create` (10/min por IP), `room-join` (20/min por IP)
  y `game-action` (120/min por token+IP). Se aplica a crear/unirse/start/leave y
  a roll/move/rematch.
- `lockForUpdate()` sobre la fila `games` + `Cache::lock` + `version` optimista;
  conflictos responden `409`.
- Resultado de partida restringido a sus jugadores: `401` sin token, `403` con
  token ajeno. Historial público separado en `/partidas`.
- `APP_DEBUG=false`, HTTPS forzado, Cookies `secure` en producción, sin secretos
  en el repositorio y orígenes WebSocket restringidos.
- Los eventos llevan `version`; el cliente descarta snapshot obsoletos.

---

## 9. Prueba de humo tras desplegar

Marca cada punto con 2–3 pestañas o navegadores distintos:

- [ ] `GET /` carga sin errores de consola y con HTTPS.
- [ ] Crear sala; el código de 4 letras se copia y la URL `/rooms/{code}` funciona.
- [ ] Unirse desde otra pestaña con apodo/color distintos; la lista se actualiza en vivo.
- [ ] Con 2–3 jugadores la previsualización tiene 3 brazos; con 4, 4; con 5–6, 6 en estrella.
- [ ] Un jugador que no es host no ve habilitado **Empezar partida**.
- [ ] Al iniciar, todas las pestañas navegan a `/games/{code}` y el tablero queda fijado.
- [ ] El jugador activo ve **Tirar dado**; tras tirar, solo sus fichas legales parpadean.
- [ ] Mover una ficha actualiza el tablero en todas las pestañas sin recargar.
- [ ] Recargar una pestaña reconstruye el tablero desde el snapshot sin animaciones.
- [ ] El dado y los eventos llegan por WSS; no hay error de contenido mixto ni `403` de canal.
- [ ] `GET /api/games/{code}/result` sin token → `401`; con token ajeno → `403`.
- [ ] Al terminar, todas ven la pantalla final; **Volver a jugar** crea otra sala con los mismos jugadores.
- [ ] `/partidas` y `/partidas/{code}` muestran el historial.
- [ ] `GET /api/games/DEMO/result` con `X-Player-Token: demo-token-1` funciona si se sembró la demo.
- [ ] `php artisan parchis:cleanup-abandoned-rooms` informa sin borrar partidas terminadas.
- [ ] En Logs de Cloud no hay excepciones nuevas; en Monitoring la cola y los WebSockets tienen actividad.

---

## 10. Decisiones y limitaciones

### Decisiones cerradas

- Turno inicial del host y después por `seat`; sin aleatorizar.
- Casillas seguras: salida + punto medio de cada brazo ocupado.
- El 5 también mueve fichas desplegadas; sacar sigue siendo opcional.
- Tercer 6 consumido sin mover; sin pase voluntario.
- Victoria antes que turno extra; desempate por fichas en meta → `progress` → `seat`.
- Revancha por clonación de sala con nuevo `code`, copiando apodos/colores/seats/token_hash.
- Eventos atómicos con snapshot completo; animaciones solo visuales.
- Alternativa gratuita: Wasmer Edge + Pusher Sandbox; Reverb queda para local/Cloud.
- Dirección visual 2: plano moderno accesible, tokens CSS, `Inter` + `Manrope`,
  SVG propio, sonido Web Audio desactivado por defecto.
- Accesibilidad WCAG 2.2 AA: tablero `role="group"`, fichas enfocables, tabla
  textual, `role="status"` deduplicado por versión y `role="alert"` para errores.
- Sin temporizador de turno: añadirlo exigiría cambiar las reglas cerradas.

### Limitaciones pendientes

- Las capturas reales de pantalla están en
  [`docs/screenshots`](docs/screenshots/README.md).
- `composer types:check` (PHPStan/Larastan) y `npm run check` pasan sin errores.
- `composer run deploy:build` usa `npm install --no-audit --no-fund` para que
  funcione también en Windows; Cloud crea el build desde un checkout limpio.
- `npm run test:a11y` usa axe-core + jsdom. No se han ejecutado lectores de
  pantalla reales, axe en Chrome ni pruebas en dispositivos móviles.
- La partida demo usa tokens públicos a propósito; no debe sembrarse en un
  entorno donde se quiera privacidad.
- No hay barreras, capturas bonus ni arrival bonus: están excluidos de esta versión.

---

## 11. Capturas

Las capturas planificadas y las instrucciones para generarlas están en
[`docs/screenshots/README.md`](docs/screenshots/README.md). Todavía faltan los
archivos binarios reales (`01-welcome.png`, `02-waiting-room.png`, `03-board.png`,
`04-result.png`, `05-history.png`).
