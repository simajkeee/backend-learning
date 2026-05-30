<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use LogicException;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table('orders')]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    private Product $product;

    #[ORM\Column(type: Types::STRING, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    public ?\DateTimeImmutable $createdAt = null;


    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    public ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(mappedBy: 'relatedOrder', cascade: ['persist', 'remove'])]
    private ?OrderFulfillment $orderFulfillment = null;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function markPaid(): void
    {
        if ($this->status !== OrderStatus::PENDING) {
            throw new LogicException("Can't set paid status for the order with status {$this->status->value}");
        }

        $this->status = OrderStatus::PAID;
    }

    public function fulfill(): OrderFulfillment
    {
        if ($this->status !== OrderStatus::PAID) {
            throw new LogicException("Can't fulfill the order with status {$this->status->value}");
        }

        $this->status = OrderStatus::FULFILLED;

        $fulfillment = new OrderFulfillment($this);
        $this->orderFulfillment = $fulfillment;

        return $fulfillment;
    }

    public function refund(): void
    {
        if ($this->status !== OrderStatus::PAID) {
            throw new LogicException("Can't refund the order with status {$this->status->value}");
        }

        $this->status = OrderStatus::REFUNDED;
    }

    public function getOrderFulfillment(): ?OrderFulfillment
    {
        return $this->orderFulfillment;
    }
}
