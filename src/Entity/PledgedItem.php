<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Repository\PledgedItemRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PledgedItemRepository::class)]
#[ORM\Table(name: 'pledged_items')]
#[ORM\HasLifecycleCallbacks]
class PledgedItem
{
public const STATUS_PLEDGED = 'pledged'; // на хранении
public const STATUS_REDEEMED = 'redeemed'; // выкуплен
public const STATUS_FOR_SALE = 'for_sale'; // передан на реализацию
public const STATUS_SOLD = 'sold'; // продан
public const STATUS_WITHDRAWN = 'withdrawn'; // изъят ломбардом
public const STATUS_HIDDEN = 'hidden'; // скрыт с витрины

#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: 'integer')]
private ?int $id = null;

#[ORM\ManyToOne(targetEntity: LoanTicket::class, inversedBy: 'pledgedItems')]
#[ORM\JoinColumn(name: 'loan_ticket_id', nullable: true, onDelete: 'SET NULL')]
private ?LoanTicket $loanTicket = null;

#[ORM\ManyToOne(targetEntity: Category::class)]
#[ORM\JoinColumn(nullable: true)]
private ?Category $category = null;

#[ORM\ManyToOne(targetEntity: GoodType::class)]
#[ORM\JoinColumn(name: 'good_type_id', nullable: true)]
private ?GoodType $goodType = null;

#[ORM\ManyToOne(targetEntity: MetalStandard::class)]
#[ORM\JoinColumn(name: 'metal_standard_id', nullable: true)]
private ?MetalStandard $metalStandard = null;

#[ORM\ManyToOne(targetEntity: MetalColor::class)]
#[ORM\JoinColumn(name: 'metal_color_id', nullable: true)]
private ?MetalColor $metalColor = null;

#[ORM\OneToMany(mappedBy: 'pledgedItem', targetEntity: PledgedItemInsert::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
#[Assert\Valid]
private Collection $itemInserts;

#[ORM\ManyToOne(targetEntity: Currency::class)]
#[ORM\JoinColumn(nullable: true)]
private ?Currency $currency = null;

#[ORM\Column(length: 255)]
private ?string $name = null;

#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $description = null;

#[ORM\Column(length: 100, nullable: true)]
private ?string $size = null;

#[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
private ?string $itemWeight = null;

#[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
private ?string $scrapWeight = null;

#[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
private ?string $estimatedValue = null;

#[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
private ?string $redemptionAmount = null;

#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
private ?\DateTimeInterface $redemptionDate = null;

#[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
private ?string $soldPrice = null;

#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $condition = null;

#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $specification = null;

#[ORM\Column(length: 50)]
private string $status = self::STATUS_PLEDGED;

#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
private ?\DateTimeInterface $statusDate = null;

#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
private ?\DateTimeInterface $publishedAt = null;

#[ORM\OneToMany(mappedBy: 'pledgedItem', targetEntity: PledgedItemImage::class, cascade: ['persist', 'remove'])]
private Collection $images;

public function __construct()
{
    $this->images = new ArrayCollection();
    $this->itemInserts = new ArrayCollection();
    $this->statusDate = new \DateTime();
}

#[ORM\PreUpdate]
public function onUpdate(): void { $this->statusDate = new \DateTime(); }

#[Assert\Callback]
public function validatePledgedItem(ExecutionContextInterface $context): void
{
    $name = trim((string) $this->getName());
    if ($name === '') {
        $context->buildViolation('Укажите название изделия.')
            ->atPath('name')
            ->addViolation();
    }

    $estimated = (float) ($this->getEstimatedValue() ?? 0);
    if ($estimated <= 0) {
        $context->buildViolation('Оценочная стоимость должна быть больше 0.')
            ->atPath('estimatedValue')
            ->addViolation();
    }

    $itemWeight  = (float) ($this->getItemWeight() ?? 0);
    $scrapWeight = (float) ($this->getScrapWeight() ?? 0);
    if ($itemWeight > 0 && $scrapWeight > 0 && $scrapWeight > $itemWeight) {
        $context->buildViolation(sprintf(
            'Вес лома (%.2f г) не может превышать вес изделия (%.2f г).',
            $scrapWeight, $itemWeight
        ))
            ->atPath('scrapWeight')
            ->addViolation();
    }

    if (!$this->isForSale() && !$this->isSold() && empty($this->getSoldPrice())) {
        return;
    }

    if (empty($this->getSoldPrice()) || (float) $this->getSoldPrice() <= 0) {
        $context->buildViolation('Для перевода на реализацию укажите цену продажи.')
            ->atPath('soldPrice')
            ->addViolation();
    }

    $hasUploadedImages = false;
    $root = $context->getRoot();
    if ($root instanceof FormInterface && $root->has('imageFiles')) {
        $files = $root->get('imageFiles')->getData();
        if (is_array($files)) {
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $hasUploadedImages = true;
                    break;
                }
            }
        }
    }

    if ($this->getImages()->isEmpty() && !$hasUploadedImages) {
        $context->buildViolation('Для перевода на реализацию загрузите хотя бы одно фото.')
            ->atPath('imageFiles')
            ->addViolation();
    }

    if (empty($this->getCondition())) {
        $context->buildViolation('Для перевода на реализацию укажите состояние изделия.')
            ->atPath('condition')
            ->addViolation();
    }

    $soldPrice = (float) ($this->getSoldPrice() ?? 0);
    if ($soldPrice > 0 && $estimated > 0 && $soldPrice < $estimated) {
        $context->buildViolation(sprintf(
            'Цена продажи (%.2f ₽) не может быть ниже оценочной стоимости (%.2f ₽).',
            $soldPrice, $estimated
        ))
            ->atPath('soldPrice')
            ->addViolation();
    }
}

