<?php
namespace App\Entity;

use App\Repository\ProcessedLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProcessedLogRepository::class)]
class ProcessedLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'json')]
    private array $logs = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function setLogs(array $logs): self
    {
        $this->logs = $logs;
        return $this;
    }
}