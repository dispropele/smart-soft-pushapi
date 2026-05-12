<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'pledged_item_images')]
class PledgedItemImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PledgedItem::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'pledged_item_id', nullable: false, onDelete: 'CASCADE')]
    private ?PledgedItem $pledgedItem = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $src = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $preview = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isCover = false;

    public function getId(): ?int { return $this->id; }
    public function getPledgedItem(): ?PledgedItem { return $this->pledgedItem; }
    public function setPledgedItem(?PledgedItem $p): static { $this->pledgedItem = $p; return $this; }
    public function getSrc(): ?string { return $this->src; }
    public function setSrc(string $src): static { $this->src = $src; return $this; }
    public function getPreview(): ?string { return $this->preview; }
    public function setPreview(string $preview): static { $this->preview = $preview; return $this; }
    public function isCover(): bool { return $this->isCover; }
    public function setIsCover(bool $v): static { $this->isCover = $v; return $this; }
}