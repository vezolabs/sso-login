<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Saml;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\Settings;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;

class SamlService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CertificateManager $certificateManager
    ) {
    }

    /**
     * Build OneLogin SAML settings array for the given provider and request.
     *
     * @return array<string, mixed>
     */
    public function buildSettings(IdentityProvider $provider, Request $request): array
    {
        $defaultAcsUrl = $this->urlGenerator->generate(
            'vezolabs_sso_acs',
            ['providerSlug' => $provider->getName()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $defaultEntityId = $this->urlGenerator->generate(
            'vezolabs_sso_metadata',
            ['providerSlug' => $provider->getName()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $spAcsUrl = !empty($provider->getSpAcsUrl()) ? $provider->getSpAcsUrl() : $defaultAcsUrl;
        $spEntityId = !empty($provider->getSpEntityId()) ? $provider->getSpEntityId() : $defaultEntityId;

        $primaryCertBase64 = '';
        if (!empty(trim((string) $provider->getIdpX509Cert()))) {
            try {
                $primaryCert = $this->certificateManager->extractCleanDer(
                    $this->certificateManager->formatToPem($provider->getIdpX509Cert())
                );
                $primaryCertBase64 = base64_encode($primaryCert);
            } catch (\Throwable) {
                $primaryCertBase64 = '';
            }
        }

        $idpConfig = [
            'entityId' => $provider->getIdpEntityId() ?: '',
            'singleSignOnService' => [
                'url' => $provider->getIdpSsoUrl() ?: '',
                'binding' => Constants::BINDING_HTTP_REDIRECT,
            ],
            'x509cert' => $primaryCertBase64,
        ];

        if (!empty($provider->getIdpSloUrl())) {
            $idpConfig['singleLogoutService'] = [
                'url' => $provider->getIdpSloUrl(),
                'binding' => Constants::BINDING_HTTP_REDIRECT,
            ];
        }

        // If rollover cert is present, configure multi-cert validation
        if (!empty(trim((string) $provider->getIdpX509CertRollover()))) {
            try {
                $rolloverCert = $this->certificateManager->extractCleanDer(
                    $this->certificateManager->formatToPem($provider->getIdpX509CertRollover())
                );
                $rolloverCertBase64 = base64_encode($rolloverCert);

                $idpConfig['x509certMulti'] = [
                    'signing' => [
                        $primaryCertBase64,
                        $rolloverCertBase64,
                    ],
                ];
            } catch (\Throwable) {
                // Ignore invalid rollover cert during settings build
            }
        }

        $spConfig = [
            'entityId' => $spEntityId,
            'assertionConsumerService' => [
                'url' => $spAcsUrl,
                'binding' => Constants::BINDING_HTTP_POST,
            ],
            'NameIDFormat' => $provider->getNameIdFormat(),
        ];

        if (!empty($provider->getSpSloUrl())) {
            $spConfig['singleLogoutService'] = [
                'url' => $provider->getSpSloUrl(),
                'binding' => Constants::BINDING_HTTP_REDIRECT,
            ];
        }

        if (!empty($provider->getSpX509Cert())) {
            $spConfig['x509cert'] = $provider->getSpX509Cert();
        }

        if (!empty($provider->getSpPrivateKey())) {
            $spConfig['privateKey'] = $provider->getSpPrivateKey();
        }

        return [
            'strict' => true,
            'debug' => false,
            'sp' => $spConfig,
            'idp' => $idpConfig,
            'security' => [
                'nameIdEncrypted' => false,
                'authnRequestsSigned' => $provider->isSignAuthnRequests(),
                'logoutRequestSigned' => false,
                'logoutResponseSigned' => false,
                'wantMessagesSigned' => $provider->isWantResponseSigned(),
                'wantAssertionsSigned' => $provider->isWantAssertionsSigned(),
                'wantAssertionsEncrypted' => false,
                'wantNameId' => true,
                'wantNameIdEncrypted' => false,
                'requestedAuthnContext' => false,
                'signMetadata' => false,
                'wantXMLValidation' => true,
                'signatureAlgorithm' => XMLSecurityKey::RSA_SHA256,
                'digestAlgorithm' => XMLSecurityDSig::SHA256,
                'lowercaseUrlencoding' => false,
            ],
        ];
    }

    public function createAuth(IdentityProvider $provider, Request $request): Auth
    {
        $settings = $this->buildSettings($provider, $request);

        return new Auth($settings);
    }

    /**
     * Generate IdP redirect URL for SP-initiated SSO.
     */
    public function generateLoginUrl(IdentityProvider $provider, Request $request, ?string $returnTo = null): string
    {
        $auth = $this->createAuth($provider, $request);

        return $auth->login($returnTo, [], false, false, true);
    }

    /**
     * Process incoming SAML 2.0 response in ACS.
     *
     * @return array{
     *     name_id: string,
     *     name_id_format: string,
     *     session_index: ?string,
     *     attributes: array<string, array<string>>,
     *     relay_state: ?string
     * }
     */
    public function processResponse(IdentityProvider $provider, Request $request): array
    {
        $auth = $this->createAuth($provider, $request);

        try {
            $auth->processResponse();
        } catch (\Exception $e) {
            throw new RuntimeException('SAML response processing failed: ' . $e->getMessage(), 0, $e);
        }

        $errors = $auth->getErrors();
        if (!empty($errors)) {
            $reason = $auth->getLastErrorReason();
            throw new RuntimeException(sprintf('SAML validation failed: %s (%s)', implode(', ', $errors), $reason));
        }

        if (!$auth->isAuthenticated()) {
            throw new RuntimeException('SAML assertion rejected: user is not authenticated.');
        }

        $nameId = $auth->getNameId();
        $nameIdFormat = $auth->getNameIdFormat();
        $sessionIndex = $auth->getSessionIndex();
        $attributes = $auth->getAttributes();
        $relayState = $request->request->get('RelayState');

        return [
            'name_id' => (string) $nameId,
            'name_id_format' => (string) $nameIdFormat,
            'session_index' => $sessionIndex ?: null,
            'attributes' => $attributes,
            'relay_state' => $relayState ?: null,
        ];
    }

    /**
     * Generate SP XML Metadata for download/registration in Azure.
     */
    public function generateSpMetadata(IdentityProvider $provider, Request $request): string
    {
        $settingsArray = $this->buildSettings($provider, $request);
        $settings = new Settings($settingsArray, true);
        $metadata = $settings->getSPMetadata();

        $errors = $settings->validateMetadata($metadata);
        if (!empty($errors)) {
            throw new RuntimeException('Invalid SP metadata generated: ' . implode(', ', $errors));
        }

        return $metadata;
    }
}
