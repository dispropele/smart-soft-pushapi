<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'inserts')]
class Insert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InsertType::class)]
    #[ORM\JoinColumn(name: 'insert_type_id', nullable: false)]
    #[Assert\NotNull(message: 'Выберите тип вставки.')]
    private ?InsertType $insertType = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Укажите название вставки.')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    public function __toString(): string
    {
        $type = $this->insertType ? $this->insertType->getName() . ' / ' : '';
        return $type . ($this->name ?? '');
    }

    public function getId(): ?int { return $this->id; }
    public function getInsertType(): ?InsertType { return $this->insertType; }
    public function setInsertType(?InsertType $t): static { $this->insertType = $t; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
}