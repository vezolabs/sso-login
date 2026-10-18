<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\RoleMapping;

use Pimcore\Model\User;
use Pimcore\Model\User\Role;
use RuntimeException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;

class RoleMappingService
{
    /** Common claim URIs used by Microsoft Entra ID and SAML 2.0 IdPs for roles/groups */
    private const array ROLE_CLAIM_KEYS = [
        'http://schemas.microsoft.com/ws/2008/06/identity/claims/role',
        'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups',
        'roles',
        'groups',
        'role',
        'group',
    ];

    /**
     * Get list of all existing Pimcore roles in the system.
     *
     * @return array<array{id: int, name: string, description: string}>
     */
    public function getAvailablePimcoreRoles(): array
    {
        $listing = new Role\Listing();
        $roles = [];

        /** @var Role $role */
        foreach ($listing->load() as $role) {
            $roles[] = [
                'id' => (int) $role->getId(),
                'name' => (string) $role->getName(),
                'description' => (string) ($role->getDescription() ?? ''),
            ];
        }

        return $roles;
    }

    /**
     * Extract external roles/groups from SAML response attributes.
     *
     * @param array<string, array<string>> $attributes
     * @return array<string>
     */
    public function extractExternalRoles(array $attributes): array
    {
        $externalRoles = [];

        foreach (self::ROLE_CLAIM_KEYS as $key) {
            if (isset($attributes[$key]) && \is_array($attributes[$key])) {
                foreach ($attributes[$key] as $val) {
                    $cleanVal = trim((string) $val);
                    if ($cleanVal !== '' && !\in_array($cleanVal, $externalRoles, true)) {
                        $externalRoles[] = $cleanVal;
                    }
                }
            }
        }

        return $externalRoles;
    }

    /**
     * Resolve incoming SAML roles into Pimcore role IDs according to provider policies.
     *
     * @param array<string, array<string>> $attributes
     * @return array<int> Array of resolved Pimcore Role IDs
     *
     * @throws AccessDeniedException If authorization fails based on unmapped role policy
     */
    public function resolveRoles(
        IdentityProvider $provider,
        array $attributes,
        ?User $existingUser = null
    ): array {
        // If first-login-only policy and user already exists, preserve existing roles
        if ($existingUser !== null && $provider->getRoleSyncPolicy() === IdentityProvider::ROLE_SYNC_FIRST_LOGIN_ONLY) {
            return $existingUser->getRoles();
        }

        $externalRoles = $this->extractExternalRoles($attributes);
        $roleMapping = $provider->getRoleMapping(); // [ 'entra_role' => 'pimcore_role_name' ]

        $resolvedRoleNames = [];
        foreach ($externalRoles as $extRole) {
            if (isset($roleMapping[$extRole]) && !empty($roleMapping[$extRole])) {
                $targetPimcoreRole = $roleMapping[$extRole];
                if (!\in_array($targetPimcoreRole, $resolvedRoleNames, true)) {
                    $resolvedRoleNames[] = $targetPimcoreRole;
                }
            }
        }

        // Handle case where no mapped roles matched
        if (empty($resolvedRoleNames)) {
            $unmappedPolicy = $provider->getUnmappedRolePolicy();

            if ($unmappedPolicy === IdentityProvider::UNMAPPED_ROLE_DENY) {
                throw new AccessDeniedException(
                    'Authentication succeeded, but no Microsoft Entra / SAML role is mapped to an authorized Pimcore role.'
                );
            }

            if ($unmappedPolicy === IdentityProvider::UNMAPPED_ROLE_RESTRICTED) {
                $fallbackRoleName = $provider->getRestrictedFallbackRole();
                if (!empty($fallbackRoleName)) {
                    $fallbackRole = Role::getByName($fallbackRoleName);
                    if ($fallbackRole instanceof Role) {
                        $resolvedRoleNames[] = $fallbackRoleName;
                    } else {
                        throw new AccessDeniedException(
                            'Configured restricted fallback role does not exist in Pimcore.'
                        );
                    }
                } else {
                    throw new AccessDeniedException(
                        'No mapped roles found and no restricted fallback role is configured.'
                    );
                }
            }

            if ($unmappedPolicy === IdentityProvider::UNMAPPED_ROLE_MANUAL) {
                throw new AccessDeniedException(
                    'Your account is pending administrator approval before access can be granted.'
                );
            }
        }

        // Convert role names to role IDs
        $resolvedRoleIds = [];
        foreach ($resolvedRoleNames as $roleName) {
            $role = Role::getByName($roleName);
            if ($role instanceof Role) {
                $roleId = (int) $role->getId();
                if (!\in_array($roleId, $resolvedRoleIds, true)) {
                    $resolvedRoleIds[] = $roleId;
                }
            }
        }

        // If user already exists and we need to keep local roles separate
        if ($existingUser !== null) {
            $currentRoleIds = $existingUser->getRoles();

            if (!$provider->isRevokeRemovedRoles()) {
                // Merge new SSO roles with existing roles
                $resolvedRoleIds = array_values(array_unique(array_merge($currentRoleIds, $resolvedRoleIds)));
            } else {
                // Strict synchronization: only revoke roles that were mapped to this provider
                $allConfiguredPimcoreRoleIds = $this->getConfiguredTargetRoleIds($provider);

                // Roles on the user that do NOT belong to this provider's mapping are local roles: keep them!
                $localRoleIds = array_diff($currentRoleIds, $allConfiguredPimcoreRoleIds);

                $resolvedRoleIds = array_values(array_unique(array_merge($localRoleIds, $resolvedRoleIds)));
            }
        }

        return $resolvedRoleIds;
    }

    /**
     * Get all Pimcore role IDs configured as targets in this provider's role mapping.
     *
     * @return array<int>
     */
    public function getConfiguredTargetRoleIds(IdentityProvider $provider): array
    {
        $roleIds = [];
        foreach ($provider->getRoleMapping() as $pimcoreRoleName) {
            $role = Role::getByName((string) $pimcoreRoleName);
            if ($role instanceof Role) {
                $roleIds[] = (int) $role->getId();
            }
        }

        if ($provider->getRestrictedFallbackRole()) {
            $fallback = Role::getByName($provider->getRestrictedFallbackRole());
            if ($fallback instanceof Role) {
                $roleIds[] = (int) $fallback->getId();
            }
        }

        return array_values(array_unique($roleIds));
    }
}
