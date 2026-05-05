<?php

namespace App\Entity;

use App\Repository\GoodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: GoodRepository::class)]
#[ORM\Table(name: 'goods')]
#[ORM\HasLifecycleCallbacks]
class Good
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SOLD      = 'sold';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_HIDDEN    = 'hidden';

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: "merchant_id", nullable: false)]
    private ?Merchant $merchant = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $soldPrice = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: "currency_id", nullable: true)]
    private ?Currency $currency = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true)]
    private ?Category $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specification = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $size = null;

    #[ORM\ManyToOne(targetEntity: MetalStandard::class)]
    #[ORM\JoinColumn(name: "metal_standard_id", nullable: true)]
    private ?MetalStandard $metalStandard = null;

    /**
     * статус: active | sold | withdrawn | hidden
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $statusDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $hiddenReason = null;

    #[ORM\OneToMany(mappedBy: 'good', targetEntity: GoodImage::class, cascade: ['persist', 'remove'])]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function refreshStatusDate(): void
    {
        $this->statusDate = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }


    /** товар показывается на витрине */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** скрыт с витрины по любой причине */
    public function isHidden(): bool
    {
        return $this->status !== self::STATUS_ACTIVE;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE    => 'Активно',
            self::STATUS_SOLD      => 'Продано',
            self::STATUS_WITHDRAWN => 'Изъято',
            self::STATUS_HIDDEN    => 'Скрыто',
            default                => $this->status ?? '—',
        };
    }

    public function getHiddenReasonLabel(): string
    {
        return match ($this->hiddenReason) {
            1       => 'Снято вручную',
            2       => 'Срок истёк',
            3       => 'Удалено',
            4       => 'Изъято',
            default => $this->hiddenReason ? "Причина #{$this->hiddenReason}" : '—',
        };
    }


    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getSoldPrice(): ?string { return $this->soldPrice; }
    public function setSoldPrice(?string $soldPrice): static { $this->soldPrice = $soldPrice; return $this; }

    public function getSpecification(): ?string { return $this->specification; }
    public function setSpecification(?string $specification): static { $this->specification = $specification; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getSize(): ?string { return $this->size; }
    public function setSize(?string $size): static { $this->size = $size; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $status): static { $this->status = $status; return $this; }

    public function getStatusDate(): ?\DateTimeInterface { return $this->statusDate; }
    public function setStatusDate(?\DateTimeInterface $statusDate): static { $this->statusDate = $statusDate; return $this; }

    public function getHiddenReason(): ?int { return $this->hiddenReason; }
    public function setHiddenReason(?int $hiddenReason): static { $this->hiddenReason = $hiddenReason; return $this; }

    public function getMerchant(): ?Merchant { return $this->merchant; }
    public function setMerchant(?Merchant $merchant): static { $this->merchant = $merchant; return $this; }

    public function getCurrency(): ?Currency { return $this->currency; }
    public function setCurrency(?Currency $currency): static { $this->currency = $currency; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    public function getMetalStandard(): ?MetalStandard { return $this->metalStandard; }
    public function setMetalStandard(?MetalStandard $metalStandard): static { $this->metalStandard = $metalStandard; return $this; }

    public function getImages(): Collection { return $this->images; }

    public function addImage(GoodImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setGood($this);
        }
        return $this;
    }

    public function removeImage(GoodImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getGood() === $this) $image->setGood(null);
        }
        return $this;
    }
}
