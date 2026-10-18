<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Saml;

use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Pimcore\Model\User\Role;

class SamlConfigurationValidator
{
    public function __construct(private readonly CertificateManager $certificateManager)
    {
    }

    /**
     * Run full pre-flight validation on an IdentityProvider configuration.
     *
     * @return array{
     *     valid: bool,
     *     checks: array<array{name: string, passed: bool, severity: 'error'|'warning'|'info', message: string}>,
     *     errors: array<string>,
     *     warnings: array<string>
     * }
     */
    public function validate(IdentityProvider $provider): array
    {
        $checks = [];
        $errors = [];
        $warnings = [];

        // 1. IdP Entity ID
        $hasEntityId = !empty(trim($provider->getIdpEntityId()));
        $checks[] = [
            'name' => 'IdP Entity ID',
            'passed' => $hasEntityId,
            'severity' => 'error',
            'message' => $hasEntityId ? 'Configured' : 'Missing Identity Provider Entity ID / Issuer URI.',
        ];
        if (!$hasEntityId) {
            $errors[] = 'Missing Identity Provider Entity ID / Issuer URI.';
        }

        // 2. IdP SSO URL
        $ssoUrl = trim($provider->getIdpSsoUrl());
        $validSsoUrl = filter_var($ssoUrl, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($ssoUrl), 'https://');
        $checks[] = [
            'name' => 'SSO Login URL',
            'passed' => $validSsoUrl,
            'severity' => 'error',
            'message' => $validSsoUrl ? 'Valid HTTPS URL' : 'Must be a valid HTTPS Single Sign-On endpoint.',
        ];
        if (!$validSsoUrl) {
            $errors[] = 'Must be a valid HTTPS Single Sign-On endpoint.';
        }

        // 3. Signing Certificate
        $certRaw = trim($provider->getIdpX509Cert());
        $certInfo = $this->certificateManager->inspect($certRaw);
        $certValid = $certInfo['status'] === CertificateManager::STATUS_VALID || $certInfo['status'] === CertificateManager::STATUS_EXPIRING_SOON;

        $checks[] = [
            'name' => 'Primary X.509 Signing Certificate',
            'passed' => $certValid,
            'severity' => 'error',
            'message' => match ($certInfo['status']) {
                CertificateManager::STATUS_VALID => sprintf('Valid (expires in %d days)', $certInfo['days_left']),
                CertificateManager::STATUS_EXPIRING_SOON => sprintf('Expiring soon: %d days remaining', $certInfo['days_left']),
                CertificateManager::STATUS_EXPIRED => 'Certificate has EXPIRED.',
                default => 'Invalid or missing X.509 certificate.',
            },
        ];
        if (!$certValid) {
            $errors[] = 'Primary X.509 signing certificate is invalid or expired.';
        } elseif ($certInfo['status'] === CertificateManager::STATUS_EXPIRING_SOON) {
            $warnings[] = sprintf('Signing certificate expires soon (%d days remaining).', $certInfo['days_left']);
        }

        // 4. Rollover Certificate check (if present)
        if (!empty(trim((string) $provider->getIdpX509CertRollover()))) {
            $rolloverInfo = $this->certificateManager->inspect($provider->getIdpX509CertRollover());
            $checks[] = [
                'name' => 'Rollover Certificate',
                'passed' => $rolloverInfo['status'] !== CertificateManager::STATUS_EXPIRED && $rolloverInfo['status'] !== CertificateManager::STATUS_INVALID,
                'severity' => 'warning',
                'message' => sprintf('Configured (expires in %d days)', $rolloverInfo['days_left']),
            ];
        }

        // 5. Attribute Mapping
        $attrMapping = $provider->getAttributeMapping();
        $hasEmailClaim = !empty($attrMapping['email']);
        $hasExtIdClaim = !empty($attrMapping['external_id']);
        $checks[] = [
            'name' => 'Attribute Mapping (Email & Subject)',
            'passed' => $hasEmailClaim && $hasExtIdClaim,
            'severity' => 'error',
            'message' => ($hasEmailClaim && $hasExtIdClaim) ? 'Configured' : 'Missing required claim mapping for email or external subject ID.',
        ];
        if (!$hasEmailClaim || !$hasExtIdClaim) {
            $errors[] = 'Attribute mapping must map at least "email" and "external_id".';
        }

        // 6. Role Mapping & Policies
        $roleMapping = $provider->getRoleMapping();
        if (empty($roleMapping)) {
            if ($provider->getUnmappedRolePolicy() === IdentityProvider::UNMAPPED_ROLE_DENY) {
                $warnings[] = 'No role mappings configured and policy is "Deny unmapped": all users will be denied access until roles are mapped.';
                $checks[] = [
                    'name' => 'Role Mappings',
                    'passed' => false,
                    'severity' => 'warning',
                    'message' => 'No role mappings defined. Unmapped policy is set to Deny Access.',
                ];
            } else {
                $checks[] = [
                    'name' => 'Role Mappings',
                    'passed' => true,
                    'severity' => 'info',
                    'message' => 'Fallback role policy configured: ' . $provider->getUnmappedRolePolicy(),
                ];
            }
        } else {
            $checks[] = [
                'name' => 'Role Mappings',
                'passed' => true,
                'severity' => 'info',
                'message' => sprintf('%d role mapping(s) defined.', \count($roleMapping)),
            ];
        }

        // 7. Security Policy Checks
        $assertionsSigned = $provider->isWantAssertionsSigned();
        $checks[] = [
            'name' => 'Assertion Signature Enforcement',
            'passed' => $assertionsSigned,
            'severity' => 'warning',
            'message' => $assertionsSigned ? 'Enforced' : 'WantAssertionsSigned is disabled: SAML assertions will not require signatures.',
        ];
        if (!$assertionsSigned) {
            $warnings[] = 'SAML assertion signatures are not enforced. It is recommended to enable wantAssertionsSigned for Entra ID.';
        }

        return [
            'valid' => empty($errors),
            'checks' => $checks,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
