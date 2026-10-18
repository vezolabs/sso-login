<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Repository;

use Doctrine\DBAL\Connection;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Entity\ConfigHistory;

class IdentityProviderRepository
{
    public const string TABLE_NAME = 'vezolabs_sso_identity_providers';

    public function __construct(
        private readonly Connection $connection,
        private readonly ?ConfigHistoryRepository $historyRepository = null
    ) {
    }

    public function findById(int $id): ?IdentityProvider
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE id = ?',
            [$id]
        );

        return $data ? IdentityProvider::fromArray($data) : null;
    }

    public function findByName(string $name): ?IdentityProvider
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE name = ?',
            [$name]
        );

        return $data ? IdentityProvider::fromArray($data) : null;
    }

    public function findDefault(): ?IdentityProvider
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE is_default = 1 AND enabled = 1 LIMIT 1'
        );

        if ($data) {
            return IdentityProvider::fromArray($data);
        }

        // Fallback: first enabled provider
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE enabled = 1 ORDER BY id ASC LIMIT 1'
        );

        return $data ? IdentityProvider::fromArray($data) : null;
    }

    /**
     * @return array<IdentityProvider>
     */
    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' ORDER BY id ASC'
        );

        return array_map([IdentityProvider::class, 'fromArray'], $rows);
    }

    /**
     * @return array<IdentityProvider>
     */
    public function findEnabled(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE enabled = 1 ORDER BY id ASC'
        );

        return array_map([IdentityProvider::class, 'fromArray'], $rows);
    }

    public function save(IdentityProvider $provider, string $changedByUser = 'admin', string $action = 'UPDATE'): void
    {
        $provider->touch();
        $data = [
            'name' => $provider->getName(),
            'type' => $provider->getType(),
            'display_name' => $provider->getDisplayName(),
            'description' => $provider->getDescription(),
            'enabled' => $provider->isEnabled() ? 1 : 0,
            'is_default' => $provider->isDefault() ? 1 : 0,
            'tenant_id' => $provider->getTenantId(),
            'idp_entity_id' => $provider->getIdpEntityId(),
            'idp_sso_url' => $provider->getIdpSsoUrl(),
            'idp_slo_url' => $provider->getIdpSloUrl(),
            'idp_x509_cert' => $provider->getIdpX509Cert(),
            'idp_x509_cert_rollover' => $provider->getIdpX509CertRollover(),
            'idp_metadata_url' => $provider->getIdpMetadataUrl(),
            'idp_metadata_xml' => $provider->getIdpMetadataXml(),
            'sp_entity_id' => $provider->getSpEntityId(),
            'sp_acs_url' => $provider->getSpAcsUrl(),
            'sp_slo_url' => $provider->getSpSloUrl(),
            'sp_x509_cert' => $provider->getSpX509Cert(),
            'sp_private_key' => $provider->getSpPrivateKey(),
            'name_id_format' => $provider->getNameIdFormat(),
            'sign_authn_requests' => $provider->isSignAuthnRequests() ? 1 : 0,
            'want_assertions_signed' => $provider->isWantAssertionsSigned() ? 1 : 0,
            'want_response_signed' => $provider->isWantResponseSigned() ? 1 : 0,
            'login_button_label' => $provider->getLoginButtonLabel(),
            'login_button_visible' => $provider->isLoginButtonVisible() ? 1 : 0,
            'login_button_icon' => $provider->getLoginButtonIcon(),
            'hide_local_login' => $provider->isHideLocalLogin() ? 1 : 0,
            'auto_redirect_sso' => $provider->isAutoRedirectSso() ? 1 : 0,
            'attribute_mapping' => json_encode($provider->getAttributeMapping(), JSON_THROW_ON_ERROR),
            'role_mapping' => json_encode($provider->getRoleMapping(), JSON_THROW_ON_ERROR),
            'role_sync_policy' => $provider->getRoleSyncPolicy(),
            'unmapped_role_policy' => $provider->getUnmappedRolePolicy(),
            'restricted_fallback_role' => $provider->getRestrictedFallbackRole(),
            'revoke_removed_roles' => $provider->isRevokeRemovedRoles() ? 1 : 0,
            'provisioning_enabled' => $provider->isProvisioningEnabled() ? 1 : 0,
            'require_mapped_roles_for_creation' => $provider->isRequireMappedRolesForCreation() ? 1 : 0,
            'default_pimcore_role' => $provider->getDefaultPimcoreRole(),
            'sync_user_attributes_on_login' => $provider->isSyncUserAttributesOnLogin() ? 1 : 0,
            'handle_disabled_users' => $provider->getHandleDisabledUsers(),
            'allowed_domains' => json_encode($provider->getAllowedDomains(), JSON_THROW_ON_ERROR),
            'updated_at' => $provider->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        // If setting as default, clear any other default
        if ($provider->isDefault()) {
            $this->connection->executeStatement(
                'UPDATE ' . self::TABLE_NAME . ' SET is_default = 0 WHERE id != ?',
                [$provider->getId() ?? 0]
            );
        }

        if ($provider->getId() === null) {
            $data['created_at'] = $provider->getCreatedAt()->format('Y-m-d H:i:s');
            $this->connection->insert(self::TABLE_NAME, $data);
            $provider->setId((int) $this->connection->lastInsertId());
            $action = 'CREATE';
        } else {
            $this->connection->update(self::TABLE_NAME, $data, ['id' => $provider->getId()]);
        }

        // Record history snapshot
        if ($this->historyRepository !== null && $provider->getId() !== null) {
            $this->historyRepository->recordSnapshot($provider, $changedByUser, $action);
        }
    }

    public function delete(int $id): void
    {
        $this->connection->delete(self::TABLE_NAME, ['id' => $id]);
    }
}
