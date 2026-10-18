<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Entity;

class IdentityProvider
{
    public const string TYPE_ENTRA_ID = 'microsoft_entra_id';
    public const string TYPE_GENERIC_SAML = 'generic_saml';

    public const string ROLE_SYNC_EVERY_LOGIN = 'every_login';
    public const string ROLE_SYNC_FIRST_LOGIN_ONLY = 'first_login_only';

    public const string UNMAPPED_ROLE_DENY = 'deny';
    public const string UNMAPPED_ROLE_RESTRICTED = 'assign_restricted';
    public const string UNMAPPED_ROLE_MANUAL = 'manual_approval';

    public const string DISABLED_USER_DENY = 'deny_access';
    public const string DISABLED_USER_IGNORE = 'do_nothing';

    private ?int $id = null;
    private string $name = 'entra-id';
    private string $type = self::TYPE_ENTRA_ID;
    private string $displayName = 'Microsoft Entra ID';
    private ?string $description = null;
    private bool $enabled = false;
    private bool $isDefault = false;
    private ?string $tenantId = null;

    // IdP Details
    private string $idpEntityId = '';
    private string $idpSsoUrl = '';
    private ?string $idpSloUrl = null;
    private string $idpX509Cert = '';
    private ?string $idpX509CertRollover = null;
    private ?string $idpMetadataUrl = null;
    private ?string $idpMetadataXml = null;

    // SP Details (overrides)
    private ?string $spEntityId = null;
    private ?string $spAcsUrl = null;
    private ?string $spSloUrl = null;
    private ?string $spX509Cert = null;
    private ?string $spPrivateKey = null;

    // SAML Protocol settings
    private string $nameIdFormat = 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified';
    private bool $signAuthnRequests = false;
    private bool $wantAssertionsSigned = true;
    private bool $wantResponseSigned = false;

    // Login UI settings
    private string $loginButtonLabel = 'Sign in with Microsoft';
    private bool $loginButtonVisible = true;
    private string $loginButtonIcon = 'microsoft';
    private bool $hideLocalLogin = false;
    private bool $autoRedirectSso = false;

    // Attribute & Role Mappings
    /** @var array<string, string> */
    private array $attributeMapping = [
        'email' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        'firstname' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
        'lastname' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
        'displayname' => 'http://schemas.microsoft.com/identity/claims/displayname',
        'external_id' => 'http://schemas.microsoft.com/identity/claims/objectidentifier',
    ];

    /** @var array<string, string> External role/group ID -> Pimcore role ID */
    private array $roleMapping = [];

    private string $roleSyncPolicy = self::ROLE_SYNC_EVERY_LOGIN;
    private string $unmappedRolePolicy = self::UNMAPPED_ROLE_DENY;
    private ?string $restrictedFallbackRole = null;
    private bool $revokeRemovedRoles = true;

    // Provisioning
    private bool $provisioningEnabled = true;
    private bool $requireMappedRolesForCreation = true;
    private ?string $defaultPimcoreRole = null;
    private bool $syncUserAttributesOnLogin = true;
    private string $handleDisabledUsers = self::DISABLED_USER_DENY;

