<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

final class RegisterController extends AbstractController
{
    #[Route('/api/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $JWTTokenManager,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setRegisteredIn(new \DateTimeImmutable());

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $user->setRoles(['ROLE_USER']);

        try {
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (\Exception $e) {

            // Erreur à log avec plus d'infos
            $logger->error('Une erreur est survenue lors de l\'enregistrement.', [
                'erreur' => $e->getMessage()
            ]);

            // Erreur renvoyée au front
            return $this->json([
                'error' => 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Génère le token directement après inscription
        $token = $JWTTokenManager->create($user);

        return $this->json([
            'message' => 'Utilisateur créé avec succès',
            'user' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'token' => $token,
        ]);
    }
}
