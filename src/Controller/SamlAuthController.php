<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Vezolabs\SsoLoginBundle\Entity\AuditLog;
use Vezolabs\SsoLoginBundle\Repository\IdentityProviderRepository;
use Vezolabs\SsoLoginBundle\Security\SsoSessionManager;
use Vezolabs\SsoLoginBundle\Service\Audit\AuditLogger;
use Vezolabs\SsoLoginBundle\Service\Diagnostics\SsoDiagnosticsService;
use Vezolabs\SsoLoginBundle\Service\Provisioning\UserProvisioningService;
use Vezolabs\SsoLoginBundle\Service\Saml\SamlService;

class SamlAuthController extends AbstractController
{
    public function __construct(
        private readonly IdentityProviderRepository $providerRepository,
        private readonly SamlService $samlService,
        private readonly UserProvisioningService $provisioningService,
        private readonly SsoSessionManager $sessionManager,
        private readonly AuditLogger $auditLogger,
        private readonly SsoDiagnosticsService $diagnosticsService
    ) {
    }

    /**
     * Start SP-Initiated SAML 2.0 login.
     */
    #[Route('/sso/login/{providerSlug}', name: 'vezolabs_sso_login', methods: ['GET'])]
    public function login(string $providerSlug, Request $request): Response
    {
        $provider = $this->providerRepository->findByName($providerSlug);

        if ($provider === null || !$provider->isEnabled()) {
            return new Response('SSO Provider is disabled or not found.', Response::HTTP_NOT_FOUND);
        }

        $returnTo = $request->query->get('returnTo', '/pimcore-studio/');
        $loginUrl = $this->samlService->generateLoginUrl($provider, $request, $returnTo);

        return new RedirectResponse($loginUrl);
    }

    /**
     * SAML 2.0 Assertion Consumer Service (ACS) endpoint.
     */
    #[Route('/sso/acs/{providerSlug}', name: 'vezolabs_sso_acs', methods: ['POST'])]
    public function acs(string $providerSlug, Request $request): Response
    {
        $correlationId = bin2hex(random_bytes(8));
        $provider = $this->providerRepository->findByName($providerSlug);

        if ($provider === null || !$provider->isEnabled()) {
            $this->auditLogger->logFailure(null, 'Provider disabled or not found', $request, $correlationId);
            return $this->renderErrorPage('SSO Provider is disabled or not found.', $correlationId, 404);
        }

        try {
            // 1. Process and validate SAML response
            $samlData = $this->samlService->processResponse($provider, $request);

            // 2. Provision or resolve internal Pimcore user
            $user = $this->provisioningService->provisionUser(
                $provider,
                $samlData['name_id'],
                $samlData['attributes']
            );

            // 3. Establish authenticated Pimcore Studio session
            $this->sessionManager->establishSession($user, $request);

            // 4. Record audit log
            $this->auditLogger->logSuccess(
                $provider,
                (string) $user->getUsername(),
                $samlData['name_id'],
                $user->getRoles(),
                $samlData['attributes'],
                $request,
                $correlationId
            );

            // 5. Redirect to destination (RelayState or default Pimcore Studio)
            $destination = $samlData['relay_state'] ?: '/pimcore-studio/';
            if (!str_starts_with($destination, '/')) {
                $destination = '/pimcore-studio/';
            }

            return new RedirectResponse($destination);

        } catch (AccessDeniedException $e) {
            $this->auditLogger->logFailure($provider, $e->getMessage(), $request, $correlationId, null, null, true);
            return $this->renderErrorPage($e->getMessage(), $correlationId, 403);
        } catch (Exception $e) {
            $this->auditLogger->logFailure($provider, $e->getMessage(), $request, $correlationId);
            return $this->renderErrorPage('Authentication failed: ' . $e->getMessage(), $correlationId, 500);
        }
    }

