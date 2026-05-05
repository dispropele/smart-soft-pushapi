<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'loan_tickets')]
class LoanTicket
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $ticketNumber = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'loanTickets')]
    #[ORM\JoinColumn(name: 'client_id', nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $loanAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $interestRate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $issuedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $returnDate = null;

    #[ORM\Column(length: 50)]
    private ?string $status = self::STATUS_OPEN;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'loanTicket', targetEntity: LoanedItem::class, cascade: ['persist', 'remove'])]
    private Collection $loanedItems;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->loanedItems = new ArrayCollection();
        // Устанавливаем дату выдачи по умолчанию - сейчас
        $this->issuedAt = new \DateTime(); 
        // Устанавливаем статус по умолчанию
        $this->status = self::STATUS_OPEN;
    }

    public function __toString(): string
    {
        return $this->ticketNumber ?? '';
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicketNumber(): ?string
    {
        return $this->ticketNumber;
    }

    public function setTicketNumber(string $ticketNumber): static
    {
        $this->ticketNumber = $ticketNumber;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getLoanAmount(): ?string
    {
        return $this->loanAmount;
    }

    public function setLoanAmount(string $loanAmount): static
    {
        $this->loanAmount = $loanAmount;
        return $this;
    }

    public function getInterestRate(): ?string
    {
        return $this->interestRate;
    }

    public function setInterestRate(?string $interestRate): static
    {
        $this->interestRate = $interestRate;
        return $this;
    }

    public function getIssuedAt(): ?\DateTimeInterface
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeInterface $issuedAt): static
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }

    public function getReturnDate(): ?\DateTimeInterface
    {
        return $this->returnDate;
    }

    public function setReturnDate(\DateTimeInterface $returnDate): static
    {
        $this->returnDate = $returnDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLoanedItems(): Collection
    {
        return $this->loanedItems;
    }

    public function addLoanedItem(LoanedItem $loanedItem): static
    {
        if (!$this->loanedItems->contains($loanedItem)) {
            $this->loanedItems->add($loanedItem);
            $loanedItem->setLoanTicket($this);
        }
        return $this;
    }

    public function removeLoanedItem(LoanedItem $loanedItem): static
    {
        if ($this->loanedItems->removeElement($loanedItem)) {
            if ($loanedItem->getLoanTicket() === $this) {
                $loanedItem->setLoanTicket(null);
            }
        }
        return $this;
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->issuedAt !== null && $this->returnDate !== null) {
            if ($this->returnDate < $this->issuedAt) {
                $context->buildViolation('Дата возврата не может быть раньше даты выдачи')
                    ->atPath('returnDate')
                    ->addViolation();
            }
        }
    }
}
