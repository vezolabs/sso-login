<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vezolabs\SsoLoginBundle\Service\Saml\MetadataParser;

class MetadataParserTest extends TestCase
{
    private MetadataParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MetadataParser();
    }

    public function testParsesValidEntraIdMetadataXml(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<EntityDescriptor ID="_8c3f7b52-9b2d-45f8-8ef7-123456789abc" entityID="https://sts.windows.net/72f988bf-86f1-41af-91ab-2d7cd011db47/" xmlns="urn:oasis:names:tc:SAML:2.0:metadata">
    <IDPSSODescriptor WantAuthnRequestsSigned="true" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <KeyDescriptor use="signing">
            <KeyInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
                <X509Data>
                    <X509Certificate>MIIDBTCCAe2gAwIBAgIQJn0p4...CERT1DATA...</X509Certificate>
                </X509Data>
            </KeyInfo>
        </KeyDescriptor>
        <KeyDescriptor use="signing">
            <KeyInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
                <X509Data>
                    <X509Certificate>MIIDBTCCAe2gAwIBAgIQJn0p4...CERT2ROLLOVER...</X509Certificate>
                </X509Data>
            </KeyInfo>
        </KeyDescriptor>
        <SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://login.microsoftonline.com/72f988bf-86f1-41af-91ab-2d7cd011db47/saml2" />
        <SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://login.microsoftonline.com/72f988bf-86f1-41af-91ab-2d7cd011db47/saml2" />
        <SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="https://login.microsoftonline.com/72f988bf-86f1-41af-91ab-2d7cd011db47/saml2" />
    </IDPSSODescriptor>
</EntityDescriptor>
XML;

        $result = $this->parser->parse($xml);

        $this->assertSame('https://sts.windows.net/72f988bf-86f1-41af-91ab-2d7cd011db47/', $result['entity_id']);
        $this->assertSame('https://login.microsoftonline.com/72f988bf-86f1-41af-91ab-2d7cd011db47/saml2', $result['sso_url']);
        $this->assertSame('https://login.microsoftonline.com/72f988bf-86f1-41af-91ab-2d7cd011db47/saml2', $result['slo_url']);
        $this->assertCount(2, $result['signing_certificates']);
        $this->assertStringContainsString('MIIDBTCCAe2gAwIBAgIQJn0p4...CERT1DATA...', $result['primary_cert']);
        $this->assertNotNull($result['rollover_cert']);
        $this->assertStringContainsString('MIIDBTCCAe2gAwIBAgIQJn0p4...CERT2ROLLOVER...', $result['rollover_cert']);
    }

    public function testRejectsEmptyXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Metadata XML is empty.');
        $this->parser->parse('   ');
    }

    public function testRejectsMalformedXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse metadata XML');
        $this->parser->parse('<EntityDescriptor>unclosed tag');
    }

    public function testRejectsXmlWithoutEntityDescriptor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid SAML metadata: <EntityDescriptor> root element not found.');
        $this->parser->parse('<Root><Child>None</Child></Root>');
    }

    public function testRejectsXmlWithoutEntityId(): void
    {
        $xml = '<EntityDescriptor xmlns="urn:oasis:names:tc:SAML:2.0:metadata"></EntityDescriptor>';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('entityID attribute is missing');
        $this->parser->parse($xml);
    }

    public function testXxeInjectionIsSafelyPrevented(): void
    {
        $xxePayload = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE test [
  <!ENTITY % xxe SYSTEM "file:///etc/passwd">
  %xxe;
]>
<EntityDescriptor entityID="https://example.com/idp" xmlns="urn:oasis:names:tc:SAML:2.0:metadata">
    <IDPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <KeyDescriptor use="signing">
            <KeyInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
                <X509Data>
                    <X509Certificate>MII...CERT...</X509Certificate>
                </X509Data>
            </KeyInfo>
        </KeyDescriptor>
        <SingleSignOnService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://example.com/sso" />
    </IDPSSODescriptor>
</EntityDescriptor>
XML;

        // Either parsing rejects the DTD or parses safely without expanding /etc/passwd
        try {
            $result = $this->parser->parse($xxePayload);
            // If parsed, verify no system data was injected into entity_id or sso_url
            $this->assertSame('https://example.com/idp', $result['entity_id']);
            $this->assertSame('https://example.com/sso', $result['sso_url']);
        } catch (RuntimeException $e) {
            // Rejection of DTD or invalid XML syntax is also a secure behavior
            $this->assertTrue(true);
        }
    }
}
