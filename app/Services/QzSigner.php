<?php

namespace App\Services;

use RuntimeException;

/**
 * Signs the requests that the browser sends to QZ Tray, so each computer can trust the app once and QZ Tray
 * stops asking for permission every time.
 */
class QzSigner
{
    public static function directory(): string
    {
        return storage_path('app/private/qz');
    }

    public static function certificatePath(): string
    {
        return self::directory().'/certificate.pem';
    }

    public static function privateKeyPath(): string
    {
        return self::directory().'/private-key.pem';
    }

    public function isConfigured(): bool
    {
        return is_file(self::certificatePath()) && is_file(self::privateKeyPath());
    }

    public function certificate(): string
    {
        return (string) file_get_contents(self::certificatePath());
    }

    /**
     * Creates a self-signed certificate and its private key (valid for ten years).
     *
     * @return array{certificate: string, private_key: string}
     */
    public function generate(string $commonName): array
    {
        $config = tempnam(sys_get_temp_dir(), 'qz');
        file_put_contents($config, '[req]
distinguished_name = dn
[dn]
');

        try {
            return $this->createFiles($commonName, $config);
        } finally {
            @unlink($config);
        }
    }

    /**
     * @return array{certificate: string, private_key: string}
     */
    private function createFiles(string $commonName, string $config): array
    {
        $options = ['config' => $config, 'digest_alg' => 'sha512'];
        $key = openssl_pkey_new($options + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false) {
            throw new RuntimeException('No se pudo crear la llave privada.');
        }

        $request = openssl_csr_new(['commonName' => $commonName, 'organizationName' => 'ML Grupo Comercial'], $key, $options);

        if (! $request instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('No se pudo crear la solicitud del certificado.');
        }

        $certificate = openssl_csr_sign($request, null, $key, 3650, $options);

        if ($certificate === false) {
            throw new RuntimeException('No se pudo firmar el certificado.');
        }

        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($key, $privateKeyPem, null, $options);

        return ['certificate' => $certificatePem, 'private_key' => $privateKeyPem];
    }

    /**
     * Signs the text QZ Tray asks for with SHA-512 and returns the signature in base64.
     */
    public function sign(string $message): string
    {
        $key = openssl_pkey_get_private((string) file_get_contents(self::privateKeyPath()));

        if ($key === false || ! openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA512)) {
            throw new RuntimeException('No se pudo firmar la solicitud.');
        }

        return base64_encode($signature);
    }
}
