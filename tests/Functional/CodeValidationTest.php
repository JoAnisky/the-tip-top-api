<?php

namespace App\Tests\Functional;

use App\Tests\Fixtures\TestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de l'endpoint POST /api/codes/validate.
 *
 * Scénarios couverts :
 *  - Requête sans token → 401
 *  - Code valide + token valide → 200 avec gain dans la réponse
 *  - Code déjà validé → 400 avec message générique (pas d'énumération)
 *  - Code expiré → 400
 *  - Code inexistant → 400 (même message — sécurité : pas d'énumération)
 */
class CodeValidationTest extends WebTestCase
{
    use ApiTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client    = static::createClient();
        $container = static::getContainer();

        $em         = $container->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $metadata   = $em->getMetadataFactory()->getAllMetadata();

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $loader   = new Loader();
        $executor = new ORMExecutor($em);

        $fixture = $container->get(TestFixtures::class);
        $loader->addFixture($fixture);
        $executor->execute($loader->getFixtures(), true);
    }

    public function testValidateCodeWithoutTokenReturns401(): void
    {

        $this->client->request(
            'POST',
            '/api/codes/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['code' => TestFixtures::VALID_CODE])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testValidateValidCodeReturns201WithGain(): void
    {
        $client = $this->client;
        $token  = $this->getJwtToken($client, TestFixtures::USER_EMAIL, TestFixtures::USER_PASSWORD);

        $this->requestWithToken(
            $client,
            'POST',
            '/api/codes/validate',
            $token,
            ['code' => TestFixtures::VALID_CODE],
            'application/ld+json'
        );

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);

        // La réponse doit contenir les champs normalisés du groupe code:read
        $this->assertArrayHasKey('code', $data);
        $this->assertSame(TestFixtures::VALID_CODE, $data['code']);
        $this->assertNotNull($data['validatedOn']);

        // Le gain doit être présent et avoir un nom
        $this->assertArrayHasKey('gain', $data);
        $this->assertArrayHasKey('name', $data['gain']);
        $this->assertNotEmpty($data['gain']['name']);
    }

    public function testValidateAlreadyValidatedCodeReturns400(): void
    {
        $client = $this->client;
        $token  = $this->getJwtToken($client, TestFixtures::USER_EMAIL, TestFixtures::USER_PASSWORD);

        $this->requestWithToken(
            $client,
            'POST',
            '/api/codes/validate',
            $token,
            ['code' => TestFixtures::ALREADY_VALIDATED_CODE],
            'application/ld+json'
        );

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        // Message générique — ne révèle pas si le code existe ou non
        $this->assertStringContainsString(
            'invalide ou a déjà été utilisé',
            $data['detail'] ?? $data['message'] ?? ''
        );
    }

    public function testValidateExpiredCodeReturns400(): void
    {
        $client = $this->client;
        $token  = $this->getJwtToken($client, TestFixtures::USER_EMAIL, TestFixtures::USER_PASSWORD);

        $this->requestWithToken(
            $client,
            'POST',
            '/api/codes/validate',
            $token,
            ['code' => TestFixtures::EXPIRED_CODE],
            'application/ld+json'
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testValidateNonExistentCodeReturns400WithSameMessageAsUsedCode(): void
    {
        $client = $this->client;
        $token  = $this->getJwtToken($client, TestFixtures::USER_EMAIL, TestFixtures::USER_PASSWORD);

        $this->requestWithToken(
            $client,
            'POST',
            '/api/codes/validate',
            $token,
            ['code' => 'NOTEXIST01'],
            'application/ld+json'
        );

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        // Même message que pour un code déjà utilisé → pas d'énumération possible
        $this->assertStringContainsString(
            'invalide ou a déjà été utilisé',
            $data['detail'] ?? $data['message'] ?? ''
        );
    }
}
