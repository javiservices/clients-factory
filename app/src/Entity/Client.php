<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @Datos personales cifrados – LOPD-GDD (LO 3/2018) / RGPD (UE 2016/679) */
    #[ORM\Column(type: 'encrypted_string')]
    private ?string $name = null;

    #[ORM\Column(type: 'encrypted_string')]
    private ?string $surname = null;

    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $locality = null;

    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $zip_code = null;

    #[ORM\Column(type: 'encrypted_date', nullable: true)]
    private ?\DateTimeInterface $birth_date = null;

    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $email = null;

    /** DNI/NIF – dato especialmente sensible bajo normativa española */
    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $id_number = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $start_date = null;

    #[ORM\Column(type: 'encrypted_string', nullable: true)]
    private ?string $card_code = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $billable = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastAccess = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'client')]
    private Collection $subscriptions;

    /**
     * @var Collection<int, AccessLog>
     */
    #[ORM\OneToMany(targetEntity: AccessLog::class, mappedBy: 'Client', orphanRemoval: true)]
    private Collection $accessLogs;

    /**
     * @var Collection<int, Invoice>
     */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'client')]
    private Collection $invoices;

    /**
     * @var Collection<int, BillingRecord>
     */
    #[ORM\OneToMany(targetEntity: BillingRecord::class, mappedBy: 'client')]
    private Collection $billingRecords;

    public function __construct()
    {
        $this->subscriptions = new ArrayCollection();
        $this->accessLogs = new ArrayCollection();
        $this->invoices = new ArrayCollection();
        $this->billingRecords = new ArrayCollection();
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

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): static
    {
        $this->surname = $surname;

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

    public function getLocality(): ?string
    {
        return $this->locality;
    }

    public function setLocality(?string $locality): static
    {
        $this->locality = $locality;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zip_code;
    }

    public function setZipCode(?string $zip_code): static
    {
        $this->zip_code = $zip_code;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birth_date;
    }

    public function setBirthDate(?\DateTimeInterface $birth_date): static
    {
        $this->birth_date = $birth_date;

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

    public function getIdNumber(): ?string
    {
        return $this->id_number;
    }

    public function setIdNumber(?string $id_number): static
    {
        $this->id_number = $id_number;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTimeInterface $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getCardCode(): ?string
    {
        return $this->card_code;
    }

    public function setCardCode(?string $card_code): static
    {
        $this->card_code = $card_code;

        return $this;
    }

    public function isBillable(): bool
    {
        return $this->billable;
    }

    public function setBillable(bool $billable): static
    {
        $this->billable = $billable;

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(Subscription $subscription): static
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setClient($this);
        }

        return $this;
    }

    public function removeSubscription(Subscription $subscription): static
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // set the owning side to null (unless already changed)
            if ($subscription->getClient() === $this) {
                $subscription->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AccessLog>
     */
    public function getAccessLogs(): Collection
    {
        return $this->accessLogs;
    }

    public function addAccessLog(AccessLog $accessLog): static
    {
        if (!$this->accessLogs->contains($accessLog)) {
            $this->accessLogs->add($accessLog);
            $accessLog->setClient($this);
        }

        return $this;
    }

    public function removeAccessLog(AccessLog $accessLog): static
    {
        if ($this->accessLogs->removeElement($accessLog)) {
            // set the owning side to null (unless already changed)
            if ($accessLog->getClient() === $this) {
                $accessLog->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setClient($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getClient() === $this) {
                $invoice->setClient(null);
            }
        }

        return $this;
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
            $billingRecord->setClient($this);
        }

        return $this;
    }

    public function removeBillingRecord(BillingRecord $billingRecord): static
    {
        if ($this->billingRecords->removeElement($billingRecord)) {
            // set the owning side to null (unless already changed)
            if ($billingRecord->getClient() === $this) {
                $billingRecord->setClient(null);
            }
        }

        return $this;
    }

    public function getLastAccess(): ?\DateTimeInterface
    {
        return $this->lastAccess;
    }

    public function setLastAccess(?\DateTimeInterface $lastAccess): static
    {
        $this->lastAccess = $lastAccess;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }
}
