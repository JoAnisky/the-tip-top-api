<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ClaimInput
{
    #[Groups(['code:claim'])]
    #[Assert\NotBlank(message: "Le code est obligatoire.")]
    #[Assert\Length(
        min: 10,
        max: 10,
        exactMessage: "Le code doit faire exactement {{ limit }} caractères."
    )]
    public string $code = '';
}
