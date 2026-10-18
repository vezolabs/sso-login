<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Controller\Api;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Repository\AuditLogRepository;
use Vezolabs\SsoLoginBundle\Repository\ExternalIdentityRepository;
use Vezolabs\SsoLoginBundle\Repository\IdentityProviderRepository;
use Vezolabs\SsoLoginBundle\Service\Diagnostics\SsoDiagnosticsService;
use Vezolabs\SsoLoginBundle\Service\RoleMapping\RoleMappingService;
use Vezolabs\SsoLoginBundle\Service\Saml\CertificateManager;
use Vezolabs\SsoLoginBundle\Service\Saml\MetadataFetcher;
use Vezolabs\SsoLoginBundle\Service\Saml\MetadataParser;
use Vezolabs\SsoLoginBundle\Service\Saml\SamlConfigurationValidator;
use Vezolabs\SsoLoginBundle\VezolabsSsoLoginBundle;

#[Route('/pimcore-studio/api/sso')]
class StudioSsoApiController extends AbstractController
{
    public function __construct(
        private readonly IdentityProviderRepository $providerRepository,
        private readonly ExternalIdentityRepository $externalIdentityRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly MetadataParser $metadataParser,
        private readonly MetadataFetcher $metadataFetcher,
        private readonly CertificateManager $certificateManager,
        private readonly SamlConfigurationValidator $validator,
        private readonly RoleMappingService $roleMappingService,
        private readonly SsoDiagnosticsService $diagnosticsService
    ) {
    }

    /**
     * Dashboard Overview metrics.
     */
    #[Route('/overview', name: 'vezolabs_sso_api_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $providers = $this->providerRepository->findAll();
        $defaultProvider = $this->providerRepository->findDefault();
        $health = $this->diagnosticsService->runHealthCheck($providers);

        $lastSuccess = $this->auditLogRepository->findLastSuccessfulLogin();
        $lastFailure = $this->auditLogRepository->findLastFailedLogin();
        $ssoUserCount = $this->externalIdentityRepository->countSsoUsers();