public function __toString(): string { return $this->name ?? ''; }

public function isPledged(): bool { return $this->status === self::STATUS_PLEDGED; }
public function isRedeemed(): bool { return $this->status === self::STATUS_REDEEMED; }
public function isForSale(): bool { return $this->status === self::STATUS_FOR_SALE; }
public function isSold(): bool { return $this->status === self::STATUS_SOLD; }
public function isWithdrawn(): bool { return $this->status === self::STATUS_WITHDRAWN; }
public function isOnCatalog(): bool { return $this->status === self::STATUS_FOR_SALE; }

public function getStatusLabel(): string
{
return match($this->status) {
self::STATUS_PLEDGED => 'На хранении',
self::STATUS_REDEEMED => 'Выкуплен',
self::STATUS_FOR_SALE => 'На реализации',
self::STATUS_SOLD => 'Продан',
self::STATUS_WITHDRAWN => 'Изъят',
self::STATUS_HIDDEN => 'Скрыт',
default => $this->status,
};
}

public function getId(): ?int { return $this->id; }
public function getLoanTicket(): ?LoanTicket { return $this->loanTicket; }
public function setLoanTicket(?LoanTicket $t): static { $this->loanTicket = $t; return $this; }
public function getCategory(): ?Category { return $this->category; }
public function setCategory(?Category $c): static { $this->category = $c; return $this; }
public function getGoodType(): ?GoodType { return $this->goodType; }
public function setGoodType(?GoodType $t): static { $this->goodType = $t; return $this; }
public function getMetalStandard(): ?MetalStandard { return $this->metalStandard; }
public function setMetalStandard(?MetalStandard $ms): static { $this->metalStandard = $ms; return $this; }
public function getMetal(): ?Metal { return $this->metalStandard?->getMetal(); }
public function setMetal(?Metal $metal): static { return $this; }
public function getMetalColor(): ?MetalColor { return $this->metalColor; }
public function setMetalColor(?MetalColor $mc): static { $this->metalColor = $mc; return $this; }
public function getItemInserts(): Collection { return $this->itemInserts; }
public function addItemInsert(PledgedItemInsert $itemInsert): static
{
    if (!$this->itemInserts->contains($itemInsert)) {
        $this->itemInserts->add($itemInsert);
        $itemInsert->setPledgedItem($this);
    }
    return $this;
}
public function removeItemInsert(PledgedItemInsert $itemInsert): static
{
    if ($this->itemInserts->removeElement($itemInsert)) {
        if ($itemInsert->getPledgedItem() === $this) {
            $itemInsert->setPledgedItem(null);
        }
    }
    return $this;
}
public function getTotalInsertWeight(): float
{
    return array_sum($this->itemInserts->map(fn(PledgedItemInsert $insert) => (float) ($insert->getWeight() ?? 0))->toArray());
}
public function getCurrency(): ?Currency { return $this->currency; }
public function setCurrency(?Currency $c): static { $this->currency = $c; return $this; }
public function getName(): ?string { return $this->name; }
public function setName(string $n): static { $this->name = $n; return $this; }
public function getDescription(): ?string { return $this->description; }
public function setDescription(?string $v): static { $this->description = $v; return $this; }
public function getSize(): ?string { return $this->size; }
public function setSize(?string $v): static { $this->size = $v; return $this; }
public function getItemWeight(): ?string { return $this->itemWeight; }
public function setItemWeight(?string $v): static { $this->itemWeight = $v; return $this; }
public function getScrapWeight(): ?string { return $this->scrapWeight; }
public function setScrapWeight(?string $v): static { $this->scrapWeight = $v; return $this; }
public function getEstimatedValue(): ?string { return $this->estimatedValue; }
public function setEstimatedValue(?string $v): static { $this->estimatedValue = $v; return $this; }
public function getRedemptionAmount(): ?string { return $this->redemptionAmount; }
public function setRedemptionAmount(?string $v): static { $this->redemptionAmount = $v; return $this; }
public function getRedemptionDate(): ?\DateTimeInterface { return $this->redemptionDate; }
public function setRedemptionDate(?\DateTimeInterface $v): static { $this->redemptionDate = $v; return $this; }
public function getSoldPrice(): ?string { return $this->soldPrice; }
public function setSoldPrice(?string $v): static { $this->soldPrice = $v; return $this; }
public function getDisplayPrice(): ?string { return $this->soldPrice ?? $this->estimatedValue; }
public function getCondition(): ?string { return $this->condition; }
public function setCondition(?string $v): static { $this->condition = $v; return $this; }
public function getSpecification(): ?string { return $this->specification; }
public function setSpecification(?string $v): static { $this->specification = $v; return $this; }
public function getStatus(): string { return $this->status; }

// ИСПРАВЛЕНИЕ: Защита от передачи null из формы
public function setStatus(?string $s): static {
if ($s !== null) {
$this->status = $s;
}
return $this;
}

public function getStatusDate(): ?\DateTimeInterface { return $this->statusDate; }
public function setStatusDate(?\DateTimeInterface $v): static { $this->statusDate = $v; return $this; }
public function getPublishedAt(): ?\DateTimeInterface { return $this->publishedAt; }
public function setPublishedAt(?\DateTimeInterface $v): static { $this->publishedAt = $v; return $this; }
public function getImages(): Collection { return $this->images; }

public function addImage(PledgedItemImage $image): static
{
if (!$this->images->contains($image)) {
$this->images->add($image);
$image->setPledgedItem($this);
}
return $this;
}
}
