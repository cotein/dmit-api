<?php

namespace App\Src\Services\Account;

/**
 * TOTP (RFC 6238): los códigos de 6 dígitos que genera una app autenticadora.
 *
 * Va escrito acá y no con un paquete (google2fa) a propósito: el vendor del API en
 * producción se monta desde el repo y agregar una dependencia obliga a tocar el
 * composer del servidor. El algoritmo es chico y estándar, y está verificado contra
 * los vectores de prueba del propio RFC (ver la verificación de TOTP en los tests).
 *
 * Parámetros fijos, que son los que asumen todas las apps: SHA-1, 6 dígitos,
 * período de 30 segundos. `verify()` acepta ±1 período para tolerar relojes
 * desfasados unos segundos.
 */
class TotpService
{
    private const DIGITS = 6;

    private const PERIOD = 30;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Secreto nuevo: 20 bytes al azar (160 bits), como recomienda el RFC. */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /** La URL que se convierte en QR para la app autenticadora. */
    public function otpauthUrl(string $account, string $secret, string $issuer = 'DMIT Facturador'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account) . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
    }

    /**
     * ¿El código corresponde al secreto AHORA? Se tolera un período para atrás y
     * uno para adelante (relojes desfasados, códigos que vencen mientras se escribe).
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = intdiv(time(), self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->at($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Código del período exacto (se usa en las pruebas con los vectores del RFC). */
    public function at(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);

        if ($key === '') {
            return '';
        }

        // Contador de 8 bytes big-endian (dos palabras de 32 bits, por si algún día
        // el contador pasa los 2^32 — hoy recién en el año 6000).
        $binario = pack('N*', intdiv($counter, 4294967296)) . pack('N*', $counter % 4294967296);
        $hash = hash_hmac('sha1', $binario, $key, true);

        // Truncamiento dinámico (RFC 4226 §5.3).
        $offset = ord($hash[19]) & 0x0F;
        $parte = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($parte % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $binario): string
    {
        $bits = '';

        foreach (str_split($binario) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $salida = '';

        foreach (str_split($bits, 5) as $grupo) {
            if (strlen($grupo) < 5) {
                $grupo = str_pad($grupo, 5, '0', STR_PAD_RIGHT);
            }

            $salida .= self::ALPHABET[bindec($grupo)];
        }

        return $salida;
    }

    private function base32Decode(string $texto): string
    {
        $texto = strtoupper(str_replace([' ', '=', '-'], '', $texto));

        if ($texto === '') {
            return '';
        }

        $bits = '';

        foreach (str_split($texto) as $letra) {
            $pos = strpos(self::ALPHABET, $letra);

            if ($pos === false) {
                return '';
            }

            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $salida = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $salida .= chr(bindec($byte));
            }
        }

        return $salida;
    }
}
