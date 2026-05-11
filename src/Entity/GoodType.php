<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'good_types')]
class GoodType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $hasStones = false;

    #[ORM\OneToMany(mappedBy: 'goodType', targetEntity: Good::class)]
    private Collection $goods;

    public function __construct()
    {
        $this->goods = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }
    public function isHasStones(): bool { return $this->hasStones; }
    public function setHasStones(bool $hasStones): static { $this->hasStones = $hasStones; return $this; }
    public function getGoods(): Collection { return $this->goods; }
    public function addGood(Good $good): static { if (!$this->goods->contains($good)) { $this->goods->add($good); $good->setGoodType($this); } return $this; }
    public function removeGood(Good $good): static { if ($this->goods->removeElement($good)) { if ($good->getGoodType() === $this) { $good->setGoodType(null); } } return $this; }
}
