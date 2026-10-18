<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Repository;

use Doctrine\DBAL\Connection;
use Vezolabs\SsoLoginBundle\Entity\AuditLog;

class AuditLogRepository
{
    public const string TABLE_NAME = 'vezolabs_sso_audit_logs';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function log(AuditLog $log): void
    {
        $data = [
            'timestamp' => $log->getTimestamp()->format('Y-m-d H:i:s'),
            'provider_id' => $log->getProviderId(),
            'provider_name' => $log->getProviderName(),
            'username' => $log->getUsername(),
            'external_user_id' => $log->getExternalUserId(),
            'status' => $log->getStatus(),
            'reason' => $log->getReason(),
            'assigned_roles' => json_encode($log->getAssignedRoles(), JSON_THROW_ON_ERROR),
            'attributes_summary' => json_encode($log->getAttributesSummary(), JSON_THROW_ON_ERROR),
            'ip_address' => $log->getIpAddress(),
            'user_agent' => $log->getUserAgent(),
            'correlation_id' => $log->getCorrelationId(),
        ];

        $this->connection->insert(self::TABLE_NAME, $data);
        $log->setId((int) $this->connection->lastInsertId());
    }

    /**
     * @return array{items: array<AuditLog>, total: int}
     */
    public function findFiltered(
        ?string $status = null,
        ?string $providerName = null,
        ?string $username = null,
        ?string $from = null,
        ?string $to = null,
        int $page = 1,
        int $limit = 25
    ): array {
        $qb = $this->connection->createQueryBuilder();
        $qb->from(self::TABLE_NAME, 'l');

        if ($status !== null && $status !== '') {
            $qb->andWhere('l.status = :status')->setParameter('status', $status);
        }
        if ($providerName !== null && $providerName !== '') {
            $qb->andWhere('l.provider_name = :provider_name')->setParameter('provider_name', $providerName);
        }
        if ($username !== null && $username !== '') {
            $qb->andWhere('l.username LIKE :username')->setParameter('username', '%' . $username . '%');
        }
        if ($from !== null && $from !== '') {
            $qb->andWhere('l.timestamp >= :from')->setParameter('from', $from);
        }
        if ($to !== null && $to !== '') {
            $qb->andWhere('l.timestamp <= :to')->setParameter('to', $to);
        }

        // Count
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(*)')->executeQuery()->fetchOne();

        // Items
        $offset = max(0, ($page - 1) * $limit);
        $rows = $qb->select('l.*')
            ->orderBy('l.timestamp', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        $items = array_map([AuditLog::class, 'fromArray'], $rows);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function findLastSuccessfulLogin(): ?AuditLog
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE status = ? ORDER BY timestamp DESC LIMIT 1',
            [AuditLog::STATUS_SUCCESS]
        );

        return $row ? AuditLog::fromArray($row) : null;
    }

    public function findLastFailedLogin(): ?AuditLog
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE status IN (?, ?) ORDER BY timestamp DESC LIMIT 1',
            [AuditLog::STATUS_FAILED, AuditLog::STATUS_DENIED]
        );

        return $row ? AuditLog::fromArray($row) : null;
    }

    public function prune(int $keepDays = 90): int
    {
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $keepDays))->format('Y-m-d H:i:s');

        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE_NAME . ' WHERE timestamp < ?',
            [$cutoff]
        );
    }
}
