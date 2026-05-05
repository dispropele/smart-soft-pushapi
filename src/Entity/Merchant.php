<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'merchants')]
class Merchant
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: "city_id", nullable: true)]
    private ?City $city = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Regex(pattern: '/^\d*$/', message: 'Допустимы только цифры')]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shortlink = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $imageSrc = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $imagePreview = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }
    public function getShortlink(): ?string { return $this->shortlink; }
    public function setShortlink(?string $shortlink): static { $this->shortlink = $shortlink; return $this; }
    public function getImageSrc(): ?string { return $this->imageSrc; }
    public function setImageSrc(?string $imageSrc): static { $this->imageSrc = $imageSrc; return $this; }
    public function getImagePreview(): ?string { return $this->imagePreview; }
    public function setImagePreview(?string $imagePreview): static { $this->imagePreview = $imagePreview; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCity(): ?City { return $this->city; }
    public function setCity(?City $city): static { $this->city = $city; return $this; }
}
