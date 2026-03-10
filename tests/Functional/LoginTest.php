<?php

namespace App\Tests\Functional;

use App\Tests\Fixtures\TestFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LoginTest extends WebTestCase
{
    use ApiTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // createClient() boot le kernel — on le fait UNE seule fois ici
        $this->client = static::createClient();
        $container    = static::getContainer();

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

    /**
     * Login with valid credentials returns jwt token
     * @return void
     */
    public function testLoginWithValidCredentialsReturnsJwtToken(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'    => TestFixtures::USER_EMAIL,
                'password' => TestFixtures::USER_PASSWORD,
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $data['token']);
    }

    /**
     * Login with wrong password returns 401
     * @return void
     */
    public function testLoginWithWrongPasswordReturns401(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'    => TestFixtures::USER_EMAIL,
                'password' => 'mauvais_mot_de_passe',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Login with unknown email returns 401
     * @return void
     */
    public function testLoginWithUnknownEmailReturns401(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'    => 'inexistant@thetiptop.fr',
                'password' => TestFixtures::USER_PASSWORD,
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Login with missing password returns bad request
     * @return void
     */
    public function testLoginWithMissingPasswordReturnsBadRequest(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => TestFixtures::USER_EMAIL])
        );

        $this->assertResponseStatusCodeSame(400);
    }
}
