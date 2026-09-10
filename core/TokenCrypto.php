<?php
/**
 * GestorPro — TokenCrypto
 * Criptografa/descriptografa tokens de acesso (Meta, Google) antes de salvar no banco.
 * Algoritmo: AES-256-CBC com IV aleatório por operação.
 * A chave é derivada do APP_SECRET via HKDF (SHA-256).
 *
 * INSTALAÇÃO:
 *   Coloque este arquivo em /core/TokenCrypto.php
 *   Ele é carregado automaticamente pelo autoload do App.php
 *
 * USO:
 *   $enc = TokenCrypto::encrypt($plainToken);  // antes de salvar no banco
 *   $tok = TokenCrypto::decrypt($enc);          // depois de ler do banco
 */
class TokenCrypto
{
    private const CIPHER  = 'aes-256-cbc';
    private const PREFIX  = 'ENC:'; // marcador — evita double-encrypt

    /** Retorna a chave de 32 bytes derivada do APP_SECRET */
    private static function key(): string
    {
        $secret = defined('APP_SECRET') ? APP_SECRET : getenv('APP_SECRET');
        if (!$secret) {
            throw new \RuntimeException('APP_SECRET não definido — TokenCrypto não pode operar.');
        }
        // HKDF-like: hash_hmac(SHA-256, "token_encryption_key", APP_SECRET) → 32 bytes
        return hash_hmac('sha256', 'gestorpro_token_encryption_v1', $secret, true);
    }

    /**
     * Criptografa um token.
     * Retorna string Base64 no formato:  ENC:<base64(iv + ciphertext)>
     * Se o valor já estiver criptografado (prefixo ENC:), retorna sem modificar.
     * Se o valor estiver vazio, retorna string vazia.
     */
    public static function encrypt(string $plain): string
    {
        if ($plain === '') return '';
        if (str_starts_with($plain, self::PREFIX)) return $plain; // já criptografado

        $iv         = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $ciphertext = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Falha ao criptografar token.');
        }
        return self::PREFIX . base64_encode($iv . $ciphertext);
    }

    /**
     * Descriptografa um token.
     * Aceita tanto tokens já criptografados (prefixo ENC:) quanto tokens em texto
     * puro (legado — tokens salvos antes da migração), retornando-os sem modificar.
     * Se o valor estiver vazio, retorna string vazia.
     */
    public static function decrypt(string $stored): string
    {
        if ($stored === '') return '';
        if (!str_starts_with($stored, self::PREFIX)) {
            // Token legado em texto puro — retorna como está
            // (após reconexão da conta, será salvo criptografado)
            return $stored;
        }

        $raw        = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false) return '';

        $ivLen      = openssl_cipher_iv_length(self::CIPHER);
        $iv         = substr($raw, 0, $ivLen);
        $ciphertext = substr($raw, $ivLen);

        $plain = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv);
        return ($plain !== false) ? $plain : '';
    }

    /**
     * Verifica se um valor já está criptografado.
     */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
