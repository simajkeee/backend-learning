<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PaymentProviderEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: PaymentProviderEventRepository::class)]
class PaymentProviderEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private ?string $providerEventId = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $payload = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(inversedBy: 'paymentProviderEvent', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'order_id', nullable: false)]
    private ?Order $relatedOrder = null;

    public function __construct(Order $order, string $providerEventId, string $payload)
    {
        $this->relatedOrder = $order;
        $this->providerEventId = $providerEventId;
        $this->payload = $payload;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProviderEventId(): ?string
    {
        return $this->providerEventId;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }

    public function isSameProviderEventId(string $providerEventId): bool
    {
        return $this->providerEventId === $providerEventId;
    }
}
