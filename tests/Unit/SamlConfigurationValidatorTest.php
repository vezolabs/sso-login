<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Service\Saml\CertificateManager;
use Vezolabs\SsoLoginBundle\Service\Saml\SamlConfigurationValidator;

class SamlConfigurationValidatorTest extends TestCase
{
    private CertificateManager $certificateManager;
    private SamlConfigurationValidator $validator;

    protected function setUp(): void
    {
        $this->certificateManager = new CertificateManager();
        $this->validator = new SamlConfigurationValidator($this->certificateManager);
    }

    public function testFailsWhenProviderIsUnconfigured(): void
    {
        $provider = new IdentityProvider();
        $provider->setName('entra-id');

        $result = $this->validator->validate($provider);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('Missing Identity Provider Entity ID / Issuer URI.', $result['errors']);
        $this->assertContains('Must be a valid HTTPS Single Sign-On endpoint.', $result['errors']);
    }

    public function testPassesWhenProviderIsProperlyConfigured(): void
    {
        $certPair = $this->certificateManager->generateSelfSignedCert('Test IdP', 365);

        $provider = new IdentityProvider();
        $provider->setName('entra-id');
        $provider->setIdpEntityId('https://sts.windows.net/tenant-123/');
        $provider->setIdpSsoUrl('https://login.microsoftonline.com/tenant-123/saml2');
        $provider->setIdpX509Cert($certPair['cert']);
        $provider->setAttributeMapping([
            'email' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
            'external_id' => 'http://schemas.microsoft.com/identity/claims/objectidentifier',
            'firstname' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
            'lastname' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
            'displayname' => 'http://schemas.microsoft.com/identity/claims/displayname',
        ]);
        $provider->setRoleMapping([
            'GlobalAdmin' => 'admin',
        ]);
        $provider->setWantAssertionsSigned(true);

        $result = $this->validator->validate($provider);

        $this->assertTrue($result['valid'], 'Expected validation to pass but got errors: ' . implode('; ', $result['errors']));
        $this->assertEmpty($result['errors']);
    }

    public function testWarnsWhenAssertionSignatureIsNotEnforced(): void
    {
        $certPair = $this->certificateManager->generateSelfSignedCert('Test IdP', 365);

        $provider = new IdentityProvider();
        $provider->setName('entra-id');
        $provider->setIdpEntityId('https://sts.windows.net/tenant-123/');
        $provider->setIdpSsoUrl('https://login.microsoftonline.com/tenant-123/saml2');
        $provider->setIdpX509Cert($certPair['cert']);
        $provider->setAttributeMapping([
            'email' => 'email',
            'external_id' => 'oid',
        ]);
        $provider->setRoleMapping(['admin' => 'admin']);
        $provider->setWantAssertionsSigned(false);

        $result = $this->validator->validate($provider);

        $this->assertNotEmpty($result['warnings']);
        $this->assertTrue(
            array_reduce($result['warnings'], fn($carry, $item) => $carry || str_contains($item, 'assertion signatures'), false)
        );
    }
}