    /** @var array<string> */
    private array $allowedDomains = [];

    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function fromArray(array $data): self
    {
        $provider = new self();
        if (isset($data['id'])) {
            $provider->setId((int) $data['id']);
        }
        $provider->setName($data['name'] ?? 'entra-id');
        $provider->setType($data['type'] ?? self::TYPE_ENTRA_ID);
        $provider->setDisplayName($data['display_name'] ?? 'Microsoft Entra ID');
        $provider->setDescription($data['description'] ?? null);
        $provider->setEnabled((bool) ($data['enabled'] ?? false));
        $provider->setIsDefault((bool) ($data['is_default'] ?? false));
        $provider->setTenantId($data['tenant_id'] ?? null);

        $provider->setIdpEntityId($data['idp_entity_id'] ?? '');
        $provider->setIdpSsoUrl($data['idp_sso_url'] ?? '');
        $provider->setIdpSloUrl($data['idp_slo_url'] ?? null);
        $provider->setIdpX509Cert($data['idp_x509_cert'] ?? '');
        $provider->setIdpX509CertRollover($data['idp_x509_cert_rollover'] ?? null);
        $provider->setIdpMetadataUrl($data['idp_metadata_url'] ?? null);
        $provider->setIdpMetadataXml($data['idp_metadata_xml'] ?? null);

        $provider->setSpEntityId($data['sp_entity_id'] ?? null);
        $provider->setSpAcsUrl($data['sp_acs_url'] ?? null);
        $provider->setSpSloUrl($data['sp_slo_url'] ?? null);
        $provider->setSpX509Cert($data['sp_x509_cert'] ?? null);
        $provider->setSpPrivateKey($data['sp_private_key'] ?? null);

        $provider->setNameIdFormat($data['name_id_format'] ?? 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified');
        $provider->setSignAuthnRequests((bool) ($data['sign_authn_requests'] ?? false));
        $provider->setWantAssertionsSigned((bool) ($data['want_assertions_signed'] ?? true));
        $provider->setWantResponseSigned((bool) ($data['want_response_signed'] ?? false));

        $provider->setLoginButtonLabel($data['login_button_label'] ?? 'Sign in with Microsoft');
        $provider->setLoginButtonVisible((bool) ($data['login_button_visible'] ?? true));
        $provider->setLoginButtonIcon($data['login_button_icon'] ?? 'microsoft');
        $provider->setHideLocalLogin((bool) ($data['hide_local_login'] ?? false));
        $provider->setAutoRedirectSso((bool) ($data['auto_redirect_sso'] ?? false));

        $provider->setAttributeMapping(\is_array($data['attribute_mapping'] ?? null)
            ? $data['attribute_mapping']
            : (json_decode($data['attribute_mapping'] ?? '{}', true) ?: []));

        $provider->setRoleMapping(\is_array($data['role_mapping'] ?? null)
            ? $data['role_mapping']
            : (json_decode($data['role_mapping'] ?? '{}', true) ?: []));

        $provider->setRoleSyncPolicy($data['role_sync_policy'] ?? self::ROLE_SYNC_EVERY_LOGIN);
        $provider->setUnmappedRolePolicy($data['unmapped_role_policy'] ?? self::UNMAPPED_ROLE_DENY);
        $provider->setRestrictedFallbackRole($data['restricted_fallback_role'] ?? null);
        $provider->setRevokeRemovedRoles((bool) ($data['revoke_removed_roles'] ?? true));

        $provider->setProvisioningEnabled((bool) ($data['provisioning_enabled'] ?? true));
        $provider->setRequireMappedRolesForCreation((bool) ($data['require_mapped_roles_for_creation'] ?? true));
        $provider->setDefaultPimcoreRole($data['default_pimcore_role'] ?? null);
        $provider->setSyncUserAttributesOnLogin((bool) ($data['sync_user_attributes_on_login'] ?? true));
        $provider->setHandleDisabledUsers($data['handle_disabled_users'] ?? self::DISABLED_USER_DENY);

        $provider->setAllowedDomains(\is_array($data['allowed_domains'] ?? null)
            ? $data['allowed_domains']
            : (json_decode($data['allowed_domains'] ?? '[]', true) ?: []));

        if (isset($data['created_at']) && $data['created_at']) {
            $provider->createdAt = new \DateTimeImmutable((string) $data['created_at']);
        }
        if (isset($data['updated_at']) && $data['updated_at']) {
            $provider->updatedAt = new \DateTimeImmutable((string) $data['updated_at']);
        }

        return $provider;
    }

    public function toArray(bool $includeSecrets = false): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'is_default' => $this->isDefault,
            'tenant_id' => $this->tenantId,
            'idp_entity_id' => $this->idpEntityId,
            'idp_sso_url' => $this->idpSsoUrl,
            'idp_slo_url' => $this->idpSloUrl,
            'idp_x509_cert' => $this->idpX509Cert,
            'idp_x509_cert_rollover' => $this->idpX509CertRollover,
            'idp_metadata_url' => $this->idpMetadataUrl,
            'idp_metadata_xml' => $this->idpMetadataXml,
            'sp_entity_id' => $this->spEntityId,
            'sp_acs_url' => $this->spAcsUrl,
            'sp_slo_url' => $this->spSloUrl,
            'sp_x509_cert' => $this->spX509Cert,
            'sp_private_key' => $includeSecrets ? $this->spPrivateKey : ($this->spPrivateKey ? '***CONFIGURED***' : null),
            'name_id_format' => $this->nameIdFormat,
            'sign_authn_requests' => $this->signAuthnRequests,
            'want_assertions_signed' => $this->wantAssertionsSigned,
            'want_response_signed' => $this->wantResponseSigned,
            'login_button_label' => $this->loginButtonLabel,
            'login_button_visible' => $this->loginButtonVisible,
            'login_button_icon' => $this->loginButtonIcon,
            'hide_local_login' => $this->hideLocalLogin,
            'auto_redirect_sso' => $this->autoRedirectSso,
            'attribute_mapping' => $this->attributeMapping,
            'role_mapping' => $this->roleMapping,
            'role_sync_policy' => $this->roleSyncPolicy,
            'unmapped_role_policy' => $this->unmappedRolePolicy,
            'restricted_fallback_role' => $this->restrictedFallbackRole,
            'revoke_removed_roles' => $this->revokeRemovedRoles,
            'provisioning_enabled' => $this->provisioningEnabled,
            'require_mapped_roles_for_creation' => $this->requireMappedRolesForCreation,
            'default_pimcore_role' => $this->defaultPimcoreRole,
            'sync_user_attributes_on_login' => $this->syncUserAttributesOnLogin,
            'handle_disabled_users' => $this->handleDisabledUsers,
            'allowed_domains' => $this->allowedDomains,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $displayName): self { $this->displayName = $displayName; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $isDefault): self { $this->isDefault = $isDefault; return $this; }
    public function getTenantId(): ?string { return $this->tenantId; }
    public function setTenantId(?string $tenantId): self { $this->tenantId = $tenantId; return $this; }

