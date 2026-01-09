<?php

namespace App\Entity;

use App\Repository\PersonneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PersonneRepository::class)
 */
class Personne
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $prenom;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nom;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $naissance;

    /**
     * @ORM\ManyToOne(targetEntity=Personne::class, inversedBy="enfantPere")
     */
    private $pere;

    /**
     * @ORM\OneToMany(targetEntity=Personne::class, mappedBy="pere")
     */
    private $enfantPere;

    /**
     * @ORM\ManyToOne(targetEntity=Personne::class, inversedBy="enfantMere")
     */
    private $mere;

    /**
     * @ORM\OneToMany(targetEntity=Personne::class, mappedBy="mere")
     */
    private $enfantMere;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mort = false;

    /**
     * @ORM\ManyToMany(targetEntity=Personne::class, inversedBy="personnes")
     */
    private $partenaires;

    /**
     * @ORM\ManyToMany(targetEntity=Personne::class, mappedBy="partenaires")
     */
    private $personnes;

    /**
     * @ORM\Column(type="string", length=1)
     */
    private $genre;

    /**
     * @ORM\ManyToOne(targetEntity=Generation::class, inversedBy="personnes")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $generation;

    public function __construct()
    {
        $this->enfantPere = new ArrayCollection();
        $this->enfantMere = new ArrayCollection();
        $this->partenaires = new ArrayCollection();
        $this->personnes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getNaissance(): ?\DateTimeInterface
    {
        return $this->naissance;
    }

    public function setNaissance(?\DateTimeInterface $naissance): self
    {
        $this->naissance = $naissance;

        return $this;
    }

    public function getPere(): ?self
    {
        return $this->pere;
    }

    public function setPere(?self $pere): self
    {
        $this->pere = $pere;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getEnfantPere(): Collection
    {
        return $this->enfantPere;
    }

    public function addEnfantPere(self $personne): self
    {
        if (!$this->enfantPere->contains($personne)) {
            $this->enfantPere[] = $personne;
            $personne->setPere($this);
        }

        return $this;
    }

    public function removeEnfantPere(self $personne): self
    {
        if ($this->enfantPere->removeElement($personne)) {
            // set the owning side to null (unless already changed)
            if ($personne->getPere() === $this) {
                $personne->setPere(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getEnfantMere(): Collection
    {
        return $this->enfantMere;
    }

    public function addEnfantMere(self $personne): self
    {
        if (!$this->enfantMere->contains($personne)) {
            $this->enfantMere[] = $personne;
            $personne->setMere($this);
        }

        return $this;
    }

    public function removeEnfantMere(self $personne): self
    {
        if ($this->enfantMere->removeElement($personne)) {
            // set the owning side to null (unless already changed)
            if ($personne->getPere() === $this) {
                $personne->setMere(null);
            }
        }

        return $this;
    }

    public function getMere(): ?self
    {
        return $this->mere;
    }

    public function setMere(?self $mere): self
    {
        $this->mere = $mere;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->getPrenom().' '.$this->getNom();
    }

    public function isMort(): ?bool
    {
        return $this->mort;
    }

    public function setMort(bool $mort): self
    {
        $this->mort = $mort;

        return $this;
    }

    public function isAlive(): bool
    {
        return !$this->mort;
    }

    /**
     * @return Collection<int, self>
     */
    public function getPartenaires(): Collection
    {
        return $this->partenaires;
    }

    public function addPartenaire(self $partenaire): self
    {
        if (!$this->partenaires->contains($partenaire)) {
            $this->partenaires[] = $partenaire;
        }

        return $this;
    }

    public function removePartenaire(self $partenaire): self
    {
        $this->partenaires->removeElement($partenaire);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getPersonnes(): Collection
    {
        return $this->personnes;
    }

    public function addPersonne(self $personne): self
    {
        if (!$this->personnes->contains($personne)) {
            $this->personnes[] = $personne;
            $personne->addPartenaire($this);
        }

        return $this;
    }

    public function removePersonne(self $personne): self
    {
        if ($this->personnes->removeElement($personne)) {
            $personne->removePartenaire($this);
        }

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): self
    {
        $this->genre = $genre;

        return $this;
    }

    public function getGeneration(): ?Generation
    {
        return $this->generation;
    }

    public function setGeneration(?Generation $generation): self
    {
        $this->generation = $generation;

        return $this;
    }
}