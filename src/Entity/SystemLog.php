<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity]
#[ORM\Table(name: 'system_logs')]
class SystemLog
{
    public const LEVEL_INFO     = 'info';
    public const LEVEL_WARNING  = 'warning';
    public const LEVEL_ERROR    = 'error';
    public const LEVEL_CRITICAL = 'critical';

    public const CHANNEL_AUTH     = 'auth';
    public const CHANNEL_REPLEDGE = 'repledge';
    public const CHANNEL_SALE     = 'sale';
    public const CHANNEL_SYSTEM   = 'system';
    public const CHANNEL_TICKET   = 'ticket';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $level = self::LEVEL_INFO;

    #[ORM\Column(length: 50)]
    private string $channel = self::CHANNEL_SYSTEM;

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column(type: Types::JSON)]
    private array $context = [];

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $referenceId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getLevel(): string { return $this->level; }
    public function setLevel(string $v): static { $this->level = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $v): static { $this->message = $v; return $this; }
    public function getContext(): array { return $this->context; }
    public function setContext(array $v): static { $this->context = $v; return $this; }
    public function getReferenceId(): ?int { return $this->referenceId; }
    public function setReferenceId(?int $v): static { $this->referenceId = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}