    public function getIdpEntityId(): string { return $this->idpEntityId; }
    public function setIdpEntityId(string $idpEntityId): self { $this->idpEntityId = $idpEntityId; return $this; }
    public function getIdpSsoUrl(): string { return $this->idpSsoUrl; }
    public function setIdpSsoUrl(string $idpSsoUrl): self { $this->idpSsoUrl = $idpSsoUrl; return $this; }
    public function getIdpSloUrl(): ?string { return $this->idpSloUrl; }
    public function setIdpSloUrl(?string $idpSloUrl): self { $this->idpSloUrl = $idpSloUrl; return $this; }
    public function getIdpX509Cert(): string { return $this->idpX509Cert; }
    public function setIdpX509Cert(string $idpX509Cert): self { $this->idpX509Cert = $idpX509Cert; return $this; }
    public function getIdpX509CertRollover(): ?string { return $this->idpX509CertRollover; }
    public function setIdpX509CertRollover(?string $idpX509CertRollover): self { $this->idpX509CertRollover = $idpX509CertRollover; return $this; }
    public function getIdpMetadataUrl(): ?string { return $this->idpMetadataUrl; }
    public function setIdpMetadataUrl(?string $idpMetadataUrl): self { $this->idpMetadataUrl = $idpMetadataUrl; return $this; }
    public function getIdpMetadataXml(): ?string { return $this->idpMetadataXml; }
    public function setIdpMetadataXml(?string $idpMetadataXml): self { $this->idpMetadataXml = $idpMetadataXml; return $this; }

    public function getSpEntityId(): ?string { return $this->spEntityId; }
    public function setSpEntityId(?string $spEntityId): self { $this->spEntityId = $spEntityId; return $this; }
    public function getSpAcsUrl(): ?string { return $this->spAcsUrl; }
    public function setSpAcsUrl(?string $spAcsUrl): self { $this->spAcsUrl = $spAcsUrl; return $this; }
    public function getSpSloUrl(): ?string { return $this->spSloUrl; }
    public function setSpSloUrl(?string $spSloUrl): self { $this->spSloUrl = $spSloUrl; return $this; }
    public function getSpX509Cert(): ?string { return $this->spX509Cert; }
    public function setSpX509Cert(?string $spX509Cert): self { $this->spX509Cert = $spX509Cert; return $this; }
    public function getSpPrivateKey(): ?string { return $this->spPrivateKey; }
    public function setSpPrivateKey(?string $spPrivateKey): self { $this->spPrivateKey = $spPrivateKey; return $this; }

