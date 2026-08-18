<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stripe\ApiRequestor;
use Tests\Support\StripeFalso;

abstract class TestCase extends BaseTestCase
{
    protected StripeFalso $stripe;

    /**
     * Ningun test sale a internet.
     *
     * El SDK de Stripe guarda su cliente HTTP en estatico, asi que basta con
     * que un test lo deje puesto para que el siguiente herede una conexion
     * real. Instalarlo aqui, en todos, convierte un descuido —una llamada a
     * la pasarela desde un test que no la esperaba— en un fallo inmediato y
     * legible en vez de en una peticion de verdad contra la cuenta de Stripe.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe = StripeFalso::instalar();

        // Claves de mentira, pero con la forma que espera el SDK: sin ellas
        // el cliente ni siquiera llega a construir la peticion, y los tests
        // dependerian del `.env` de quien los ejecute.
        config([
            'services.stripe.key' => 'pk_test_falsa',
            'services.stripe.secret' => 'sk_test_falsa',
            'services.stripe.webhook.secret' => 'whsec_falsa',

            /*
             * Sin recoleccion de sesiones caducadas.
             *
             * La sesion vive en base de datos y Laravel la limpia por sorteo:
             * `[2, 100]` significa que una de cada cincuenta peticiones lanza
             * un DELETE de mas. Medido: 3 consultas sin recoleccion, 4 con
             * ella.
             *
             * A cualquier test le da igual, salvo a los que **cuentan
             * consultas** para probar que no hay N+1 (PERF-001). Ahi el
             * sorteo hacia que una de cada once ejecuciones de la bateria
             * comparase 3 con 4 y fallara por algo que no tiene nada que ver
             * con lo que se esta probando.
             *
             * Se apaga aqui y no en el test que lo sufrio porque el problema
             * no es de ese test: es de cualquiera que mida.
             */
            'session.lottery' => [0, 100],
        ]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }
}