    /**
     * Download or view Service Provider SAML 2.0 Metadata XML.
     */
    #[Route('/sso/metadata/{providerSlug}', name: 'vezolabs_sso_metadata', methods: ['GET'])]
    public function metadata(string $providerSlug, Request $request): Response
    {
        $provider = $this->providerRepository->findByName($providerSlug);

        if ($provider === null) {
            return new Response('SSO Provider not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $xml = $this->samlService->generateSpMetadata($provider, $request);

            return new Response($xml, Response::HTTP_OK, [
                'Content-Type' => 'application/samlmetadata+xml',
                'Content-Disposition' => sprintf('inline; filename="sp-metadata-%s.xml"', $provider->getName()),
            ]);
        } catch (Exception $e) {
            return new Response('Error generating metadata: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Start end-to-end diagnostic test login (preview mode).
     */
    #[Route('/sso/test/{providerSlug}', name: 'vezolabs_sso_test', methods: ['GET'])]
    public function testLogin(string $providerSlug, Request $request): Response
    {
        $provider = $this->providerRepository->findByName($providerSlug);
        if ($provider === null) {
            return new Response('Provider not found', Response::HTTP_NOT_FOUND);
        }

        $testAcsUrl = $this->generateUrl('vezolabs_sso_test_acs', ['providerSlug' => $provider->getName()]);
        $loginUrl = $this->samlService->generateLoginUrl($provider, $request, $testAcsUrl);

        return new RedirectResponse($loginUrl);
    }

    /**
     * Diagnostic test ACS endpoint (inspects attributes and roles without modifying database).
     */
    #[Route('/sso/test-acs/{providerSlug}', name: 'vezolabs_sso_test_acs', methods: ['POST'])]
    public function testAcs(string $providerSlug, Request $request): Response
    {
        $provider = $this->providerRepository->findByName($providerSlug);
        if ($provider === null) {
            return new Response('Provider not found', Response::HTTP_NOT_FOUND);
        }

        try {
            $samlData = $this->samlService->processResponse($provider, $request);
            $preview = $this->diagnosticsService->previewResolution($provider, $samlData['attributes']);

            $html = sprintf(
                '<!DOCTYPE html><html><head><title>SSO Test Result</title><style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 2rem; background: #f8fafc; color: #1e293b; }
                .card { background: white; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
                .badge { padding: 4px 12px; border-radius: 9999px; font-weight: 600; display: inline-block; }
                .success { background: #dcfce7; color: #15803d; }
                .error { background: #fee2e2; color: #b91c1c; }
                pre { background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 6px; overflow-x: auto; }
                h1, h2 { margin-top: 0; }
                </style></head><body><div class="card">
                <h1>SAML Diagnostic Test Result</h1>
                <p>Status: <span class="badge %s">%s</span></p>
                <p><strong>Message:</strong> %s</p>
                <h2>Resolved User Fields</h2>
                <pre>%s</pre>
                <h2>Incoming Claims</h2>
                <pre>%s</pre>
                <p><a href="/pimcore-studio/">Return to Pimcore Studio</a></p>
                </div></body></html>',
                $preview['allowed'] ? 'success' : 'error',
                $preview['allowed'] ? 'AUTHORIZED' : 'ACCESS DENIED',
                htmlspecialchars($preview['message']),
                htmlspecialchars(json_encode($preview['resolved_attributes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                htmlspecialchars(json_encode($samlData['attributes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            );

            return new Response($html, 200, ['Content-Type' => 'text/html']);
        } catch (Exception $e) {
            return new Response('Test failed: ' . htmlspecialchars($e->getMessage()), 500);
        }
    }

    private function renderErrorPage(string $message, string $correlationId, int $statusCode): Response
    {
        $html = sprintf(
            '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Single Sign-On Authentication Failed</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
                    .error-card { background: white; border-radius: 12px; padding: 32px; max-width: 520px; width: 100%%; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); border-top: 5px solid #ef4444; }
                    h2 { margin-top: 0; color: #0f172a; font-size: 1.5rem; }
                    .msg { color: #475569; line-height: 1.6; margin: 16px 0; font-size: 0.95rem; }
                    .meta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 0.85rem; color: #64748b; margin: 20px 0; }
                    .btn { display: inline-block; background: #0284c7; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 500; font-size: 0.9rem; }
                    .btn:hover { background: #0369a1; }
                </style>
            </head>
            <body>
                <div class="error-card">
                    <h2>SSO Authentication Failed</h2>
                    <div class="msg">%s</div>
                    <div class="meta">
                        <div><strong>Correlation ID:</strong> <code>%s</code></div>
                        <div>Please provide this reference to your Pimcore system administrator if you require assistance.</div>
                    </div>
                    <a href="/pimcore-studio/login" class="btn">Return to Login</a>
                </div>
            </body>
            </html>',
            htmlspecialchars($message),
            htmlspecialchars($correlationId)
        );

        return new Response($html, $statusCode);
    }
}
