<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| SEC-013: v1 no publicaba este fichero, asi que aplicaba el default del
| framework — `allowed_origins: ['*']` sobre `api/*`. Con el token en
| cabecera eso era solo un fallo de endurecimiento; con las cookies de
| sesion de Sanctum (D2) seria critico, porque un origen cualquiera podria
| emitir peticiones autenticadas con la cookie de la victima.
|
| Los origenes se declaran explicitamente y `supports_credentials` va a true
| para que el navegador acepte la cookie de sesion.
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // En local la SPA corre en Vite (5173) y hace proxy hacia :8000, asi que
    // la mayoria de peticiones son same-origin. Esta lista cubre el acceso
    // directo durante el desarrollo. Nunca '*': es incompatible con cookies.
    'allowed_origins' => array_filter(explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173'
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'Origin'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
