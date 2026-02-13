<?php

namespace App\Controller;

use App\Entity\SocialAccount;
use App\Entity\User;
use App\Repository\SocialAccountRepository;
use App\Service\TokenService as JWTService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/auth/oauth')]
class OAuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SocialAccountRepository $socialAccountRepo,
        private JWTService $jwtService,
        private UserPasswordHasherInterface $passwordHasher,
        private string $cookieDomain,
        private bool $cookieSecure,
        private string $frontendUrl
    ) {}

    #[Route('/google', name: 'auth_oauth_google')]
    public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile'], []);
    }

    #[Route('/google/callback', name: 'auth_oauth_google_callback')]
    public function connectGoogleCheck(ClientRegistry $clientRegistry): Response
    {
        try {
            $client = $clientRegistry->getClient('google');
            $googleUser = $client->fetchUser();

            $email = $googleUser->getEmail();
            $firstName = $googleUser->getFirstName();
            $lastName = $googleUser->getLastName();
            $googleId = $googleUser->getId();

            $user = $this->findOrCreateUserWithSocialAccount(
                provider: 'google',
                providerId: $googleId,
                providerEmail: $email,
                firstName: $firstName,
                lastName: $lastName
            );

            return $this->createAuthResponse($user);

        } catch (\Exception $e) {
            return $this->redirectToFrontendWithError($e->getMessage());
        }
    }

    #[Route('/facebook', name: 'auth_oauth_facebook')]
    public function connectFacebook(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('facebook')
            ->redirect(['email', 'public_profile'], []);
    }

    #[Route('/facebook/callback', name: 'auth_oauth_facebook_callback')]
    public function connectFacebookCheck(ClientRegistry $clientRegistry): Response
    {
        try {
            $client = $clientRegistry->getClient('facebook');
            $facebookUser = $client->fetchUser();

            $email = $facebookUser->getEmail();
            $name = $facebookUser->getName();
            $facebookId = $facebookUser->getId();

            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';

            $user = $this->findOrCreateUserWithSocialAccount(
                provider: 'facebook',
                providerId: $facebookId,
                providerEmail: $email,
                firstName: $firstName,
                lastName: $lastName
            );

            return $this->createAuthResponse($user);

        } catch (\Exception $e) {
            return $this->redirectToFrontendWithError($e->getMessage());
        }
    }

    /**
     * Logique de recherche/création de user avec compte social
     * @throws RandomException
     */
    private function findOrCreateUserWithSocialAccount(
        string $provider,
        string $providerId,
        string $providerEmail,
        string $firstName,
        string $lastName
    ): User {
        // 1. Cherche d'abord par compte social existant
        $socialAccount = $this->socialAccountRepo->findByProviderAndId($provider, $providerId);

        if ($socialAccount) {
            // Compte social trouvé, mise à jour de lastUsedAt
            $socialAccount->updateLastUsed();
            $this->em->flush();
            return $socialAccount->getUser();
        }

        // 2. Cherche un user existant par email
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $providerEmail]);

        if ($user) {
            // User existe, on lui ajoute le compte social
            $socialAccount = $this->createSocialAccount($provider, $providerId, $providerEmail);
            $user->addSocialAccount($socialAccount);
            $this->em->persist($socialAccount);
            $this->em->flush();
            return $user;
        }

        // 3. Crée un nouveau user avec son compte social
        $user = new User();
        $user->setEmail($providerEmail);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        // Mot de passe aléatoire (non utilisé pour OAuth)
        $randomPassword = bin2hex(random_bytes(32));
        $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true); // Email vérifié par OAuth provider

        // Crée et lie le compte social
        $socialAccount = $this->createSocialAccount($provider, $providerId, $providerEmail);
        $user->addSocialAccount($socialAccount);

        $this->em->persist($user);
        $this->em->persist($socialAccount);
        $this->em->flush();

        return $user;
    }

    private function createSocialAccount(
        string $provider,
        string $providerId,
        string $providerEmail
    ): SocialAccount {
        $socialAccount = new SocialAccount();
        $socialAccount->setProviderName($provider);
        $socialAccount->setProviderId($providerId);
        $socialAccount->setProviderEmail($providerEmail);
        $socialAccount->updateLastUsed();

        return $socialAccount;
    }

    private function createAuthResponse(User $user): Response
    {
        $accessToken = $this->jwtService->createAccessToken($user);
        $refreshTokenEntity = $this->jwtService->createRefreshToken($user);
        $refreshToken = $refreshTokenEntity->getToken();

        $userData = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ];

        $response = new RedirectResponse($this->frontendUrl . '/');

        // Helper pour créer les cookies
        $this->addAuthCookie($response, 'access_token', $accessToken, 900);
        $this->addAuthCookie($response, 'refresh_token', $refreshToken, 2592000);
        $this->addAuthCookie($response, 'user_data', json_encode($userData), 900, false); // httpOnly = false

        return $response;
    }

    private function redirectToFrontendWithError(string $message): RedirectResponse
    {
        return new RedirectResponse(
            $this->frontendUrl . '/login?error=' . urlencode('Erreur OAuth: ' . $message)
        );
    }

    private function addAuthCookie(
        Response $response,
        string $name,
        string $value,
        int $lifetime,
        bool $httpOnly = true
    ): void
    {
        $response->headers->setCookie(
            new Cookie(
                name: $name,
                value: $value,
                expire: time() + $lifetime,
                path: '/',
                domain: $this->cookieDomain,
                secure: $this->cookieSecure,
                httpOnly: $httpOnly,
                sameSite: 'lax'
            )
        );
    }
}
