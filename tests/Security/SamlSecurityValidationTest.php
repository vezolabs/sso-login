<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use RobRob\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Service\Saml\CertificateManager;
use Vezolabs\SsoLoginBundle\Service\Saml\SamlService;

class SamlSecurityValidationTest extends TestCase
{
    private CertificateManager $certificateManager;
    private SamlService $samlService;

    protected function setUp(): void
    {
        $this->certificateManager = new CertificateManager();
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->willReturnCallback(fn(string $route, array $params = []) => 'https://pimcore.example.com/' . $route);

        $this->samlService = new SamlService($urlGenerator, $this->certificateManager);
    }

    public function testSamlSettingsEnforceStrictSecurityParameters(): void
    {
        $certPair = $this->certificateManager->generateSelfSignedCert('Security Test IdP', 365);

        $provider = new IdentityProvider();
        $provider->setName('entra-id');
        $provider->setIdpEntityId('https://sts.windows.net/tenant-id/');
        $provider->setIdpSsoUrl('https://login.microsoftonline.com/tenant-id/saml2');
        $provider->setIdpX509Cert($certPair['cert']);
        $provider->setWantAssertionsSigned(true);
        $provider->setWantResponseSigned(true);

        $request = Request::create('https://pimcore.example.com/sso/login/entra-id');
        $settings = $this->samlService->buildSettings($provider, $request);

        $this->assertTrue($settings['strict'], 'SAML strict mode must always be true.');
        $this->assertFalse($settings['debug'], 'Debug mode must default to false in production settings.');
        $this->assertTrue($settings['security']['wantXMLValidation'], 'XML validation must be strictly enforced.');
        $this->assertTrue($settings['security']['wantAssertionsSigned'], 'Assertion signatures must be required.');
        $this->assertTrue($settings['security']['wantMessagesSigned'], 'Message signatures must be required.');
        $this->assertSame(
            XMLSecurityKey::RSA_SHA256,
            $settings['security']['signatureAlgorithm'],
            'Cryptographic signature algorithm must be RSA-SHA256.'
        );
        $this->assertSame(
            'http://www.w3.org/2001/04/xmlenc#sha256',
            $settings['security']['digestAlgorithm'],
            'Cryptographic digest algorithm must be SHA256.'
        );
    }

    public function testCertificateManagerDetectsTamperedCertificates(): void
    {
        $validPair = $this->certificateManager->generateSelfSignedCert('Valid', 365);
        $tamperedCert = str_replace('A', 'Z', $validPair['cert']);

        $info = $this->certificateManager->inspect($tamperedCert);

        $this->assertNotSame(
            CertificateManager::STATUS_VALID,
            $info['status'],
            'Tampered or corrupted X.509 certificate must not report as VALID.'
        );
    }

    public function testMultiCertRolloverIncludesBothKeysInSecuritySettings(): void
    {
        $primaryPair = $this->certificateManager->generateSelfSignedCert('Primary', 365);
        $rolloverPair = $this->certificateManager->generateSelfSignedCert('Rollover', 365);

        $provider = new IdentityProvider();
        $provider->setName('entra-id');
        $provider->setIdpEntityId('https://sts.windows.net/tenant-id/');
        $provider->setIdpSsoUrl('https://login.microsoftonline.com/tenant-id/saml2');
        $provider->setIdpX509Cert($primaryPair['cert']);
        $provider->setIdpX509CertRollover($rolloverPair['cert']);

        $request = Request::create('https://pimcore.example.com/sso/login/entra-id');
        $settings = $this->samlService->buildSettings($provider, $request);

        $this->assertArrayHasKey('x509certMulti', $settings['idp']);
        $this->assertArrayHasKey('signing', $settings['idp']['x509certMulti']);
        $this->assertCount(2, $settings['idp']['x509certMulti']['signing']);
    }
}
