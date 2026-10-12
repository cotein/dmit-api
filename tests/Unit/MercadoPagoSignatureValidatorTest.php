<?php

namespace Tests\Unit;

use App\Src\Services\MercadoPago\MercadoPagoSignatureValidator;
use Tests\TestCase;

/**
 * La firma del webhook es la única puerta que separa "Mercado Pago avisó que
 * pagaron" de "cualquiera que conozca la URL inventó un pago aprobado".
 */
class MercadoPagoSignatureValidatorTest extends TestCase
{
    private const SECRETO = 'clave-secreta-de-prueba';

    private function validador(): MercadoPagoSignatureValidator
    {
        return new MercadoPagoSignatureValidator;
    }

    /** Arma el header tal cual lo manda Mercado Pago. */
    private function firma(string $manifest, string $secreto = self::SECRETO): string
    {
        return 'ts=1791659875,v1=' . hash_hmac('sha256', $manifest, $secreto);
    }

    public function test_acepta_una_firma_valida(): void
    {
        config(['mercadopago.webhook_secret' => self::SECRETO]);

        $manifest = $this->validador()->manifest('123456', 'req-1', '1791659875');

        $this->assertTrue(
            $this->validador()->isValid($this->firma($manifest), 'req-1', '123456')
        );
    }

    public function test_el_manifest_respeta_el_formato_de_mercado_pago(): void
    {
        // Si cambia este formato hay que revisar la integración: Mercado Pago
        // firma exactamente esta cadena, con ese orden y esos puntos y coma.
        $this->assertSame(
            'id:123456;request-id:req-1;ts:1791659875;',
            $this->validador()->manifest('123456', 'req-1', '1791659875')
        );
    }

    public function test_rechaza_una_firma_hecha_con_otro_secreto(): void
    {
        config(['mercadopago.webhook_secret' => self::SECRETO]);

        $manifest = $this->validador()->manifest('123456', 'req-1', '1791659875');

        $this->assertFalse(
            $this->validador()->isValid($this->firma($manifest, 'secreto-del-atacante'), 'req-1', '123456')
        );
    }

    public function test_rechaza_si_cambia_el_id_del_recurso(): void
    {
        config(['mercadopago.webhook_secret' => self::SECRETO]);

        // Firma legítima de otro pago, reusada para este: sirve para inflar un cobro.
        $manifestDeOtro = $this->validador()->manifest('999999', 'req-1', '1791659875');

        $this->assertFalse(
            $this->validador()->isValid($this->firma($manifestDeOtro), 'req-1', '123456')
        );
    }

    public function test_sin_secreto_configurado_rechaza_todo(): void
    {
        config(['mercadopago.webhook_secret' => null]);

        $manifest = $this->validador()->manifest('123456', 'req-1', '1791659875');

        $this->assertFalse(
            $this->validador()->isValid($this->firma($manifest), 'req-1', '123456')
        );
    }

    public function test_rechaza_un_header_mal_formado_o_ausente(): void
    {
        config(['mercadopago.webhook_secret' => self::SECRETO]);

        $this->assertFalse($this->validador()->isValid(null, 'req-1', '123456'));
        $this->assertFalse($this->validador()->isValid('sin-formato', 'req-1', '123456'));
        $this->assertFalse($this->validador()->isValid('ts=1791659875', 'req-1', '123456'));
    }

    public function test_rechaza_si_no_viene_el_id_del_recurso(): void
    {
        config(['mercadopago.webhook_secret' => self::SECRETO]);

        $manifest = $this->validador()->manifest('123456', 'req-1', '1791659875');

        $this->assertFalse($this->validador()->isValid($this->firma($manifest), 'req-1', null));
    }
}
