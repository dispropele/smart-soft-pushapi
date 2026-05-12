<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'metal_standards')]
class MetalStandard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Metal::class)]
    #[ORM\JoinColumn(name: "metal_id",nullable: false)]
    #[Assert\NotNull(message: 'Выберите металл.')]
    private ?Metal $metal = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Укажите пробу (например 585, 925).')]
    #[Assert\Length(min: 1, max: 50)]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMetal(): ?Metal
    {
        return $this->metal;
    }

    public function setMetal(?Metal $metal): static
    {
        $this->metal = $metal;

        return $this;
    }

    public function __toString(): string
    {
        $metalName = $this->metal ? $this->metal->getName() : '';
        return trim($metalName . ' ' . ($this->name ?? ''));
    }
}
