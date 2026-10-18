<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Entity;

class AuditLog
{
    public const string STATUS_SUCCESS = 'SUCCESS';
    public const string STATUS_FAILED = 'FAILED';
    public const string STATUS_DENIED = 'DENIED';

    private ?int $id = null;
    private \DateTimeImmutable $timestamp;
    private ?int $providerId = null;
    private ?string $providerName = null;
    private ?string $username = null;
    private ?string $externalUserId = null;
    private string $status;
    private ?string $reason = null;
    /** @var array<string> */
    private array $assignedRoles = [];
    /** @var array<string, mixed> Safe summary of claims, no secrets */
    private array $attributesSummary = [];
    private ?string $ipAddress = null;
    private ?string $userAgent = null;
    private string $correlationId;

    public function __construct(
        string $status,
        string $correlationId,
        ?string $reason = null,
        ?string $username = null,
        ?string $providerName = null,
        ?int $providerId = null
    ) {
        $this->timestamp = new \DateTimeImmutable();
        $this->status = $status;
        $this->correlationId = $correlationId;
        $this->reason = $reason;
        $this->username = $username;
        $this->providerName = $providerName;
        $this->providerId = $providerId;
    }

    public static function fromArray(array $data): self
    {
        $log = new self(
            (string) ($data['status'] ?? self::STATUS_FAILED),
            (string) ($data['correlation_id'] ?? bin2hex(random_bytes(8))),
            $data['reason'] ?? null,
            $data['username'] ?? null,
            $data['provider_name'] ?? null,
            isset($data['provider_id']) ? (int) $data['provider_id'] : null
        );

        if (isset($data['id'])) {
            $log->id = (int) $data['id'];
        }
        if (isset($data['timestamp']) && $data['timestamp']) {
            $log->timestamp = new \DateTimeImmutable((string) $data['timestamp']);
        }
        $log->externalUserId = $data['external_user_id'] ?? null;
        $log->assignedRoles = \is_array($data['assigned_roles'] ?? null)
            ? $data['assigned_roles']
            : (json_decode($data['assigned_roles'] ?? '[]', true) ?: []);
        $log->attributesSummary = \is_array($data['attributes_summary'] ?? null)
            ? $data['attributes_summary']
            : (json_decode($data['attributes_summary'] ?? '{}', true) ?: []);
        $log->ipAddress = $data['ip_address'] ?? null;
        $log->userAgent = $data['user_agent'] ?? null;

        return $log;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp->format(\DateTimeInterface::ATOM),
            'provider_id' => $this->providerId,
            'provider_name' => $this->providerName,
            'username' => $this->username,
            'external_user_id' => $this->externalUserId,
            'status' => $this->status,
            'reason' => $this->reason,
            'assigned_roles' => $this->assignedRoles,
            'attributes_summary' => $this->attributesSummary,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'correlation_id' => $this->correlationId,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function getTimestamp(): \DateTimeImmutable { return $this->timestamp; }
    public function getProviderId(): ?int { return $this->providerId; }
    public function getProviderName(): ?string { return $this->providerName; }
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $u): self { $this->username = $u; return $this; }
    public function getExternalUserId(): ?string { return $this->externalUserId; }
    public function setExternalUserId(?string $id): self { $this->externalUserId = $id; return $this; }
    public function getStatus(): string { return $this->status; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): self { $this->reason = $reason; return $this; }
    public function getAssignedRoles(): array { return $this->assignedRoles; }
    public function setAssignedRoles(array $roles): self { $this->assignedRoles = $roles; return $this; }
    public function getAttributesSummary(): array { return $this->attributesSummary; }
    public function setAttributesSummary(array $attrs): self { $this->attributesSummary = $attrs; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ip): self { $this->ipAddress = $ip; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $ua): self { $this->userAgent = $ua; return $this; }
    public function getCorrelationId(): string { return $this->correlationId; }
}
