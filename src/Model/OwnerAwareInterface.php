<?php

namespace App\Model;

use App\Entity\User;

interface OwnerAwareInterface
{
    public function getUser(): ?User;
}
