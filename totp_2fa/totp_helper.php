<?php
/**
 * totp_helper.php
 * Implementación TOTP (RFC 6238) en PHP puro. Sin librerías externas.
 */

/**
 * Genera una clave secreta aleatoria en Base32 (compatible con Google Authenticator).
 */
function totp_generar_secret(): string
{
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 16; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Decodifica Base32 a binario.
 */
function totp_base32_decode(string $secret): string
{
    $map    = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $secret = strtoupper($secret);
    $bin    = '';
    $buf    = 0;
    $bits   = 0;

    foreach (str_split($secret) as $c) {
        if (!isset($map[$c])) continue;
        $buf  = ($buf << 5) | $map[$c];
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $bin  .= chr(($buf >> $bits) & 0xFF);
        }
    }
    return $bin;
}

/**
 * Calcula el código TOTP para un secret y un contador (timestamp / 30).
 */
function totp_calcular(string $secret, int $offset = 0): string
{
    $key     = totp_base32_decode($secret);
    $counter = intdiv(time(), 30) + $offset;
    $packed  = pack('J', $counter);          // big-endian 64-bit
    $hash    = hash_hmac('sha1', $packed, $key, true);
    $offset  = ord($hash[19]) & 0x0F;
    $code    = (
        ((ord($hash[$offset])     & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
         (ord($hash[$offset + 3]) & 0xFF)
    ) % 1_000_000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verifica un código TOTP aceptando ±1 ventana de 30 s (tolerancia de reloj).
 */
function totp_verificar(string $secret, string $codigo): bool
{
    $codigo = trim($codigo);
    foreach ([-1, 0, 1] as $offset) {
        if (hash_equals(totp_calcular($secret, $offset), $codigo)) {
            return true;
        }
    }
    return false;
}

/**
 * Devuelve la URL otpauth:// para generar el QR.
 */
function totp_otpauth(string $secret, string $usuario, string $issuer = 'Sistema SDL'): string
{
    return 'otpauth://totp/'
        . rawurlencode($issuer) . ':' . rawurlencode($usuario)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

/**
 * URL de la API de Google Charts para generar el QR (sin instalar nada).
 */
function totp_qr_url(string $otpauth, int $size = 200): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size='
        . $size . 'x' . $size . '&data=' . rawurlencode($otpauth);
}
