<?php

namespace App\Entity;

use App\Repository\GenerationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=GenerationRepository::class)
 * @ORM\Table(name="generation", uniqueConstraints={@ORM\UniqueConstraint(name="unique_display_order", columns={"display_order"})})
 */
class Generation
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
    private $generationName;

    /**
     * @ORM\Column(type="integer", unique=true)
     */
    private $displayOrder;

    /**
     * @ORM\OneToMany(targetEntity=Personne::class, mappedBy="generation")
     */
    private $personnes;

    public function __construct()
    {
        $this->personnes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGenerationName(): ?string
    {
        return $this->generationName;
    }

    public function setGenerationName(string $generationName): self
    {
        $this->generationName = $generationName;
        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;
        return $this;
    }

    /**
     * @return Collection<int, Personne>
     */
    public function getPersonnes(): Collection
    {
        return $this->personnes;
    }

    public function addPersonne(Personne $personne): self
    {
        if (!$this->personnes->contains($personne)) {
            $this->personnes[] = $personne;
            $personne->setGeneration($this);
        }
        return $this;
    }

    public function removePersonne(Personne $personne): self
    {
        if ($this->personnes->removeElement($personne)) {
            if ($personne->getGeneration() === $this) {
                $personne->setGeneration(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->generationName;
    }
}
