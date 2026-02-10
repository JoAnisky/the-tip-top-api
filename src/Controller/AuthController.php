<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/auth', name: 'app_auth')]
final class AuthController extends AbstractController
{

    public function __construct(private TokenService $tokenService) {}

    /**
     * @throws Exception
     */
    #[Route('/register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email'] ?? '');
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? '');
        $user->setGender($data['gender'] ?? 'male');

        if (isset($data['phoneNumber'])) $user->setPhoneNumber($data['phoneNumber']);
        if (isset($data['address'])) $user->setAddress($data['address']);
        if (isset($data['city'])) $user->setCity($data['city']);
        if (isset($data['postalCode'])) $user->setPostalCode($data['postalCode']);
        if (isset($data['birthDate'])) {
            $user->setBirthDate(new \DateTimeImmutable($data['birthDate']));
        }
        $user->setIsNewsletterSubscribed($data['newsletter'] ?? false);

        if (empty($data['plainPassword'])) {
            return new JsonResponse(['success' => false, 'message' => 'Mot de passe requis'], 400);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $data['plainPassword']));

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(['success' => false, 'errors' => $errorMessages], 400);
        }

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'user' => ['id' => $user->getId(), 'email' => $user->getEmail()]
        ], 201);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(): void
    {
        // Géré par security.yaml
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $token = $request->cookies->get('refresh_token');
        if (!$token) {
            return new JsonResponse(['success' => false], 401);
        }

        $refreshToken = $this->tokenService->validateRefreshToken($token);
        if (!$refreshToken) {
            return new JsonResponse(['success' => false], 401);
        }

        $user = $refreshToken->getUser();
        $newRefreshToken = $this->tokenService->rotateRefreshToken($refreshToken);
        $accessToken = $this->tokenService->createAccessToken($user);

        $response = new JsonResponse([
            'success' => true,
            'accessToken' => $accessToken,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ]
        ]);

        $response->headers->setCookie($this->createCookie($newRefreshToken->getToken()));
        return $response;
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $token = $request->cookies->get('refresh_token');
        if ($token) {
            $refreshToken = $this->tokenService->validateRefreshToken($token);
            if ($refreshToken) {
                $this->tokenService->revokeRefreshToken($refreshToken);
            }
        }

        $response = new JsonResponse(['success' => true]);
        $response->headers->clearCookie('refresh_token', '/', $_ENV['SESSION_COOKIE_DOMAIN'] ?? null);
        return $response;
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['success' => false], 401);
        }

        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ]
        ]);
    }

    private function createCookie(string $token): Cookie
    {
        return Cookie::create('refresh_token')
            ->withValue($token)
            ->withExpires(time() + 30 * 24 * 60 * 60)
            ->withPath('/')
            ->withDomain($_ENV['SESSION_COOKIE_DOMAIN'] ?? null)
            ->withSecure($_ENV['SESSION_COOKIE_SECURE'] === 'true')
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }
}
