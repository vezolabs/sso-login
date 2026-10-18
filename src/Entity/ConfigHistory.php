<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Entity;

class ConfigHistory
{
    private ?int $id = null;
    private ?int $providerId = null;
    private int $version;
    private string $action;
    private string $changedByUser;
    /** @var array<string, mixed> */
    private array $configurationSnapshot;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $version,
        string $action,
        string $changedByUser,
        array $configurationSnapshot,
        ?int $providerId = null
    ) {
        $this->version = $version;
        $this->action = $action;
        $this->changedByUser = $changedByUser;
        $this->configurationSnapshot = $configurationSnapshot;
        $this->providerId = $providerId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function fromArray(array $data): self
    {
        $history = new self(
            (int) ($data['version'] ?? 1),
            (string) ($data['action'] ?? 'UPDATE'),
            (string) ($data['changed_by_user'] ?? 'system'),
            \is_array($data['configuration_snapshot'] ?? null)
                ? $data['configuration_snapshot']
                : (json_decode($data['configuration_snapshot'] ?? '{}', true) ?: []),
            isset($data['provider_id']) ? (int) $data['provider_id'] : null
        );

        if (isset($data['id'])) {
            $history->id = (int) $data['id'];
        }
        if (isset($data['created_at']) && $data['created_at']) {
            $history->createdAt = new \DateTimeImmutable((string) $data['created_at']);
        }

        return $history;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->providerId,
            'version' => $this->version,
            'action' => $this->action,
            'changed_by_user' => $this->changedByUser,
            'configuration_snapshot' => $this->configurationSnapshot,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getProviderId(): ?int { return $this->providerId; }
    public function getVersion(): int { return $this->version; }
    public function getAction(): string { return $this->action; }
    public function getChangedByUser(): string { return $this->changedByUser; }
    public function getConfigurationSnapshot(): array { return $this->configurationSnapshot; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
