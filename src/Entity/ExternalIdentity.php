<?php

declare(strict_types=1);

namespace Vezolabs\SsoLoginBundle\Entity;

class ExternalIdentity
{
    private ?int $id = null;
    private int $providerId;
    private int $pimcoreUserId;
    private string $externalSubjectId;
    private ?string $externalEmail = null;
    private ?\DateTimeImmutable $lastLoginAt = null;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(int $providerId, int $pimcoreUserId, string $externalSubjectId, ?string $externalEmail = null)
    {
        $this->providerId = $providerId;
        $this->pimcoreUserId = $pimcoreUserId;
        $this->externalSubjectId = $externalSubjectId;
        $this->externalEmail = $externalEmail;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function fromArray(array $data): self
    {
        $entity = new self(
            (int) $data['provider_id'],
            (int) $data['pimcore_user_id'],
            (string) $data['external_subject_id'],
            $data['external_email'] ?? null
        );

        if (isset($data['id'])) {
            $entity->id = (int) $data['id'];
        }
        if (isset($data['last_login_at']) && $data['last_login_at']) {
            $entity->lastLoginAt = new \DateTimeImmutable((string) $data['last_login_at']);
        }
        if (isset($data['created_at']) && $data['created_at']) {
            $entity->createdAt = new \DateTimeImmutable((string) $data['created_at']);
        }
        if (isset($data['updated_at']) && $data['updated_at']) {
            $entity->updatedAt = new \DateTimeImmutable((string) $data['updated_at']);
        }

        return $entity;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->providerId,
            'pimcore_user_id' => $this->pimcoreUserId,
            'external_subject_id' => $this->externalSubjectId,
            'external_email' => $this->externalEmail,
            'last_login_at' => $this->lastLoginAt?->format(\DateTimeInterface::ATOM),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function getProviderId(): int { return $this->providerId; }
    public function getPimcoreUserId(): int { return $this->pimcoreUserId; }
    public function setPimcoreUserId(int $id): self { $this->pimcoreUserId = $id; return $this; }
    public function getExternalSubjectId(): string { return $this->externalSubjectId; }
    public function getExternalEmail(): ?string { return $this->externalEmail; }
    public function setExternalEmail(?string $email): self { $this->externalEmail = $email; return $this; }
    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeImmutable $date): self { $this->lastLoginAt = $date; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
}
