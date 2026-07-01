<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Currency;
use App\Enum\OrderStatus;
use App\Exception\OrderNotFulfillableException;
use App\Exception\OrderNotPayableException;
use App\Exception\OrderNotRefundableException;
use App\Exception\OrderPaymentProviderEventException;
use App\Repository\OrderRepository;
use App\ValueObject\Currency as CurrencyValue;
use App\ValueObject\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table('orders')]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null; // @phpstan-ignore property.unusedType

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

    #[ORM\OneToOne(mappedBy: 'relatedOrder', cascade: ['persist', 'remove'])]
    private ?PaymentProviderEvent $paymentProviderEvent = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $total;

    #[ORM\Column(type: Types::STRING, length: 3, enumType: Currency::class)]
    private Currency $currency;

    public function __construct(Product $product)
    {
        $this->product = $product;
        $this->total = $product->getPrice();
        $this->currency = Currency::USD;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isPaid(): bool
    {
        return OrderStatus::PAID === $this->status;
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
        if (OrderStatus::PAID === $this->status) {
            return;
        }

        if (OrderStatus::PENDING !== $this->status) {
            throw OrderNotPayableException::withDefaultMsg($this->status);
        }

        $this->status = OrderStatus::PAID;
    }

    public function fulfill(): OrderFulfillment
    {
        if (OrderStatus::PAID !== $this->status) {
            throw OrderNotFulfillableException::withDefaultMsg($this->status);
        }

        $this->status = OrderStatus::FULFILLED;

        $fulfillment = new OrderFulfillment($this);
        $this->orderFulfillment = $fulfillment;

        return $fulfillment;
    }

    public function refund(): void
    {
        if (OrderStatus::PAID !== $this->status) {
            throw OrderNotRefundableException::withDefaultMsg($this->status);
        }

        $this->status = OrderStatus::REFUNDED;
    }

    public function getOrderFulfillment(): ?OrderFulfillment
    {
        return $this->orderFulfillment;
    }

    public function assertPaidEventMatches(string $providerEventId): void
    {
        if (!$this->hasProviderEventId($providerEventId)) {
            throw OrderNotPayableException::withDefaultMsg($this->getStatus());
        }
    }

    public function hasProviderEventId(string $providerEventId): bool
    {
        if (null === $this->getPaymentProviderEvent()) {
            return false;
        }

        return $this->getPaymentProviderEvent()->isSameProviderEventId($providerEventId);
    }

    public function getPaymentProviderEvent(): ?PaymentProviderEvent
    {
        return $this->paymentProviderEvent;
    }

    public function attachPaymentProviderEvent(PaymentProviderEvent $paymentProviderEvent): void
    {
        if ($paymentProviderEvent->getRelatedOrder() !== $this) {
            throw OrderPaymentProviderEventException::withDefaultMsg();
        }

        $this->paymentProviderEvent = $paymentProviderEvent;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function hasEqualTotalTo(Money $money): bool
    {
        return Money::fromInt($this->total)->isEqual($money);
    }

    public function hasSameCurrencyAs(CurrencyValue $currency): bool
    {
        return CurrencyValue::fromEnum($this->currency)->isSame($currency);
    }
}