    public function getNameIdFormat(): string { return $this->nameIdFormat; }
    public function setNameIdFormat(string $nameIdFormat): self { $this->nameIdFormat = $nameIdFormat; return $this; }
    public function isSignAuthnRequests(): bool { return $this->signAuthnRequests; }
    public function setSignAuthnRequests(bool $sign): self { $this->signAuthnRequests = $sign; return $this; }
    public function isWantAssertionsSigned(): bool { return $this->wantAssertionsSigned; }
    public function setWantAssertionsSigned(bool $want): self { $this->wantAssertionsSigned = $want; return $this; }
    public function isWantResponseSigned(): bool { return $this->wantResponseSigned; }
    public function setWantResponseSigned(bool $want): self { $this->wantResponseSigned = $want; return $this; }

    public function getLoginButtonLabel(): string { return $this->loginButtonLabel; }
    public function setLoginButtonLabel(string $label): self { $this->loginButtonLabel = $label; return $this; }
    public function isLoginButtonVisible(): bool { return $this->loginButtonVisible; }
    public function setLoginButtonVisible(bool $visible): self { $this->loginButtonVisible = $visible; return $this; }
    public function getLoginButtonIcon(): string { return $this->loginButtonIcon; }
    public function setLoginButtonIcon(string $icon): self { $this->loginButtonIcon = $icon; return $this; }
    public function isHideLocalLogin(): bool { return $this->hideLocalLogin; }
    public function setHideLocalLogin(bool $hide): self { $this->hideLocalLogin = $hide; return $this; }
    public function isAutoRedirectSso(): bool { return $this->autoRedirectSso; }
    public function setAutoRedirectSso(bool $auto): self { $this->autoRedirectSso = $auto; return $this; }

    public function getAttributeMapping(): array { return $this->attributeMapping; }
    public function setAttributeMapping(array $mapping): self { $this->attributeMapping = $mapping; return $this; }
    public function getRoleMapping(): array { return $this->roleMapping; }
    public function setRoleMapping(array $mapping): self { $this->roleMapping = $mapping; return $this; }
    public function getRoleSyncPolicy(): string { return $this->roleSyncPolicy; }
    public function setRoleSyncPolicy(string $policy): self { $this->roleSyncPolicy = $policy; return $this; }
    public function getUnmappedRolePolicy(): string { return $this->unmappedRolePolicy; }
    public function setUnmappedRolePolicy(string $policy): self { $this->unmappedRolePolicy = $policy; return $this; }
    public function getRestrictedFallbackRole(): ?string { return $this->restrictedFallbackRole; }
    public function setRestrictedFallbackRole(?string $role): self { $this->restrictedFallbackRole = $role; return $this; }
    public function isRevokeRemovedRoles(): bool { return $this->revokeRemovedRoles; }
    public function setRevokeRemovedRoles(bool $revoke): self { $this->revokeRemovedRoles = $revoke; return $this; }

    public function isProvisioningEnabled(): bool { return $this->provisioningEnabled; }
    public function setProvisioningEnabled(bool $enabled): self { $this->provisioningEnabled = $enabled; return $this; }
    public function isRequireMappedRolesForCreation(): bool { return $this->requireMappedRolesForCreation; }
    public function setRequireMappedRolesForCreation(bool $require): self { $this->requireMappedRolesForCreation = $require; return $this; }
    public function getDefaultPimcoreRole(): ?string { return $this->defaultPimcoreRole; }
    public function setDefaultPimcoreRole(?string $role): self { $this->defaultPimcoreRole = $role; return $this; }
    public function isSyncUserAttributesOnLogin(): bool { return $this->syncUserAttributesOnLogin; }
    public function setSyncUserAttributesOnLogin(bool $sync): self { $this->syncUserAttributesOnLogin = $sync; return $this; }
    public function getHandleDisabledUsers(): string { return $this->handleDisabledUsers; }
    public function setHandleDisabledUsers(string $handle): self { $this->handleDisabledUsers = $handle; return $this; }

    public function getAllowedDomains(): array { return $this->allowedDomains; }
    public function setAllowedDomains(array $domains): self { $this->allowedDomains = array_values(array_filter($domains)); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
