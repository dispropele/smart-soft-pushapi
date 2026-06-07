<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'pledged_item_inserts')]
class PledgedItemInsert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PledgedItem::class, inversedBy: 'itemInserts')]
    #[ORM\JoinColumn(name: 'pledged_item_id', nullable: false, onDelete: 'CASCADE')]
    private ?PledgedItem $pledgedItem = null;

    #[ORM\ManyToOne(targetEntity: Insert::class)]
    #[ORM\JoinColumn(name: 'insert_id', nullable: false)]
    #[Assert\NotNull(message: 'Выберите вставку.')]
    private ?Insert $insert = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $weight = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive(message: 'Количество должно быть больше 0.')]
    private int $quantity = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __toString(): string
    {
        $label = $this->insert ? (string) $this->insert : 'Вставка';
        return $label . ($this->quantity > 1 ? ' ×' . $this->quantity : '');
    }

    public function getId(): ?int { return $this->id; }
    public function getPledgedItem(): ?PledgedItem { return $this->pledgedItem; }
    public function setPledgedItem(?PledgedItem $pledgedItem): static { $this->pledgedItem = $pledgedItem; return $this; }
    public function getInsert(): ?Insert { return $this->insert; }
    public function setInsert(?Insert $insert): static { $this->insert = $insert; return $this; }
    public function getWeight(): ?string { return $this->weight; }
    public function setWeight(?string $weight): static { $this->weight = $weight; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
}