        return new JsonResponse([
            'sso_enabled' => $health['enabled_count'] > 0,
            'health_status' => $health['status'],
            'providers_total' => $health['provider_count'],
            'providers_enabled' => $health['enabled_count'],
            'active_provider' => $defaultProvider ? [
                'id' => $defaultProvider->getId(),
                'name' => $defaultProvider->getName(),
                'display_name' => $defaultProvider->getDisplayName(),
            ] : null,
            'sso_users_count' => $ssoUserCount,
            'last_successful_login' => $lastSuccess?->getTimestamp()->format(\DateTimeInterface::ATOM),
            'last_failed_login' => $lastFailure?->getTimestamp()->format(\DateTimeInterface::ATOM),
            'outstanding_warnings' => $health['issues'],
        ]);
    }

    /**
     * List all identity providers.
     */
    #[Route('/providers', name: 'vezolabs_sso_api_providers_list', methods: ['GET'])]
    public function listProviders(): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $providers = $this->providerRepository->findAll();
        $data = array_map(fn(IdentityProvider $p) => $p->toArray(), $providers);

        return new JsonResponse(['items' => $data]);
    }

    /**
     * Get single provider details.
     */
    #[Route('/providers/{id}', name: 'vezolabs_sso_api_providers_get', methods: ['GET'])]
    public function getProvider(int $id): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $provider = $this->providerRepository->findById($id);
        if ($provider === null) {
            return new JsonResponse(['error' => 'Provider not found'], Response::HTTP_NOT_FOUND);
        }

        $validation = $this->validator->validate($provider);

        return new JsonResponse([
            'provider' => $provider->toArray(),
            'validation' => $validation,
        ]);
    }

    /**
     * Create identity provider.
     */
    #[Route('/providers', name: 'vezolabs_sso_api_providers_create', methods: ['POST'])]
    public function createProvider(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $payload = json_decode($request->getContent(), true) ?: [];
        $provider = IdentityProvider::fromArray($payload);

        $currentUser = $this->getUser()?->getUserIdentifier() ?: 'admin';
        $this->providerRepository->save($provider, $currentUser, 'CREATE');

        return new JsonResponse([
            'message' => 'Provider created successfully.',
            'provider' => $provider->toArray(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update identity provider.
     */
    #[Route('/providers/{id}', name: 'vezolabs_sso_api_providers_update', methods: ['PUT'])]
    public function updateProvider(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $provider = $this->providerRepository->findById($id);
        if ($provider === null) {
            return new JsonResponse(['error' => 'Provider not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        $payload['id'] = $id;

        $updated = IdentityProvider::fromArray($payload);
        $currentUser = $this->getUser()?->getUserIdentifier() ?: 'admin';
        $this->providerRepository->save($updated, $currentUser, 'UPDATE');

        return new JsonResponse([
            'message' => 'Provider updated successfully.',
            'provider' => $updated->toArray(),
        ]);
    }

    /**
     * Delete identity provider.
     */
    #[Route('/providers/{id}', name: 'vezolabs_sso_api_providers_delete', methods: ['DELETE'])]
    public function deleteProvider(int $id): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $provider = $this->providerRepository->findById($id);
        if ($provider === null) {
            return new JsonResponse(['error' => 'Provider not found'], Response::HTTP_NOT_FOUND);
        }

        $this->providerRepository->delete($id);

        return new JsonResponse(['message' => 'Provider deleted successfully.']);
    }

    /**
     * Toggle enabled state.
     */
    #[Route('/providers/{id}/toggle', name: 'vezolabs_sso_api_providers_toggle', methods: ['POST'])]
    public function toggleProvider(int $id): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $provider = $this->providerRepository->findById($id);
        if ($provider === null) {
            return new JsonResponse(['error' => 'Provider not found'], Response::HTTP_NOT_FOUND);
        }

        // Before enabling, check validation
        if (!$provider->isEnabled()) {
            $validation = $this->validator->validate($provider);
            if (!$validation['valid']) {
                return new JsonResponse([
                    'error' => 'Cannot enable provider with outstanding errors: ' . implode('; ', $validation['errors']),
                    'validation' => $validation,
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $provider->setEnabled(!$provider->isEnabled());
        $currentUser = $this->getUser()?->getUserIdentifier() ?: 'admin';
        $this->providerRepository->save($provider, $currentUser, 'TOGGLE');

        return new JsonResponse([
            'message' => sprintf('Provider %s successfully.', $provider->isEnabled() ? 'enabled' : 'disabled'),
            'enabled' => $provider->isEnabled(),
        ]);
    }

    /**
     * Rotate certificate (promotes rollover certificate to primary).
     */
    #[Route('/providers/{id}/rotate-cert', name: 'vezolabs_sso_api_providers_rotate_cert', methods: ['POST'])]
    public function rotateCertificate(int $id): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $provider = $this->providerRepository->findById($id);
        if ($provider === null) {
            return new JsonResponse(['error' => 'Provider not found'], Response::HTTP_NOT_FOUND);
        }

        $rolloverCert = $provider->getIdpX509CertRollover();
        if (empty($rolloverCert)) {
            return new JsonResponse(['error' => 'No rollover certificate is configured to promote.'], Response::HTTP_BAD_REQUEST);
        }

        // Promote rollover to primary and clear rollover
        $provider->setIdpX509Cert($rolloverCert);
        $provider->setIdpX509CertRollover(null);

        $currentUser = $this->getUser()?->getUserIdentifier() ?: 'admin';
        $this->providerRepository->save($provider, $currentUser, 'ROTATE_CERT');

        return new JsonResponse([
            'message' => 'Rollover certificate promoted to active primary signing certificate.',
            'provider' => $provider->toArray(),
        ]);
    }

    /**
     * Parse XML uploaded or pasted.
     */
    #[Route('/metadata/parse-xml', name: 'vezolabs_sso_api_metadata_parse_xml', methods: ['POST'])]
    public function parseMetadataXml(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $payload = json_decode($request->getContent(), true) ?: [];
        $xml = (string) ($payload['xml'] ?? '');

        if (trim($xml) === '') {
            return new JsonResponse(['error' => 'No XML provided.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $parsed = $this->metadataParser->parse($xml);
            $certInfo = $this->certificateManager->inspect($parsed['primary_cert']);

            $rolloverInfo = null;
            if ($parsed['rollover_cert']) {
                $rolloverInfo = $this->certificateManager->inspect($parsed['rollover_cert']);
            }

            return new JsonResponse([
                'parsed' => $parsed,
                'certificate' => $certInfo,
                'rollover_certificate' => $rolloverInfo,
            ]);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Fetch metadata from remote URL with SSRF protection.
     */
    #[Route('/metadata/fetch-url', name: 'vezolabs_sso_api_metadata_fetch_url', methods: ['POST'])]
    public function fetchMetadataUrl(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $payload = json_decode($request->getContent(), true) ?: [];
        $url = trim((string) ($payload['url'] ?? ''));

        if ($url === '') {
            return new JsonResponse(['error' => 'No URL provided.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $xml = $this->metadataFetcher->fetch($url);
            $parsed = $this->metadataParser->parse($xml);
            $certInfo = $this->certificateManager->inspect($parsed['primary_cert']);

            $rolloverInfo = null;
            if ($parsed['rollover_cert']) {
                $rolloverInfo = $this->certificateManager->inspect($parsed['rollover_cert']);
            }

            return new JsonResponse([
                'raw_xml' => $xml,
                'parsed' => $parsed,
                'certificate' => $certInfo,
                'rollover_certificate' => $rolloverInfo,
            ]);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get available Pimcore roles for role-mapping dropdowns.
     */
    #[Route('/roles', name: 'vezolabs_sso_api_roles_list', methods: ['GET'])]
    public function getPimcoreRoles(): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $roles = $this->roleMappingService->getAvailablePimcoreRoles();

        return new JsonResponse(['roles' => $roles]);
    }

    /**
     * Audit logs with filtering and pagination.
     */
    #[Route('/logs', name: 'vezolabs_sso_api_logs', methods: ['GET'])]
    public function getLogs(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $status = $request->query->get('status');
        $providerName = $request->query->get('provider_name');
        $username = $request->query->get('username');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 25)));

        $data = $this->auditLogRepository->findFiltered($status, $providerName, $username, null, null, $page, $limit);

        return new JsonResponse([
            'items' => array_map(fn($item) => $item->toArray(), $data['items']),
            'total' => $data['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Certificate inventory and status.
     */
    #[Route('/certificates', name: 'vezolabs_sso_api_certificates', methods: ['GET'])]
    public function getCertificates(): JsonResponse
    {
        $this->denyAccessUnlessGrantedPermission();

        $providers = $this->providerRepository->findAll();
        $certs = [];

        foreach ($providers as $provider) {
            if ($provider->getIdpX509Cert()) {
                $info = $this->certificateManager->inspect($provider->getIdpX509Cert());
                $certs[] = array_merge($info, [
                    'provider_id' => $provider->getId(),
                    'provider_name' => $provider->getDisplayName(),
                    'type' => 'PRIMARY',
                ]);
            }
            if ($provider->getIdpX509CertRollover()) {
                $info = $this->certificateManager->inspect($provider->getIdpX509CertRollover());
                $certs[] = array_merge($info, [
                    'provider_id' => $provider->getId(),
                    'provider_name' => $provider->getDisplayName(),
                    'type' => 'ROLLOVER',
                ]);
            }
        }

        return new JsonResponse(['certificates' => $certs]);
    }

    private function denyAccessUnlessGrantedPermission(): void
    {
        $user = $this->getUser();
        if ($user === null) {
            throw new AccessDeniedException('You must be logged into Pimcore Studio to access SSO configuration.');
        }

        $pimcoreUser = null;
        if ($user instanceof \Pimcore\Security\User\User) {
            $pimcoreUser = $user->getUser();
        } elseif ($user instanceof \Pimcore\Model\User) {
            $pimcoreUser = $user;
        } elseif (method_exists($user, 'getUser')) {
            $pimcoreUser = $user->getUser();
        }

        if ($pimcoreUser instanceof \Pimcore\Model\User) {
            if ($pimcoreUser->isAdmin() || $pimcoreUser->isAllowed(VezolabsSsoLoginBundle::PERMISSION_MANAGE)) {
                return;
            }
        }

        // Check Symfony permission
        if ($this->isGranted(VezolabsSsoLoginBundle::PERMISSION_MANAGE)) {
            return;
        }

        throw new AccessDeniedException('Access denied: requires VEZOLABS_SSO_MANAGE permission.');
    }
}
