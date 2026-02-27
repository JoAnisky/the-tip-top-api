<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ContactRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    public string $firstName;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    public string $lastName;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['question', 'bug', 'help', 'demo'])]
    public string $subject;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10)]
    public string $message;
}
