<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Saml;

use DOMDocument;
use DOMXPath;
use RuntimeException;

class MetadataParser
{
    /**
     * Parse SAML 2.0 Federation Metadata XML securely.
     *
     * @return array{
     *     entity_id: string,
     *     sso_url: string,
     *     slo_url: ?string,
     *     signing_certificates: array<string>,
     *     primary_cert: string,
     *     rollover_cert: ?string
     * }
     */
    public function parse(string $xmlContent): array
    {
        if (trim($xmlContent) === '') {
            throw new RuntimeException('Metadata XML is empty.');
        }

        // XXE Protection: Disable entity loading and external DTD
        $previousEntityLoader = false;
        if (\PHP_VERSION_ID < 80000 && \function_exists('libxml_disable_entity_loader')) {
            $previousEntityLoader = @libxml_disable_entity_loader(true);
        }
        libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;

        // Disallow dangerous options and network access
        $options = LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR;

        $loaded = $doc->loadXML($xmlContent, $options);

        // Restore entity loader if available in older PHP
        if (\PHP_VERSION_ID < 80000 && \function_exists('libxml_disable_entity_loader')) {
            @libxml_disable_entity_loader($previousEntityLoader);
        }

        if (!$loaded) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = !empty($errors) ? $errors[0]->message : 'Invalid XML syntax';
            throw new RuntimeException('Failed to parse metadata XML: ' . trim($message));
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('md', 'urn:oasis:names:tc:SAML:2.0:metadata');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        // Extract Entity ID
        $entityDescriptor = $xpath->query('//md:EntityDescriptor');
        if ($entityDescriptor === false || $entityDescriptor->length === 0) {
            throw new RuntimeException('Invalid SAML metadata: <EntityDescriptor> root element not found.');
        }

        /** @var \DOMElement $rootNode */
        $rootNode = $entityDescriptor->item(0);
        $entityId = $rootNode->getAttribute('entityID');
        if (empty($entityId)) {
            throw new RuntimeException('Invalid SAML metadata: entityID attribute is missing from EntityDescriptor.');
        }

        // Extract SingleSignOnService (HTTP-Redirect or HTTP-POST)
        $ssoNodes = $xpath->query('//md:IDPSSODescriptor/md:SingleSignOnService');
        $ssoUrl = '';
        if ($ssoNodes && $ssoNodes->length > 0) {
            /** @var \DOMElement $ssoNode */
            foreach ($ssoNodes as $ssoNode) {
                $binding = $ssoNode->getAttribute('Binding');
                $location = $ssoNode->getAttribute('Location');
                if (str_contains($binding, 'HTTP-Redirect')) {
                    $ssoUrl = $location;
                    break;
                }
                if ($ssoUrl === '' && !empty($location)) {
                    $ssoUrl = $location;
                }
            }
        }

        if (empty($ssoUrl)) {
            throw new RuntimeException('Invalid SAML metadata: No SingleSignOnService endpoint found.');
        }

        // Extract SingleLogoutService
        $sloNodes = $xpath->query('//md:IDPSSODescriptor/md:SingleLogoutService');
        $sloUrl = null;
        if ($sloNodes && $sloNodes->length > 0) {
            /** @var \DOMElement $sloNode */
            foreach ($sloNodes as $sloNode) {
                $binding = $sloNode->getAttribute('Binding');
                $location = $sloNode->getAttribute('Location');
                if (str_contains($binding, 'HTTP-Redirect')) {
                    $sloUrl = $location;
                    break;
                }
                if ($sloUrl === null && !empty($location)) {
                    $sloUrl = $location;
                }
            }
        }

        // Extract Signing Certificates
        $certNodes = $xpath->query(
            '//md:IDPSSODescriptor/md:KeyDescriptor[not(@use) or @use="signing"]/ds:KeyInfo/ds:X509Data/ds:X509Certificate'
        );

        $signingCertificates = [];
        if ($certNodes && $certNodes->length > 0) {
            /** @var \DOMElement $certNode */
            foreach ($certNodes as $certNode) {
                $cert = trim($certNode->nodeValue ?? '');
                $cert = preg_replace('/\s+/', '', $cert);
                if (!empty($cert) && !\in_array($cert, $signingCertificates, true)) {
                    $signingCertificates[] = $cert;
                }
            }
        }

        if (empty($signingCertificates)) {
            throw new RuntimeException('Invalid SAML metadata: No valid X.509 signing certificates found.');
        }

        $primaryCert = $signingCertificates[0];
        $rolloverCert = \count($signingCertificates) > 1 ? $signingCertificates[1] : null;

        return [
            'entity_id' => $entityId,
            'sso_url' => $ssoUrl,
            'slo_url' => $sloUrl,
            'signing_certificates' => $signingCertificates,
            'primary_cert' => $primaryCert,
            'rollover_cert' => $rolloverCert,
        ];
    }
}
