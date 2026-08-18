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
        ]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }
}
