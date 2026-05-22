<?php

namespace App\Entity;

use App\Repository\LandingPageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LandingPageRepository::class)]
class LandingPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
