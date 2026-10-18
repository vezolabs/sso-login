<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Saml;

use InvalidArgumentException;
use RuntimeException;

class CertificateManager
{
    public const string STATUS_VALID = 'VALID';
    public const string STATUS_EXPIRING_SOON = 'EXPIRING_SOON';
    public const string STATUS_EXPIRED = 'EXPIRED';
    public const string STATUS_INVALID = 'INVALID';

    public const int WARNING_DAYS = 30;

    /**
     * Parse and inspect an X.509 certificate.
     *
     * @return array{
     *     status: string,
     *     subject: string,
     *     issuer: string,
     *     valid_from: ?string,
     *     valid_to: ?string,
     *     days_left: int,
     *     fingerprint_sha1: string,
     *     fingerprint_sha256: string,
     *     raw_pem: string
     * }
     */
    public function inspect(string $rawCert): array
    {
        $pem = $this->formatToPem($rawCert);
        $parsed = @openssl_x509_parse($pem);

        if ($parsed === false) {
            return [
                'status' => self::STATUS_INVALID,
                'subject' => 'Unknown',
                'issuer' => 'Unknown',
                'valid_from' => null,
                'valid_to' => null,
                'days_left' => 0,
                'fingerprint_sha1' => '',
                'fingerprint_sha256' => '',
                'raw_pem' => $pem,
            ];
        }

        $validFrom = isset($parsed['validFrom_time_t'])
            ? (new \DateTimeImmutable('@' . $parsed['validFrom_time_t']))->format(\DateTimeInterface::ATOM)
            : null;
        $validToTime = $parsed['validTo_time_t'] ?? null;
        $validTo = $validToTime !== null
            ? (new \DateTimeImmutable('@' . $validToTime))->format(\DateTimeInterface::ATOM)
            : null;

        $now = time();
        $daysLeft = $validToTime !== null ? (int) floor(($validToTime - $now) / 86400) : 0;

        $status = self::STATUS_VALID;
        if ($validToTime !== null && $now > $validToTime) {
            $status = self::STATUS_EXPIRED;
        } elseif ($daysLeft <= self::WARNING_DAYS) {
            $status = self::STATUS_EXPIRING_SOON;
        }

        $cleanDer = $this->extractCleanDer($pem);
        $fingerprintSha1 = strtoupper(chunk_split(sha1($cleanDer), 2, ':'));
        $fingerprintSha1 = rtrim($fingerprintSha1, ':');

        $fingerprintSha256 = strtoupper(chunk_split(hash('sha256', $cleanDer), 2, ':'));
        $fingerprintSha256 = rtrim($fingerprintSha256, ':');

        $subjectName = $parsed['name'] ?? ($parsed['subject']['CN'] ?? 'Unknown');
        $issuerName = $parsed['issuer']['CN'] ?? ($parsed['issuer']['O'] ?? 'Unknown');

        return [
            'status' => $status,
            'subject' => (string) $subjectName,
            'issuer' => (string) $issuerName,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'days_left' => $daysLeft,
            'fingerprint_sha1' => $fingerprintSha1,
            'fingerprint_sha256' => $fingerprintSha256,
            'raw_pem' => $pem,
        ];
    }

    /**
     * Ensure certificate string is wrapped in standard PEM format.
     */
    public function formatToPem(string $cert): string
    {
        $cert = trim($cert);
        if (str_contains($cert, '-----BEGIN CERTIFICATE-----')) {
            return $cert . "\n";
        }

        // Strip non-base64 characters
        $base64 = preg_replace('/[^A-Za-z0-9+\/=]/', '', $cert);
        $chunked = chunk_split((string) $base64, 64, "\n");

        return "-----BEGIN CERTIFICATE-----\n" . trim($chunked) . "\n-----END CERTIFICATE-----\n";
    }

    /**
     * Alias for generateSelfSignedCertificate.
     *
     * @return array{cert: string, key: string}
     */
    public function generateSelfSignedCert(string $commonName, int $validDays = 365): array
    {
        return $this->generateSelfSignedCertificate($commonName, $validDays);
    }

    /**
     * Extract pure DER binary representation from PEM string.
     */
    public function extractCleanDer(string $pem): string
    {
        $clean = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);

        return base64_decode((string) $clean) ?: '';
    }

    /**
     * Generate self-signed SP X.509 certificate and private key.
     *
     * @return array{cert: string, key: string}
     */
    public function generateSelfSignedCertificate(string $commonName, int $validDays = 365): array
    {
        $dn = [
            'commonName' => $commonName,
            'organizationName' => 'Pimcore Studio',
            'organizationalUnitName' => 'Security',
            'countryName' => 'US',
        ];

        $privKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($privKey === false) {
            throw new RuntimeException('Failed to generate RSA private key.');
        }

        $csr = openssl_csr_new($dn, $privKey, ['digest_alg' => 'sha256']);
        if ($csr === false) {
            throw new RuntimeException('Failed to generate CSR.');
        }

        $x509 = openssl_csr_sign($csr, null, $privKey, $validDays, ['digest_alg' => 'sha256']);
        if ($x509 === false) {
            throw new RuntimeException('Failed to sign self-signed certificate.');
        }

        openssl_x509_export($x509, $certOut);
        openssl_pkey_export($privKey, $keyOut);

        return [
            'cert' => $certOut,
            'key' => $keyOut,
        ];
    }
}
