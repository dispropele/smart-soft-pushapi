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
    public const STATUS_OPEN         = 'open';
    public const STATUS_GRACE        = 'grace';    // льготный период
    public const STATUS_CLOSED       = 'closed';
    public const STATUS_EXPIRED      = 'expired';
    public const STATUS_REPLEDGED    = 'repledged'; // перезалог (заменён новым билетом)
    public const STATUS_CANCELLED    = 'cancelled'; // аннулирован

    public const DEFAULT_LOAN_DAYS  = 30;
    public const DEFAULT_GRACE_DAYS = 30;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\Length(max: 50)]
    private ?string $ticketNumber = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'loanTickets')]
    #[ORM\JoinColumn(name: 'client_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Выберите клиента.')]
    private ?Client $client = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank(message: 'Укажите сумму займа.')]
    #[Assert\Positive(message: 'Сумма займа должна быть больше 0.')]
    private ?string $loanAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $interestRate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $issuedAt = null;

    /** Дата окончания основного срока */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $returnDate = null;

    /** Длительность льготного периода в днях */
    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 0, max: 3650, notInRangeMessage: 'От 0 до 3650 дней.')]
    private int $graceDays = self::DEFAULT_GRACE_DAYS;

    /** Дата фактического закрытия билета */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $closedAt = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000)]
    private ?string $notes = null;

    /** Ссылка на билет-преемник при перезалоге */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'repledged_to_id', nullable: true)]
    private ?self $repledgedTo = null;

    /** Ссылка на исходный билет, из которого создан перезалог */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'repledged_from_id', nullable: true)]
    private ?self $repledgedFrom = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'loanTicket', targetEntity: PledgedItem::class, cascade: ['persist'])]
    private Collection $pledgedItems;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0'])]
    private string $paidInterest = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0'])]
    private string $paidPrincipal = '0';

    /** Ежедневная ставка в % — фиксируется на момент выдачи */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4, nullable: true)]
    private ?string $dailyInterestRate = null;

    #[ORM\ManyToOne(targetEntity: Tariff::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Tariff $tariff = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->pledgedItems = new ArrayCollection();
        $this->issuedAt = new \DateTime();
        $this->returnDate = (new \DateTime())->modify('+' . self::DEFAULT_LOAN_DAYS . ' days');
    }

    public function __toString(): string { return $this->ticketNumber ?? ''; }

    // --- Status helpers ---
    public function isOpen(): bool      { return $this->status === self::STATUS_OPEN; }
    public function isGrace(): bool     { return $this->status === self::STATUS_GRACE; }
    public function isClosed(): bool    { return $this->status === self::STATUS_CLOSED; }
    public function isExpired(): bool   { return $this->status === self::STATUS_EXPIRED; }
    public function isRepledged(): bool { return $this->status === self::STATUS_REPLEDGED; }
    public function isActive(): bool    { return in_array($this->status, [self::STATUS_OPEN, self::STATUS_GRACE]); }

    // --- Методы расчёта ---

    /** Реальное количество дней с момента выдачи (минимум 1) */
    public function getElapsedDays(?\DateTimeInterface $atDate = null): int
    {
        $atDate = $atDate ?? new \DateTime();
        $issued = (clone \DateTime::createFromInterface($this->issuedAt))->setTime(0, 0, 0);
        $target = (clone \DateTime::createFromInterface($atDate))->setTime(0, 0, 0);
        $days   = $issued->diff($target)->days;
        return max(1, $days);
    }

    /** Накопившийся процент на дату (с учётом ежедневной ставки) */
    public function getAccruedInterest(?\DateTimeInterface $atDate = null): float
    {
        $days      = $this->getElapsedDays($atDate);
        $principal = (float)($this->loanAmount ?? 0);
        $rate      = (float)($this->dailyInterestRate ?? 0) / 100;
        return round($principal * $rate * $days, 2);
    }

    /** Полная сумма к погашению: тело займа + накопленные проценты */
    public function getTotalDebt(?\DateTimeInterface $atDate = null): float
    {
        return round((float) ($this->loanAmount ?? 0) + $this->getAccruedInterest($atDate), 2);
    }

    /** Точных дней до конца основного срока (отрицательное = просрочен) */
    public function getExactDaysLeft(): int
    {
        if (!$this->returnDate) return 0;
        $now        = (new \DateTime())->setTime(0, 0, 0);
        $returnDate = (clone \DateTime::createFromInterface($this->returnDate))->setTime(0, 0, 0);
        $invert     = $now > $returnDate ? -1 : 1;
        return $now->diff($returnDate)->days * $invert;
    }

    /** Дата окончания льготного периода */
    public function getGraceEndDate(): ?\DateTime
    {
        if (!$this->returnDate) return null;
        return (clone \DateTime::createFromInterface($this->returnDate))
            ->modify("+{$this->graceDays} days");
    }

    /** Актуальная сумма к выкупу с учётом процентов */
    public function getReturnAmount(): string
    {
        $amount = (float)($this->loanAmount ?? 0);
        $rate   = (float)($this->interestRate ?? 0);
        return number_format($amount * (1 + $rate / 100), 2, '.', '');
    }

    /** Сумма с учётом льготного периода (дополнительные проценты за grace) */
    public function getGraceReturnAmount(): string
    {
        $amount = (float)($this->loanAmount ?? 0);
        $rate   = (float)($this->interestRate ?? 0);
        $months = 1 + ($this->graceDays / 30);
        return number_format($amount * (1 + $rate / 100 * $months), 2, '.', '');
    }

    /** Дней до конца основного срока (отрицательное = просрочен) */
    public function getDaysLeft(): int
    {
        if (!$this->returnDate) return 0;
        return (int)ceil(($this->returnDate->getTimestamp() - time()) / 86400);
    }

    /** Дней до конца льготного периода */
    public function getGraceDaysLeft(): int
    {
        $graceEnd = $this->getGraceEndDate();
        if (!$graceEnd) return 0;
        return (int)ceil(($graceEnd->getTimestamp() - time()) / 86400);
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->issuedAt && $this->returnDate && $this->returnDate < $this->issuedAt) {
            $context->buildViolation('Дата возврата не может быть раньше даты выдачи')
                ->atPath('returnDate')->addViolation();
        }

        if ($this->interestRate !== null && $this->interestRate !== '') {
            $r = (float) str_replace(',', '.', $this->interestRate);
            if ($r < 0 || $r > 100) {
                $context->buildViolation('Процент в месяц должен быть от 0 до 100.')
                    ->atPath('interestRate')->addViolation();
            }
        }
    }

    #[Assert\Callback]
    public function validateLoanAmount(ExecutionContextInterface $context): void
    {
        $loanAmount = (float) ($this->loanAmount ?? 0);
        if ($loanAmount <= 0) {
            return;
        }

        $totalEstimate = 0.0;
        foreach ($this->pledgedItems as $item) {
            $totalEstimate += (float) ($item->getEstimatedValue() ?? 0);
        }

        if ($totalEstimate > 0 && $loanAmount > $totalEstimate) {
            $context->buildViolation(
                sprintf('Сумма займа (%.2f ₽) не может превышать общую оценочную стоимость изделий (%.2f ₽).', $loanAmount, $totalEstimate)
            )->atPath('loanAmount')->addViolation();
        }
    }

    /** @return list<string> */
    public static function statusChoices(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_GRACE,
            self::STATUS_CLOSED,
            self::STATUS_EXPIRED,
            self::STATUS_REPLEDGED,
            self::STATUS_CANCELLED,
        ];
    }

    // Getters/Setters
    public function getId(): ?int { return $this->id; }
    public function getTicketNumber(): ?string { return $this->ticketNumber; }
    public function setTicketNumber(string $v): static { $this->ticketNumber = $v; return $this; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $c): static { $this->client = $c; return $this; }
    public function getLoanAmount(): ?string { return $this->loanAmount; }
    public function setLoanAmount(string $v): static { $this->loanAmount = $v; return $this; }
    public function getInterestRate(): ?string { return $this->interestRate; }
    public function setInterestRate(?string $v): static { $this->interestRate = $v; return $this; }
    public function getIssuedAt(): ?\DateTimeInterface { return $this->issuedAt; }
    public function setIssuedAt(\DateTimeInterface $v): static { $this->issuedAt = $v; return $this; }
    public function getReturnDate(): ?\DateTimeInterface { return $this->returnDate; }
    public function setReturnDate(\DateTimeInterface $v): static { $this->returnDate = $v; return $this; }
    public function getGraceDays(): int { return $this->graceDays; }
    public function setGraceDays(int $v): static { $this->graceDays = $v; return $this; }
    public function getClosedAt(): ?\DateTimeInterface { return $this->closedAt; }
    public function setClosedAt(?\DateTimeInterface $v): static { $this->closedAt = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(?string $v): static
    {
        if ($v !== null) {
            $this->status = $v;
        }
        return $this;
    }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getRepledgedTo(): ?self { return $this->repledgedTo; }
    public function setRepledgedTo(?self $v): static { $this->repledgedTo = $v; return $this; }
    public function getRepledgedFrom(): ?self { return $this->repledgedFrom; }
    public function setRepledgedFrom(?self $v): static { $this->repledgedFrom = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $v): static { $this->updatedAt = $v; return $this; }
    public function getPledgedItems(): Collection { return $this->pledgedItems; }

    public function addPledgedItem(PledgedItem $item): static
    {
        if (!$this->pledgedItems->contains($item)) {
            $this->pledgedItems->add($item);
            $item->setLoanTicket($this);
        }
        return $this;
    }

    public function removePledgedItem(PledgedItem $item): static
    {
        if ($this->pledgedItems->removeElement($item)) {
            if ($item->getLoanTicket() === $this) $item->setLoanTicket(null);
        }
        return $this;
    }

    public function getPaidInterest(): string
    {
        return $this->paidInterest;
    }

    public function setPaidInterest(string $paidInterest): static
    {
        $this->paidInterest = $paidInterest;
        return $this;
    }

    public function getPaidPrincipal(): string
    {
        return $this->paidPrincipal;
    }

    public function setPaidPrincipal(string $paidPrincipal): static
    {
        $this->paidPrincipal = $paidPrincipal;
        return $this;
    }

    public function getDailyInterestRate(): ?string
    {
        return $this->dailyInterestRate;
    }

    public function setDailyInterestRate(?string $dailyInterestRate): static
    {
        $this->dailyInterestRate = $dailyInterestRate;
        return $this;
    }

    public function getTariff(): ?Tariff
    {
        return $this->tariff;
    }

    public function setTariff(?Tariff $tariff): static
    {
        $this->tariff = $tariff;
        return $this;
    }
}