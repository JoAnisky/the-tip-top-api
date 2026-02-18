<?php

namespace App\Security;

use App\Service\TokenService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private TokenService $tokenService,
        private int $jwtTtl,
        private int $refreshTokenTtl
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        $accessToken = $this->tokenService->createAccessToken($user);
        $refreshToken = $this->tokenService->createRefreshToken($user);

        $response = new JsonResponse([
            'success' => true,
        ]);

        $response->headers->setCookie(
            Cookie::create('access_token')
                ->withValue($accessToken)
                ->withExpires(time() + $this->jwtTtl)  // 15 minutes
                ->withPath('/')
                ->withDomain($_ENV['SESSION_COOKIE_DOMAIN'] ?? null)
                ->withSecure($_ENV['SESSION_COOKIE_SECURE'] === 'true')
                ->withHttpOnly()  // protection XSS
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        $response->headers->setCookie(
            Cookie::create('refresh_token')
                ->withValue($refreshToken->getToken())
                ->withExpires(time() + $this->refreshTokenTtl)  // 30 jours
                ->withPath('/')
                ->withDomain($_ENV['SESSION_COOKIE_DOMAIN'] ?? null)
                ->withSecure($_ENV['SESSION_COOKIE_SECURE'] === 'true')
                ->withHttpOnly()
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        return $response;
    }
}
