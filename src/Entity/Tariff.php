<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'tariffs')]
class Tariff
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $name = null;

    /** Ежедневная ставка в % (например 0.3 = 0.3% в день) */
    #[ORM\Column(type: 'decimal', precision: 8, scale: 4)]
    #[Assert\Positive]
    #[Assert\LessThan(100)]
    private ?string $dailyRate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function __toString(): string
    {
        return sprintf('%s (%.4f%%/день)', $this->name ?? '', (float)$this->dailyRate);
    }

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

    public function getDailyRate(): ?string
    {
        return $this->dailyRate;
    }

    public function setDailyRate(string $dailyRate): static
    {
        $this->dailyRate = $dailyRate;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
}
