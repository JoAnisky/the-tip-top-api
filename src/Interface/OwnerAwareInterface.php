<?php

namespace App\Interface;

use App\Entity\User;

interface OwnerAwareInterface
{
    public function getUser(): ?User;
}
