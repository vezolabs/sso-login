<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Provisioning;

use Pimcore\Model\Tool\Password;
use Pimcore\Model\User;
use RuntimeException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Vezolabs\SsoLoginBundle\Entity\ExternalIdentity;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Repository\ExternalIdentityRepository;
use Vezolabs\SsoLoginBundle\Service\RoleMapping\RoleMappingService;

class UserProvisioningService
{
    public function __construct(
        private readonly ExternalIdentityRepository $externalIdentityRepository,
        private readonly RoleMappingService $roleMappingService
    ) {
    }

    /**
     * Resolve or provision a Pimcore User based on SAML assertion attributes.
     *
     * @param array<string, array<string>> $attributes
     * @throws AccessDeniedException
     */
    public function provisionUser(
        IdentityProvider $provider,
        string $nameId,
        array $attributes
    ): User {
        $attrMap = $provider->getAttributeMapping();

        // 1. Resolve stable external subject identifier
        $externalSubjectId = $this->extractClaim($attributes, $attrMap['external_id'] ?? '') ?: $nameId;
        if (empty($externalSubjectId)) {
            throw new AccessDeniedException('Missing stable subject identifier in SAML assertion.');
        }

        // 2. Resolve email and names
        $email = strtolower(trim((string) $this->extractClaim($attributes, $attrMap['email'] ?? '')));
        $firstname = (string) $this->extractClaim($attributes, $attrMap['firstname'] ?? '');
        $lastname = (string) $this->extractClaim($attributes, $attrMap['lastname'] ?? '');
        $displayName = (string) $this->extractClaim($attributes, $attrMap['displayname'] ?? '');

        if (empty($email) && filter_var($nameId, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower(trim($nameId));
        }

        // 3. Domain validation
        $allowedDomains = $provider->getAllowedDomains();
        if (!empty($allowedDomains) && !empty($email)) {
            $parts = explode('@', $email);
            $domain = end($parts);
            if (!\in_array(strtolower($domain), array_map('strtolower', $allowedDomains), true)) {
                throw new AccessDeniedException(sprintf('Domain "%s" is not in the list of allowed domains for this provider.', $domain));
            }
        }

        // 4. Find existing user by external identity link
        $externalIdentity = $this->externalIdentityRepository->findByExternalSubject(
            (int) $provider->getId(),
            $externalSubjectId
        );

        $user = null;
        if ($externalIdentity !== null) {
            $user = User::getById($externalIdentity->getPimcoreUserId());
        }

        // 5. If not linked yet, search existing Pimcore user by email or username
        if ($user === null && !empty($email)) {
            $user = User::getByEmail($email);
            if ($user === null) {
                // Try by username derived from email or nameId
                $candidateUsername = $this->sanitizeUsername($email);
                $user = User::getByName($candidateUsername);
            }

            // Link existing user if found
            if ($user instanceof User) {
                $externalIdentity = new ExternalIdentity(
                    (int) $provider->getId(),
                    (int) $user->getId(),
                    $externalSubjectId,
                    $email
                );
            }
        }

        // 6. Check disabled user policy
        if ($user instanceof User) {
            if (!$user->isActive() && $provider->getHandleDisabledUsers() === IdentityProvider::DISABLED_USER_DENY) {
                throw new AccessDeniedException('This user account has been disabled in Pimcore.');
            }
        }

        // 7. Resolve Roles
        $resolvedRoleIds = $this->roleMappingService->resolveRoles($provider, $attributes, $user);

        // 8. If new user: check provisioning policy
        if ($user === null) {
            if (!$provider->isProvisioningEnabled()) {
                throw new AccessDeniedException('Automatic user provisioning is disabled for this provider, and no matching Pimcore user was found.');
            }

            if ($provider->isRequireMappedRolesForCreation() && empty($resolvedRoleIds)) {
                throw new AccessDeniedException('User creation denied: No authorized roles are mapped for this user.');
            }

            $user = $this->createNewUser($provider, $email, $firstname, $lastname, $displayName, $externalSubjectId);
            $user->setRoles($resolvedRoleIds);
            $user->save();

            $externalIdentity = new ExternalIdentity(
                (int) $provider->getId(),
                (int) $user->getId(),
                $externalSubjectId,
                $email
            );
        } else {
            // Existing user: sync attributes and roles if enabled
            if ($provider->isSyncUserAttributesOnLogin()) {
                if (!empty($firstname)) {
                    $user->setFirstname($firstname);
                }
                if (!empty($lastname)) {
                    $user->setLastname($lastname);
                }
                if (!empty($email)) {
                    $user->setEmail($email);
                }
            }

            $user->setRoles($resolvedRoleIds);
            $user->save();
        }

        // 9. Update external identity last login
        if ($externalIdentity !== null) {
            $externalIdentity->setLastLoginAt(new \DateTimeImmutable());
            if (!empty($email)) {
                $externalIdentity->setExternalEmail($email);
            }
            $this->externalIdentityRepository->save($externalIdentity);
        }

        return $user;
    }

    private function createNewUser(
        IdentityProvider $provider,
        string $email,
        string $firstname,
        string $lastname,
        string $displayName,
        string $externalSubjectId
    ): User {
        $username = $this->generateUniqueUsername($email, $firstname, $lastname, $externalSubjectId);

        $user = new User();
        $user->setParentId(0);
        $user->setName($username);
        $user->setEmail($email);
        $user->setFirstname($firstname ?: $displayName);
        $user->setLastname($lastname);
        $user->setActive(true);
        $user->setAdmin(false); // Security: NEVER default to super admin
        $user->setPassword(Password::hash(bin2hex(random_bytes(24))));

        return $user;
    }

    private function generateUniqueUsername(
        string $email,
        string $firstname,
        string $lastname,
        string $externalSubjectId
    ): string {
        $base = !empty($email) ? explode('@', $email)[0] : (!empty($firstname) ? strtolower($firstname . '.' . $lastname) : 'sso_user');
        $base = $this->sanitizeUsername($base);

        $username = $base;
        $counter = 1;
        while (User::getByName($username) !== null) {
            $username = $base . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    private function sanitizeUsername(string $input): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);

        return !empty($sanitized) ? $sanitized : 'user_' . substr(md5($input), 0, 8);
    }

    /**
     * Extract claim value from SAML attribute array.
     *
     * @param array<string, array<string>> $attributes
     */
    public function extractClaim(array $attributes, string $claimKey): ?string
    {
        if (empty($claimKey)) {
            return null;
        }

        // Exact match
        if (isset($attributes[$claimKey]) && !empty($attributes[$claimKey])) {
            return (string) reset($attributes[$claimKey]);
        }

        // Short-name fallback (e.g. "email" matches ".../emailaddress" or "email")
        foreach ($attributes as $key => $values) {
            if (empty($values)) {
                continue;
            }
            if (str_ends_with(strtolower($key), '/' . strtolower($claimKey)) || strtolower($key) === strtolower($claimKey)) {
                return (string) reset($values);
            }
        }

        return null;
    }
}
