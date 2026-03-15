<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SubscriptionType $subscriptionType = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $remainingPasses = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastPaymentDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $frozenAt = null;

    /**
     * @var Collection<int, BillingRecord>
     */
    #[ORM\OneToMany(targetEntity: BillingRecord::class, mappedBy: 'subscription')]
    private Collection $billingRecords;

    public function __construct()
    {
        $this->billingRecords = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getSubscriptionType(): ?SubscriptionType
    {
        return $this->subscriptionType;
    }

    public function setSubscriptionType(?SubscriptionType $subscriptionType): static
    {
        $this->subscriptionType = $subscriptionType;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getRemainingPasses(): ?int
    {
        return $this->remainingPasses;
    }

    public function setRemainingPasses(?int $remainingPasses): static
    {
        $this->remainingPasses = $remainingPasses;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastPaymentDate(): ?\DateTimeInterface
    {
        return $this->lastPaymentDate;
    }

    public function setLastPaymentDate(\DateTimeInterface $lastPaymentDate): static
    {
        $this->lastPaymentDate = $lastPaymentDate;

        return $this;
    }

    public function getFrozenAt(): ?\DateTimeInterface
    {
        return $this->frozenAt;
    }

    public function setFrozenAt(?\DateTimeInterface $frozenAt): static
    {
        $this->frozenAt = $frozenAt;

        return $this;
    }

    public function isFrozen(): bool
    {
        return $this->frozenAt !== null;
    }

    /**
     * @return Collection<int, BillingRecord>
     */
    public function getBillingRecords(): Collection
    {
        return $this->billingRecords;
    }

    public function addBillingRecord(BillingRecord $billingRecord): static
    {
        if (!$this->billingRecords->contains($billingRecord)) {
            $this->billingRecords->add($billingRecord);
            $billingRecord->setSubscription($this);
        }

        return $this;
    }

    public function removeBillingRecord(BillingRecord $billingRecord): static
    {
        if ($this->billingRecords->removeElement($billingRecord)) {
            // set the owning side to null (unless already changed)
            if ($billingRecord->getSubscription() === $this) {
                $billingRecord->setSubscription(null);
            }
        }

        return $this;
    }
}
