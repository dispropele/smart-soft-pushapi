<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'good_types')]
class GoodType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Код автогенерируется из названия (nullable после миграции) */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $hasStones = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $coating = null; // Покрытие (родий, позолота и т.д.)

    public function getCoating(): ?string { return $this->coating; }
    public function setCoating(?string $coating): static { $this->coating = $coating; return $this; }   

    public function __construct()
    {
    }

    public function __toString(): string
    {
        $cat = $this->category ? ' (' . $this->category->getName() . ')' : '';
        return ($this->name ?? '') . $cat;
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): static { $this->code = $code; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }
    public function isHasStones(): bool { return $this->hasStones; }
    public function setHasStones(bool $hasStones): static { $this->hasStones = $hasStones; return $this; }
}
