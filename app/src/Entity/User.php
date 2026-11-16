<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    // Company information fields
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $taxId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $locality = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $zipCode = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $companyPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyProvince = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyCountry = 'España';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyEmail = null;

    #[ORM\Column(length: 34, nullable: true)]
    private ?string $companyIban = null;

    #[ORM\Column(length: 11, nullable: true)]
    private ?string $companyBic = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    // Company information getters and setters
    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function getCompanyCif(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): static
    {
        $this->taxId = $taxId;

        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(?string $companyAddress): static
    {
        $this->companyAddress = $companyAddress;

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
        return $this->zipCode;
    }

    public function setZipCode(?string $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getCompanyPhone(): ?string
    {
        return $this->companyPhone;
    }

    public function setCompanyPhone(?string $companyPhone): static
    {
        $this->companyPhone = $companyPhone;

        return $this;
    }

    public function getCompanyProvince(): ?string
    {
        return $this->companyProvince;
    }

    public function setCompanyProvince(?string $companyProvince): static
    {
        $this->companyProvince = $companyProvince;

        return $this;
    }

    public function getCompanyCountry(): ?string
    {
        return $this->companyCountry;
    }

    public function setCompanyCountry(?string $companyCountry): static
    {
        $this->companyCountry = $companyCountry;

        return $this;
    }

    public function getCompanyEmail(): ?string
    {
        return $this->companyEmail;
    }

    public function setCompanyEmail(?string $companyEmail): static
    {
        $this->companyEmail = $companyEmail;

        return $this;
    }

    public function getCompanyIban(): ?string
    {
        return $this->companyIban;
    }

    public function setCompanyIban(?string $companyIban): static
    {
        $this->companyIban = $companyIban ? strtoupper(str_replace([' ', '-', '.'], '', $companyIban)) : null;

        return $this;
    }

    public function getCompanyBic(): ?string
    {
        return $this->companyBic;
    }

    public function setCompanyBic(?string $companyBic): static
    {
        $this->companyBic = $companyBic ? strtoupper(trim($companyBic)) : null;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
