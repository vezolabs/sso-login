<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Repository;

use Doctrine\DBAL\Connection;
use Vezolabs\SsoLoginBundle\Entity\ConfigHistory;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;

class ConfigHistoryRepository
{
    public const string TABLE_NAME = 'vezolabs_sso_config_history';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function recordSnapshot(IdentityProvider $provider, string $changedByUser, string $action): void
    {
        $version = (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(version), 0) + 1 FROM ' . self::TABLE_NAME . ' WHERE provider_id = ?',
            [$provider->getId()]
        );

        $snapshot = $provider->toArray(true);

        $data = [
            'provider_id' => $provider->getId(),
            'version' => $version,
            'action' => $action,
            'changed_by_user' => $changedByUser,
            'configuration_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->connection->insert(self::TABLE_NAME, $data);
    }

    /**
     * @return array<ConfigHistory>
     */
    public function findByProviderId(int $providerId, int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE provider_id = ? ORDER BY version DESC LIMIT ?',
            [$providerId, $limit],
            [\PDO::PARAM_INT, \PDO::PARAM_INT]
        );

        return array_map([ConfigHistory::class, 'fromArray'], $rows);
    }

    public function findByVersion(int $providerId, int $version): ?ConfigHistory
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE provider_id = ? AND version = ?',
            [$providerId, $version]
        );

        return $row ? ConfigHistory::fromArray($row) : null;
    }
}
