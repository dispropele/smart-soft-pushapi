<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'clients')]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Укажите ФИО.')]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $fullName = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Укажите номер паспорта.')]
    #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Номер паспорта — ровно 6 цифр.')]
    private ?string $passportNumber = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^(\d{4})?$/', message: 'Серия паспорта — 4 цифры или оставьте пустым.')]
    private ?string $passportSeries = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^(\d{10,11})?$/', message: 'Телефон: 10–11 цифр (без + и скобок).')]
    private ?string $phone = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Optional([
        new Assert\Email(message: 'Некорректный e-mail.'),
        new Assert\Length(max: 100),
    ])]
    private ?string $email = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: LoanTicket::class)]
    private Collection $loanTickets;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->loanTickets = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->fullName ?? '';
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getPassportNumber(): ?string
    {
        return $this->passportNumber;
    }

    public function setPassportNumber(string $passportNumber): static
    {
        $this->passportNumber = $passportNumber;
        return $this;
    }

    public function getPassportSeries(): ?string
    {
        return $this->passportSeries;
    }

    public function setPassportSeries(?string $passportSeries): static
    {
        $this->passportSeries = $passportSeries;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLoanTickets(): Collection
    {
        return $this->loanTickets;
    }

    public function addLoanTicket(LoanTicket $loanTicket): static
    {
        if (!$this->loanTickets->contains($loanTicket)) {
            $this->loanTickets->add($loanTicket);
            $loanTicket->setClient($this);
        }
        return $this;
    }

    public function removeLoanTicket(LoanTicket $loanTicket): static
    {
        if ($this->loanTickets->removeElement($loanTicket)) {
            if ($loanTicket->getClient() === $this) {
                $loanTicket->setClient(null);
            }
        }
        return $this;
    }
}
