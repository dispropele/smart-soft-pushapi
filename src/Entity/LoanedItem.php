<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'loaned_items')]
class LoanedItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LoanTicket::class, inversedBy: 'loanedItems')]
    #[ORM\JoinColumn(name: 'loan_ticket_id', nullable: false, onDelete: 'CASCADE')]
    private ?LoanTicket $loanTicket = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $jewelryType = null;

    #[ORM\ManyToOne(targetEntity: Metal::class)]
    #[ORM\JoinColumn(name: 'metal_id', nullable: true)]
    private ?Metal $metal = null;

    #[ORM\ManyToOne(targetEntity: MetalStandard::class)]
    #[ORM\JoinColumn(name: 'metal_standard_id', nullable: true)]
    private ?MetalStandard $metalStandard = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $weight = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $estimatedValue = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $condition = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoanTicket(): ?LoanTicket
    {
        return $this->loanTicket;
    }

    public function setLoanTicket(?LoanTicket $loanTicket): static
    {
        $this->loanTicket = $loanTicket;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getJewelryType(): ?string
    {
        return $this->jewelryType;
    }

    public function setJewelryType(?string $jewelryType): static
    {
        $this->jewelryType = $jewelryType;
        return $this;
    }

    public function getMetal(): ?Metal
    {
        return $this->metal;
    }

    public function setMetal(?Metal $metal): static
    {
        $this->metal = $metal;
        return $this;
    }

    public function getMetalStandard(): ?MetalStandard
    {
        return $this->metalStandard;
    }

    public function setMetalStandard(?MetalStandard $metalStandard): static
    {
        $this->metalStandard = $metalStandard;
        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getEstimatedValue(): ?string
    {
        return $this->estimatedValue;
    }

    public function setEstimatedValue(string $estimatedValue): static
    {
        $this->estimatedValue = $estimatedValue;
        return $this;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(?string $condition): static
    {
        $this->condition = $condition;
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
}
