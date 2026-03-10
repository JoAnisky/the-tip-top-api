<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Helpers partagés entre les tests fonctionnels de l'API.
 *
 * À utiliser dans les classes qui étendent WebTestCase.
 */
trait ApiTestTrait
{
    /**
     * Effectue un POST /api/login et retourne le token JWT.
     * Lance une assertion si le login échoue.
     */
    protected function getJwtToken(KernelBrowser $client, string $email, string $password): string
    {
        $client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data, 'La réponse de login doit contenir un token JWT.');

        return $data['token'];
    }

    /**
     * Effectue une requête JSON authentifiée avec un token Bearer.
     */
    protected function requestWithToken(
        KernelBrowser $client,
        string $method,
        string $uri,
        string $token,
        array $body = [],
        string $contentType = 'application/json'
    ): void {
        $client->request(
            $method,
            $uri,
            [],
            [],
            [
                'CONTENT_TYPE'  => $contentType,
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            $body ? json_encode($body) : null
        );
    }
}
