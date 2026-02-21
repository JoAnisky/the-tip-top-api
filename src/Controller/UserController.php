<?php

namespace App\Controller;

use App\Entity\Code;
use App\Entity\User;
use App\Enum\Gender;
use App\Repository\CodeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/user')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {}
    #[Route('/me', name: 'api_users_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCurrentUser(CodeRepository $codeRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'gender' => $user->getGender()?->value,
            'birthDate' => $user->getBirthDate()?->format('Y-m-d'),
            'address' => $user->getAddress(),
            'postalCode' => $user->getPostalCode(),
            'city' => $user->getCity(),
            'newsletter' => $user->getNewsletter(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'hasOAuthAccounts' => $user->getSocialAccounts()->count() > 0,
            'gains' => $this->resolveGains($user, $codeRepository),
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/me', name: 'api_users_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    public function updateProfile(Request $request, UserRepository $userRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['gender'])) {
            $user->setGender(Gender::from($data['gender']));
        }

        if (isset($data['birthDate'])) {
            $user->setBirthDate(new \DateTimeImmutable($data['birthDate']));
        }

        if (isset($data['email'])) {
            $existingUser = $userRepository->findOneBy(['email' => $data['email']]);

            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json(
                    ['message' => 'Cet email est déjà utilisé'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $user->setEmail($data['email']);
        }

        if (isset($data['address'])) {
            $user->setAddress($data['address']);
        }

        if (isset($data['postalCode'])) {
            $user->setPostalCode($data['postalCode']);
        }

        if (isset($data['city'])) {
            $user->setCity($data['city']);
        }

        if (isset($data['newsletter'])) {
            $user->setNewsletter($data['newsletter']);
        }

        // Changement de mot de passe
        if (!empty($data['newPassword'])) {
            if ($user->getSocialAccounts()->count() > 0 && empty($user->getPassword())) {
                return $this->json(
                    ['message' => 'Impossible de changer le mot de passe d\'un compte OAuth'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!empty($data['currentPassword'])) {
                if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
                    return $this->json(
                        ['message' => 'Mot de passe actuel incorrect'],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['newPassword']);
            $user->setPassword($hashedPassword);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(
                ['message' => 'Erreurs de validation', 'errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->em->flush();
        $updatedUserData = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'gender' => $user->getGender()?->value,
            'birthDate' => $user->getBirthDate()?->format('Y-m-d'),
            'address' => $user->getAddress(),
            'postalCode' => $user->getPostalCode(),
            'city' => $user->getCity(),
            'newsletter' => $user->getNewsletter(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'hasOAuthAccounts' => $user->getSocialAccounts()->count() > 0
        ];

        $response = new JsonResponse([
            'message' => 'Profil mis à jour avec succès',
            'user' => $updatedUserData
        ]);

        // mettre à jour le cookie user_data
        $domain = $_ENV['SESSION_COOKIE_DOMAIN'] ?? null;
        $secure = ($_ENV['SESSION_COOKIE_SECURE'] ?? 'false') === 'true';

        $userDataCookie = Cookie::create('user_data')
            ->withValue(json_encode($updatedUserData))
            ->withExpires(time() + 900) // 15 minutes (comme le access_token)
            ->withPath('/')
            ->withDomain($domain)
            ->withSecure($secure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);

        $response->headers->setCookie($userDataCookie);

        return $response;
    }

    #[Route('/customers', name: 'api_customers_search', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function getCustomers(Request $request, UserRepository $userRepository): JsonResponse
    {
        $search = trim($request->query->get('search', ''));

        if(strlen($search) < 2){
            return $this->json([]);
        }

        $users = $userRepository->searchCustomers($search);

        return $this->json(array_map(fn(User $u) => [
            'id'        => $u->getId(),
            'firstName' => $u->getFirstName(),
            'lastName'  => $u->getLastName(),
            'email'     => $u->getEmail(),
        ], $users));
    }

    /**
     * Returne les gains uniquement pour ROLE_PARTICIPANT
     * @param User $user
     * @param CodeRepository $codeRepository
     * @return array
     */
    private function resolveGains(User $user, CodeRepository $codeRepository): array
    {
        if ($this->isGranted('ROLE_EMPLOYEE') || $this->isGranted('ROLE_ADMIN')) {
            return [];
        }
        return array_map(fn(Code $code) => [
            'id'          => $code->getId(),
            'code'        => $code->getCode(),
            'gainName'    => $code->getGain()?->getName(),
            'gainId'      => $code->getGain()?->getId(),
            'validatedOn' => $code->getValidatedOn()?->format('Y-m-d'),
            'isClaimed'   => $code->isClaimed(),
            'claimedOn'   => $code->getClaimedOn()?->format('Y-m-d'),
        ], $codeRepository->getValidatedCodes($user));
    }
}
