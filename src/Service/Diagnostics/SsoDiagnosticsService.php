<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Diagnostics;

use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Service\RoleMapping\RoleMappingService;
use Vezolabs\SsoLoginBundle\Service\Saml\CertificateManager;
use Vezolabs\SsoLoginBundle\Service\Saml\SamlConfigurationValidator;

class SsoDiagnosticsService
{
    public function __construct(
        private readonly SamlConfigurationValidator $validator,
        private readonly CertificateManager $certificateManager,
        private readonly RoleMappingService $roleMappingService
    ) {
    }

    /**
     * Run full health check across all enabled providers.
     *
     * @param array<IdentityProvider> $providers
     * @return array{
     *     status: 'HEALTHY'|'WARNING'|'ERROR',
     *     provider_count: int,
     *     enabled_count: int,
     *     issues: array<string>,
     *     provider_reports: array<int, mixed>
     * }
     */
    public function runHealthCheck(array $providers): array
    {
        $enabledCount = 0;
        $allIssues = [];
        $reports = [];
        $hasErrors = false;
        $hasWarnings = false;

        foreach ($providers as $provider) {
            if ($provider->isEnabled()) {
                $enabledCount++;
            }

            $validation = $this->validator->validate($provider);
            if (!empty($validation['errors'])) {
                $hasErrors = true;
                foreach ($validation['errors'] as $err) {
                    $allIssues[] = sprintf('[%s] %s', $provider->getDisplayName(), $err);
                }
            }
            if (!empty($validation['warnings'])) {
                $hasWarnings = true;
                foreach ($validation['warnings'] as $warn) {
                    $allIssues[] = sprintf('[%s] %s', $provider->getDisplayName(), $warn);
                }
            }

            $reports[(int) $provider->getId()] = $validation;
        }

        $status = 'HEALTHY';
        if ($hasErrors) {
            $status = 'ERROR';
        } elseif ($hasWarnings) {
            $status = 'WARNING';
        }

        return [
            'status' => $status,
            'provider_count' => \count($providers),
            'enabled_count' => $enabledCount,
            'issues' => $allIssues,
            'provider_reports' => $reports,
        ];
    }

    /**
     * Preview attribute and role resolution on sample or real SAML attributes (dry-run).
     *
     * @param array<string, array<string>> $sampleAttributes
     * @return array{
     *     resolved_attributes: array<string, string|null>,
     *     external_roles: array<string>,
     *     resolved_roles: array<int>,
     *     allowed: bool,
     *     message: string
     * }
     */
    public function previewResolution(IdentityProvider $provider, array $sampleAttributes): array
    {
        $attrMap = $provider->getAttributeMapping();
        $resolvedAttrs = [];

        foreach ($attrMap as $targetField => $claimKey) {
            $val = null;
            if (isset($sampleAttributes[$claimKey]) && !empty($sampleAttributes[$claimKey])) {
                $val = (string) reset($sampleAttributes[$claimKey]);
            } else {
                // Short name fallback
                foreach ($sampleAttributes as $k => $v) {
                    if (str_ends_with(strtolower($k), '/' . strtolower($claimKey)) || strtolower($k) === strtolower($claimKey)) {
                        $val = !empty($v) ? (string) reset($v) : null;
                        break;
                    }
                }
            }
            $resolvedAttrs[$targetField] = $val;
        }

        $externalRoles = $this->roleMappingService->extractExternalRoles($sampleAttributes);

        $allowed = true;
        $message = 'Authorization passed.';
        $resolvedRoles = [];

        try {
            $resolvedRoles = $this->roleMappingService->resolveRoles($provider, $sampleAttributes, null);
        } catch (\Exception $e) {
            $allowed = false;
            $message = $e->getMessage();
        }

        return [
            'resolved_attributes' => $resolvedAttrs,
            'external_roles' => $externalRoles,
            'resolved_roles' => $resolvedRoles,
            'allowed' => $allowed,
            'message' => $message,
        ];
    }
}
