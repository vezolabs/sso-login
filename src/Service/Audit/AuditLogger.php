<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Service\Audit;

use Symfony\Component\HttpFoundation\Request;
use Vezolabs\SsoLoginBundle\Entity\AuditLog;
use Vezolabs\SsoLoginBundle\Entity\IdentityProvider;
use Vezolabs\SsoLoginBundle\Repository\AuditLogRepository;

class AuditLogger
{
    public function __construct(private readonly AuditLogRepository $auditLogRepository)
    {
    }

    /**
     * Record a successful authentication attempt.
     *
     * @param array<int> $assignedRoleIds
     * @param array<string, array<string>> $rawAttributes
     */
    public function logSuccess(
        IdentityProvider $provider,
        string $username,
        string $externalSubjectId,
        array $assignedRoleIds,
        array $rawAttributes,
        Request $request,
        string $correlationId
    ): void {
        $log = new AuditLog(
            AuditLog::STATUS_SUCCESS,
            $correlationId,
            'Authentication succeeded and user was logged in.',
            $username,
            $provider->getDisplayName(),
            $provider->getId()
        );

        $log->setExternalUserId($externalSubjectId);
        $log->setAssignedRoles(array_map('strval', $assignedRoleIds));
        $log->setAttributesSummary($this->sanitizeAttributesSummary($rawAttributes));
        $log->setIpAddress($request->getClientIp());
        $log->setUserAgent(substr((string) $request->headers->get('User-Agent'), 0, 500));

        $this->auditLogRepository->log($log);
    }

    /**
     * Record a failed or denied authentication attempt.
     */
    public function logFailure(
        ?IdentityProvider $provider,
        string $reason,
        Request $request,
        string $correlationId,
        ?string $username = null,
        ?string $externalSubjectId = null,
        bool $isDenied = false
    ): void {
        $status = $isDenied ? AuditLog::STATUS_DENIED : AuditLog::STATUS_FAILED;

        $log = new AuditLog(
            $status,
            $correlationId,
            $reason,
            $username,
            $provider?->getDisplayName(),
            $provider?->getId()
        );

        $log->setExternalUserId($externalSubjectId);
        $log->setIpAddress($request->getClientIp());
        $log->setUserAgent(substr((string) $request->headers->get('User-Agent'), 0, 500));

        $this->auditLogRepository->log($log);
    }

    /**
     * Build safe attribute summary for audit logging (removes sensitive claims/tokens).
     *
     * @param array<string, array<string>> $attributes
     * @return array<string, mixed>
     */
    private function sanitizeAttributesSummary(array $attributes): array
    {
        $summary = [];
        $sensitiveKeys = ['password', 'secret', 'token', 'private_key', 'credential', 'auth_token'];

        foreach ($attributes as $key => $values) {
            $isSensitive = false;
            foreach ($sensitiveKeys as $bad) {
                if (stripos($key, $bad) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $summary[$key] = '[REDACTED]';
            } else {
                $summary[$key] = \count($values) === 1 ? reset($values) : $values;
            }
        }

        return $summary;
    }
}
