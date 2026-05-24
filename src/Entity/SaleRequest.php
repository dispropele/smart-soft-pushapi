<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sale_requests')]
class SaleRequest
{
    public const STATUS_REQUEST = 'request';
    public const STATUS_SOLD = 'sold';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PledgedItem::class)]
    #[ORM\JoinColumn(name: 'pledged_item_id', nullable: false, onDelete: 'CASCADE')]
    private ?PledgedItem $pledgedItem = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUS_REQUEST, self::STATUS_SOLD, self::STATUS_CANCELLED])]
    private string $status = self::STATUS_REQUEST;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Укажите ФИО.')]
    #[Assert\Length(max: 255)]
    private string $fullName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Укажите телефон.')]
    #[Assert\Length(max: 100)]
    private string $phone = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'Некорректный e-mail.')]
    #[Assert\Length(max: 255)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $soldPrice = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPledgedItem(): ?PledgedItem
    {
        return $this->pledgedItem;
    }

    public function setPledgedItem(PledgedItem $pledgedItem): static
    {
        $this->pledgedItem = $pledgedItem;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeInterface $processedAt): static
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    public function getSoldPrice(): ?string
    {
        return $this->soldPrice;
    }

    public function setSoldPrice(?string $soldPrice): static
    {
        $this->soldPrice = $soldPrice;
        return $this;
    }

    public function isRequest(): bool
    {
        return $this->status === self::STATUS_REQUEST;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_REQUEST => 'Заявка',
            self::STATUS_SOLD => 'Продан',
            self::STATUS_CANCELLED => 'Отменено',
            default => $this->status,
        };
    }

    #[Assert\Callback]
    public function validateSalePrice(ExecutionContextInterface $context): void
    {
        if ($this->status === self::STATUS_SOLD) {
            $soldPrice = (float) ($this->soldPrice ?? 0);
            if ($soldPrice <= 0) {
                $context->buildViolation('Для статуса «Продан» укажите цену продажи.')
                    ->atPath('soldPrice')
                    ->addViolation();
            }
        }
    }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->pledgedItem?->getName() ?? 'Изделие', $this->getStatusLabel());
    }
}
