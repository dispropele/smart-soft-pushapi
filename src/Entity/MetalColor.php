<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'metal_colors')]
class MetalColor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Metal::class)]
    #[ORM\JoinColumn(name: "metal_id", nullable: true)]
    private ?Metal $metal = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    public function __toString(): string
    {
        $metalName = $this->metal ? $this->metal->getName() : '';
        return trim($metalName . ' ' . ($this->name ?? ''));
    }

    public function getId(): ?int { return $this->id; }
    
    public function getMetal(): ?Metal { return $this->metal; }
    public function setMetal(?Metal $metal): static { $this->metal = $metal; return $this; }
    
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): static { $this->code = $code; return $this; }
}
