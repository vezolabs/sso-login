<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Repository;

use Doctrine\DBAL\Connection;
use Vezolabs\SsoLoginBundle\Entity\ExternalIdentity;

class ExternalIdentityRepository
{
    public const string TABLE_NAME = 'vezolabs_sso_external_identities';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findByExternalSubject(int $providerId, string $subjectId): ?ExternalIdentity
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE provider_id = ? AND external_subject_id = ?',
            [$providerId, $subjectId]
        );

        return $data ? ExternalIdentity::fromArray($data) : null;
    }

    public function findByPimcoreUserId(int $pimcoreUserId): ?ExternalIdentity
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE pimcore_user_id = ?',
            [$pimcoreUserId]
        );

        return $data ? ExternalIdentity::fromArray($data) : null;
    }

    public function countSsoUsers(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(DISTINCT pimcore_user_id) FROM ' . self::TABLE_NAME
        );
    }

    public function save(ExternalIdentity $identity): void
    {
        $identity->touch();
        $data = [
            'provider_id' => $identity->getProviderId(),
            'pimcore_user_id' => $identity->getPimcoreUserId(),
            'external_subject_id' => $identity->getExternalSubjectId(),
            'external_email' => $identity->getExternalEmail(),
            'last_login_at' => $identity->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $identity->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        if ($identity->getId() === null) {
            $data['created_at'] = $identity->getCreatedAt()->format('Y-m-d H:i:s');
            $this->connection->insert(self::TABLE_NAME, $data);
            $identity->setId((int) $this->connection->lastInsertId());
        } else {
            $this->connection->update(self::TABLE_NAME, $data, ['id' => $identity->getId()]);
        }
    }

    public function deleteByPimcoreUserId(int $pimcoreUserId): void
    {
        $this->connection->delete(self::TABLE_NAME, ['pimcore_user_id' => $pimcoreUserId]);
    }
}
