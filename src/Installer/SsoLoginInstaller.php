<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Pimcore\Extension\Bundle\Installer\AbstractInstaller;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Repository\AuditLogRepository;
use Vezolabs\SsoLoginBundle\Repository\ConfigHistoryRepository;
use Vezolabs\SsoLoginBundle\Repository\ExternalIdentityRepository;
use Vezolabs\SsoLoginBundle\Repository\IdentityProviderRepository;
use Vezolabs\SsoLoginBundle\VezolabsSsoLoginBundle;

class SsoLoginInstaller extends AbstractInstaller
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IdentityProviderRepository $providerRepository
    ) {
        parent::__construct();
    }

    public function install(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $schema = new Schema([], [], $schemaManager->createSchemaConfig());

        // 1. Providers table
        if (!$schemaManager->tablesExist([IdentityProviderRepository::TABLE_NAME])) {
            $table = $schema->createTable(IdentityProviderRepository::TABLE_NAME);
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('name', 'string', ['length' => 100]);
            $table->addColumn('type', 'string', ['length' => 50, 'default' => 'microsoft_entra_id']);
            $table->addColumn('display_name', 'string', ['length' => 190]);
            $table->addColumn('description', 'text', ['notnull' => false]);
            $table->addColumn('enabled', 'boolean', ['default' => false]);
            $table->addColumn('is_default', 'boolean', ['default' => false]);
            $table->addColumn('tenant_id', 'string', ['length' => 100, 'notnull' => false]);

            $table->addColumn('idp_entity_id', 'string', ['length' => 255]);
            $table->addColumn('idp_sso_url', 'string', ['length' => 255]);
            $table->addColumn('idp_slo_url', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('idp_x509_cert', 'text');
            $table->addColumn('idp_x509_cert_rollover', 'text', ['notnull' => false]);
            $table->addColumn('idp_metadata_url', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('idp_metadata_xml', 'text', ['notnull' => false, 'length' => 16777215]); // MediumText

            $table->addColumn('sp_entity_id', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('sp_acs_url', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('sp_slo_url', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('sp_x509_cert', 'text', ['notnull' => false]);
            $table->addColumn('sp_private_key', 'text', ['notnull' => false]);

            $table->addColumn('name_id_format', 'string', ['length' => 190, 'default' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified']);
            $table->addColumn('sign_authn_requests', 'boolean', ['default' => false]);
            $table->addColumn('want_assertions_signed', 'boolean', ['default' => true]);
            $table->addColumn('want_response_signed', 'boolean', ['default' => false]);

            $table->addColumn('login_button_label', 'string', ['length' => 100, 'default' => 'Sign in with Microsoft']);
            $table->addColumn('login_button_visible', 'boolean', ['default' => true]);
            $table->addColumn('login_button_icon', 'string', ['length' => 50, 'default' => 'microsoft']);
            $table->addColumn('hide_local_login', 'boolean', ['default' => false]);
            $table->addColumn('auto_redirect_sso', 'boolean', ['default' => false]);

            $table->addColumn('attribute_mapping', 'text');
            $table->addColumn('role_mapping', 'text');
            $table->addColumn('role_sync_policy', 'string', ['length' => 50, 'default' => 'every_login']);
            $table->addColumn('unmapped_role_policy', 'string', ['length' => 50, 'default' => 'deny']);
            $table->addColumn('restricted_fallback_role', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('revoke_removed_roles', 'boolean', ['default' => true]);

            $table->addColumn('provisioning_enabled', 'boolean', ['default' => true]);
            $table->addColumn('require_mapped_roles_for_creation', 'boolean', ['default' => true]);
            $table->addColumn('default_pimcore_role', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('sync_user_attributes_on_login', 'boolean', ['default' => true]);
            $table->addColumn('handle_disabled_users', 'string', ['length' => 50, 'default' => 'deny_access']);
            $table->addColumn('allowed_domains', 'text');

            $table->addColumn('created_at', 'datetime');
            $table->addColumn('updated_at', 'datetime');

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['name'], 'uniq_sso_provider_name');

            foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
                $this->connection->executeStatement($sql);
            }
        }

        // 2. External identities table
        if (!$schemaManager->tablesExist([ExternalIdentityRepository::TABLE_NAME])) {
            $schema = new Schema([], [], $schemaManager->createSchemaConfig());
            $table = $schema->createTable(ExternalIdentityRepository::TABLE_NAME);
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('provider_id', 'integer');
            $table->addColumn('pimcore_user_id', 'integer');
            $table->addColumn('external_subject_id', 'string', ['length' => 190]);
            $table->addColumn('external_email', 'string', ['length' => 190, 'notnull' => false]);
            $table->addColumn('last_login_at', 'datetime', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime');
            $table->addColumn('updated_at', 'datetime');

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['provider_id', 'external_subject_id'], 'uniq_prov_ext_subj');
            $table->addIndex(['pimcore_user_id'], 'idx_pimcore_user_id');

            foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
                $this->connection->executeStatement($sql);
            }
        }

        // 3. Audit logs table
        if (!$schemaManager->tablesExist([AuditLogRepository::TABLE_NAME])) {
            $schema = new Schema([], [], $schemaManager->createSchemaConfig());
            $table = $schema->createTable(AuditLogRepository::TABLE_NAME);
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('timestamp', 'datetime');
            $table->addColumn('provider_id', 'integer', ['notnull' => false]);
            $table->addColumn('provider_name', 'string', ['length' => 190, 'notnull' => false]);
            $table->addColumn('username', 'string', ['length' => 190, 'notnull' => false]);
            $table->addColumn('external_user_id', 'string', ['length' => 190, 'notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 50]);
            $table->addColumn('reason', 'text', ['notnull' => false]);
            $table->addColumn('assigned_roles', 'text', ['notnull' => false]);
            $table->addColumn('attributes_summary', 'text', ['notnull' => false]);
            $table->addColumn('ip_address', 'string', ['length' => 45, 'notnull' => false]);
            $table->addColumn('user_agent', 'text', ['notnull' => false]);
            $table->addColumn('correlation_id', 'string', ['length' => 64]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['timestamp'], 'idx_sso_log_timestamp');
            $table->addIndex(['status'], 'idx_sso_log_status');
            $table->addIndex(['correlation_id'], 'idx_sso_log_correlation');

            foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
                $this->connection->executeStatement($sql);
            }
        }

        // 4. Config history table
        if (!$schemaManager->tablesExist([ConfigHistoryRepository::TABLE_NAME])) {
            $schema = new Schema([], [], $schemaManager->createSchemaConfig());
            $table = $schema->createTable(ConfigHistoryRepository::TABLE_NAME);
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('provider_id', 'integer', ['notnull' => false]);
            $table->addColumn('version', 'integer');
            $table->addColumn('action', 'string', ['length' => 50]);
            $table->addColumn('changed_by_user', 'string', ['length' => 190]);
            $table->addColumn('configuration_snapshot', 'text', ['length' => 16777215]); // MediumText
            $table->addColumn('created_at', 'datetime');

            $table->setPrimaryKey(['id']);
            $table->addIndex(['provider_id', 'version'], 'idx_sso_hist_prov_ver');

            foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
                $this->connection->executeStatement($sql);
            }
        }

        // 5. Register permissions in Pimcore
        $this->registerPermissions();

        // 6. Seed initial Entra ID template provider if none exists
        if (\count($this->providerRepository->findAll()) === 0) {
            $defaultEntra = new IdentityProvider();
            $defaultEntra->setName('entra-id');
            $defaultEntra->setType(IdentityProvider::TYPE_ENTRA_ID);
            $defaultEntra->setDisplayName('Microsoft Entra ID');
            $defaultEntra->setDescription('Enterprise SAML Single Sign-On via Microsoft Entra ID');
            $defaultEntra->setEnabled(false); // Disabled until administrator enters credentials
            $defaultEntra->setIsDefault(true);
            $defaultEntra->setLoginButtonLabel('Sign in with Microsoft');
            $defaultEntra->setLoginButtonIcon('microsoft');

            $this->providerRepository->save($defaultEntra, 'installer', 'CREATE');
        }
    }

    public function uninstall(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = [
            ConfigHistoryRepository::TABLE_NAME,
            AuditLogRepository::TABLE_NAME,
            ExternalIdentityRepository::TABLE_NAME,
            IdentityProviderRepository::TABLE_NAME,
        ];

        foreach ($tables as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $this->connection->executeStatement('DROP TABLE ' . $table);
            }
        }

        // Remove permissions
        $this->connection->executeStatement(
            'DELETE FROM users_permission_definitions WHERE `key` IN (?, ?)',
            [VezolabsSsoLoginBundle::PERMISSION_MANAGE, VezolabsSsoLoginBundle::PERMISSION_VIEW]
        );
    }

    public function isInstalled(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tablesExist([IdentityProviderRepository::TABLE_NAME]);
    }

    public function canBeInstalled(): bool
    {
        return !$this->isInstalled();
    }

    public function canBeUninstalled(): bool
    {
        return $this->isInstalled();
    }

    private function registerPermissions(): void
    {
        $permissions = [
            ['key' => VezolabsSsoLoginBundle::PERMISSION_MANAGE, 'category' => 'settings'],
            ['key' => VezolabsSsoLoginBundle::PERMISSION_VIEW, 'category' => 'settings'],
        ];

        foreach ($permissions as $perm) {
            $exists = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM users_permission_definitions WHERE `key` = ?',
                [$perm['key']]
            );
            if ($exists === 0) {
                $this->connection->executeStatement(
                    'INSERT INTO users_permission_definitions (`key`, `category`) VALUES (?, ?)',
                    [$perm['key'], $perm['category']]
                );
            }
        }
    }
}
