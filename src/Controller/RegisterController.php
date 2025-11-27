<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController extends AbstractController
{
    #[Route('/api/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $JWTTokenManager,
        UserRepository $userRepository,
        ValidatorInterface $validator,
        LoggerInterface $logger
    ): JsonResponse {

        // récupération des données envoyées
        $data = json_decode($request->getContent(), true);

        // Validation des champs requis
        $requiredFields = ['email', 'password', 'firstName', 'lastName'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(
                    ['error' => "Le champ '$field' est requis"],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        // Valider le format de l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(
                ['error' => 'Le format de l\'email est invalide'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Vérifier que l'email n'existe pas déjà
        $existingUser = $userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse(
                ['error' => 'Cet email est déjà utilisé'],
                Response::HTTP_CONFLICT
            );
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setRegisteredIn(new \DateTimeImmutable());
        $user->setRoles(['ROLE_USER']);

        // Hasher et définir le mot de passe
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Valider l'entité avec le composant Validator
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return new JsonResponse(
                ['errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (\Exception $e) {

            $logger->error('Erreur lors de l\'enregistrement', [
                'email' => $data['email'],
                'exception' => $e->getMessage()
            ]);

            // Erreur renvoyée au front
            return new JsonResponse(
                ['error' => 'Une erreur est survenue lors de l\'enregistrement'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        // Génère le token directement après inscription
        try {
            $token = $JWTTokenManager->create($user);
        } catch (\Exception $e) {
            $logger->error('Erreur lors de la création du token JWT', [
                'email' => $user->getEmail(),
                'exception' => $e->getMessage()
            ]);

            return new JsonResponse(
                ['error' => 'Erreur lors de la génération du token'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse([
            'message' => 'Utilisateur créé avec succès',
            'user' => [
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ],
            'roles' => $user->getRoles(),
            'token' => $token,
        ], Response::HTTP_CREATED);
    }
}
