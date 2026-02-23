<?php

namespace App\Controller;

use App\Repository\CodeRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted("ROLE_ADMIN")]
class AdminStatsController extends AbstractController
{
    public function __construct(
        private CodeRepository $codeRepository,
        private UserRepository $userRepository,
    )
    {}

    #[Route('/stats', name: 'app_admin_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->json([
            'tickets'  => $this->codeRepository->getTicketStats(),
            'gains'    => $this->codeRepository->getGainStats(),
            'winners'  => $this->userRepository->getWinnersStats(),
        ]);
    }
}
