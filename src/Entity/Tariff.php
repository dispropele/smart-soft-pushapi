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
        return sprintf('%s · %s%%/день', $this->name ?? '', self::formatPercent((float) $this->dailyRate));
    }

    /** Процент в месяц (30 дней) для отображения и расчётов */
    public function getMonthlyRate(): string
    {
        return (string) round((float) $this->dailyRate * 30, 2);
    }

    public static function formatPercent(float $value, int $maxDecimals = 2): string
    {
        $formatted = rtrim(rtrim(number_format($value, $maxDecimals, '.', ''), '0'), '.');

        return str_replace('.', ',', $formatted);
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
