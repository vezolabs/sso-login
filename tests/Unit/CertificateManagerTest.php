<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vezolabs\SsoLoginBundle\Service\Saml\CertificateManager;

class CertificateManagerTest extends TestCase
{
    private CertificateManager $certificateManager;

    protected function setUp(): void
    {
        $this->certificateManager = new CertificateManager();
    }

    public function testFormatToPemWrapsRawBase64(): void
    {
        $rawBase64 = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Y3F6v';
        $pem = $this->certificateManager->formatToPem($rawBase64);

        $this->assertStringStartsWith("-----BEGIN CERTIFICATE-----\n", $pem);
        $this->assertStringEndsWith("-----END CERTIFICATE-----\n", $pem);
        $this->assertStringContainsString($rawBase64, $pem);
    }

    public function testFormatToPemPreservesExistingHeaders(): void
    {
        $existingPem = "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----\n";
        $pem = $this->certificateManager->formatToPem($existingPem);

        $this->assertSame($existingPem, $pem);
    }

    public function testExtractCleanDer(): void
    {
        $raw = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8=';
        $pem = "-----BEGIN CERTIFICATE-----\n" . $raw . "\n-----END CERTIFICATE-----\n";

        $der = $this->certificateManager->extractCleanDer($pem);
        $this->assertSame(base64_decode($raw), $der);
    }

    public function testInspectMalformedCertificateReturnsInvalidStatus(): void
    {
        $info = $this->certificateManager->inspect('not-a-certificate-at-all');

        $this->assertSame(CertificateManager::STATUS_INVALID, $info['status']);
        $this->assertSame('Unknown', $info['subject']);
        $this->assertNull($info['valid_from']);
        $this->assertNull($info['valid_to']);
        $this->assertSame(0, $info['days_left']);
    }

    public function testGenerateSelfSignedCertAndInspect(): void
    {
        $generated = $this->certificateManager->generateSelfSignedCert('Pimcore SSO SP', 365);

        $this->assertArrayHasKey('cert', $generated);
        $this->assertArrayHasKey('key', $generated);
        $this->assertStringStartsWith('-----BEGIN CERTIFICATE-----', $generated['cert']);
        $this->assertStringStartsWith('-----BEGIN PRIVATE KEY-----', $generated['key']);

        $info = $this->certificateManager->inspect($generated['cert']);

        $this->assertSame(CertificateManager::STATUS_VALID, $info['status']);
        $this->assertGreaterThanOrEqual(364, $info['days_left']);
        $this->assertNotEmpty($info['fingerprint_sha1']);
        $this->assertNotEmpty($info['fingerprint_sha256']);
        $this->assertMatchesRegularExpression('/^([0-9A-F]{2}:){19}[0-9A-F]{2}$/', $info['fingerprint_sha1']);
        $this->assertMatchesRegularExpression('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $info['fingerprint_sha256']);
    }
}
