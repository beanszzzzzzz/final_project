<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CustomerRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public ?string $phone = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public ?string $address = null;
}
