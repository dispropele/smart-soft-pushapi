<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'currencies')]
class Currency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Укажите символ или код валюты.')]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(pattern: '/^[\p{L}\p{M}0-9$€₽¥£.\s\-]{1,50}$/u', message: 'Допустимы буквы, цифры и распространённые знаки валют.')]
    private ?string $currency = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Укажите полное название валюты.')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
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
}
