<?php

namespace App\Tests\Unit\State;

use App\Dto\CodeInput;
use App\Entity\Code;
use App\Entity\Gain;
use App\Entity\User;
use App\Repository\CodeRepository;
use App\State\CodeValidationProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\LimiterInterface;
use ApiPlatform\Metadata\Operation;

class CodeValidationProcessorTest extends TestCase
{
    private CodeRepository&MockObject $codeRepository;
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private RateLimiterFactory&MockObject $rateLimiterFactory;
    private CodeValidationProcessor $processor;

    protected function setUp(): void
    {
        $this->codeRepository   = $this->createMock(CodeRepository::class);
        $this->em               = $this->createMock(EntityManagerInterface::class);
        $this->security         = $this->createMock(Security::class);
        $this->rateLimiterFactory = $this->createMock(RateLimiterFactory::class);

        $this->processor = new CodeValidationProcessor(
            $this->codeRepository,
            $this->em,
            $this->security,
            $this->rateLimiterFactory,
        );
    }

    // --- Helpers ---

    private function makeOperation(): Operation
    {
        return $this->createMock(Operation::class);
    }

    private function makeInput(string $codeValue): CodeInput
    {
        $input = new CodeInput();
        $input->code = $codeValue;
        return $input;
    }

    /**
     * Configure le Security mock pour retourner un utilisateur connecté
     * et le RateLimiter pour accepter la requête.
     */
    private function withAuthenticatedUser(string $email = 'user@test.com'): User
    {
        $user = new User();
        $user->setEmail($email);

        $this->security->method('getUser')->willReturn($user);
        $this->allowRateLimit();

        return $user;
    }

    private function allowRateLimit(): void
    {
        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->method('isAccepted')->willReturn(true);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $this->rateLimiterFactory->method('create')->willReturn($limiter);
    }

    private function denyRateLimit(): void
    {
        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->method('isAccepted')->willReturn(false);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $this->rateLimiterFactory->method('create')->willReturn($limiter);
    }

    private function makeValidCode(string $value, User $winner = null): Code
    {
        $gain = new Gain();
        $gain->setName('Infuseur à thé');
        $gain->setProbability(60);
        $gain->setMaxQuantity(300000);
        $gain->setAllocatedQuantity(0);

        $code = new Code();
        $code->setCode($value);
        $code->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $code->setGain($gain);

        return $code;
    }

    // --- Tests ---

    /**
     * Throws access denied when user not authenticated
     * @return void
     */
    public function testThrowsAccessDeniedWhenUserNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->expectException(AccessDeniedHttpException::class);

        $this->processor->process(
            $this->makeInput('ABC1234567'),
            $this->makeOperation()
        );
    }

    /**
     * Throws too many requests when rate limit exceeded
     * @return void
     */
    public function testThrowsTooManyRequestsWhenRateLimitExceeded(): void
    {
        $user = new User();
        $user->setEmail('user@test.com');
        $this->security->method('getUser')->willReturn($user);
        $this->denyRateLimit();

        $this->expectException(TooManyRequestsHttpException::class);

        $this->processor->process(
            $this->makeInput('ABC1234567'),
            $this->makeOperation()
        );
    }

    /**
     * Throws bad request when code not found
     * @return void
     */
    public function testThrowsBadRequestWhenCodeNotFound(): void
    {
        $this->withAuthenticatedUser();
        $this->codeRepository->method('findOneBy')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Le code saisi est invalide ou a déjà été utilisé.');

        $this->processor->process(
            $this->makeInput('NOTEXIST01'),
            $this->makeOperation()
        );
    }

    /**
     * Throws bad request when code already validated
     * @return void
     */
    public function testThrowsBadRequestWhenCodeAlreadyValidated(): void
    {
        $user = $this->withAuthenticatedUser();

        $code = $this->makeValidCode('ABC1234567');
        $code->setIsValidated(true); // déjà utilisé

        $this->codeRepository->method('findOneBy')->willReturn($code);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Le code saisi est invalide ou a déjà été utilisé.');

        $this->processor->process(
            $this->makeInput('ABC1234567'),
            $this->makeOperation()
        );
    }

    /**
     * Throws bad request when code expired
     * @return void
     */
    public function testThrowsBadRequestWhenCodeExpired(): void
    {
        $this->withAuthenticatedUser();

        $code = $this->makeValidCode('ABC1234567');
        $code->setExpiresAt(new \DateTimeImmutable('-1 day')); // expiré

        $this->codeRepository->method('findOneBy')->willReturn($code);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Le code saisi est invalide ou a déjà été utilisé.');

        $this->processor->process(
            $this->makeInput('ABC1234567'),
            $this->makeOperation()
        );
    }

    /**
     * Successful validation assigns winner and increments gain
     * @return void
     */
    public function testSuccessfulValidationAssignsWinnerAndIncrementsGain(): void
    {
        $user = $this->withAuthenticatedUser('winner@test.com');
        $code = $this->makeValidCode('ABC1234567');
        $gain = $code->getGain();

        $this->codeRepository->method('findOneBy')->willReturn($code);
        $this->em->expects($this->once())->method('flush');

        $result = $this->processor->process(
            $this->makeInput('ABC1234567'),
            $this->makeOperation()
        );

        $this->assertSame($code, $result);
        $this->assertTrue($result->isValidated());
        $this->assertSame($user, $result->getWinner());
        $this->assertNotNull($result->getValidatedOn());
        $this->assertSame(1, $gain->getAllocatedQuantity());
    }
}
