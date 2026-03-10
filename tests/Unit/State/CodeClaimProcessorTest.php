<?php

namespace App\Tests\Unit\State;

use App\Dto\ClaimInput;
use App\Entity\Code;
use App\Entity\Gain;
use App\Repository\CodeRepository;
use App\State\CodeClaimProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ApiPlatform\Metadata\Operation;

class CodeClaimProcessorTest extends TestCase
{
    private CodeRepository&MockObject $codeRepository;
    private EntityManagerInterface&MockObject $em;
    private CodeClaimProcessor $processor;

    protected function setUp(): void
    {
        $this->codeRepository = $this->createMock(CodeRepository::class);
        $this->em             = $this->createMock(EntityManagerInterface::class);

        $this->processor = new CodeClaimProcessor(
            $this->codeRepository,
            $this->em,
        );
    }

    // --- Helpers ---

    private function makeOperation(): Operation
    {
        return $this->createMock(Operation::class);
    }

    private function makeInput(string $codeValue): ClaimInput
    {
        $input = new ClaimInput();
        $input->code = $codeValue;
        return $input;
    }

    private function makeCode(string $value): Code
    {
        $gain = new Gain();
        $gain->setName('Coffret découverte 39€');
        $gain->setProbability(6);
        $gain->setMaxQuantity(30000);

        $code = new Code();
        $code->setCode($value);
        $code->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $code->setGain($gain);

        return $code;
    }

    // --- Tests ---

    /**
     * Throws not found when code does not exist
     * @return void
     */
    public function testThrowsNotFoundWhenCodeDoesNotExist(): void
    {
        $this->codeRepository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Code introuvable.');

        $this->processor->process(
            $this->makeInput('NOTEXIST01'),
            $this->makeOperation()
        );
    }

    /**
     * Throws bad request when code not yet validated by customer
     * @return void
     */
    public function testThrowsBadRequestWhenCodeNotYetValidatedByCustomer(): void
    {
        $code = $this->makeCode('ABC1234567');
        // isValidated = false par défaut dans le constructeur de Code

        $this->codeRepository->method('findOneBy')->willReturn($code);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage("Ce code n'a pas encore été activé par le client sur le site.");

        $this->processor->process(
            $this->makeInput('ABC1234567'),
            $this->makeOperation()
        );
    }

    /**
     * Throws bad request with gain name and date when code already claimed
     * @return void
     */
    public function testThrowsBadRequestWithGainNameAndDateWhenCodeAlreadyClaimed(): void
    {
        $code = $this->makeCode('ABC1234567');
        $code->setIsValidated(true);
        $code->setIsClaimed(true); // déjà remis → claimedOn est renseigné automatiquement

        $this->codeRepository->method('findOneBy')->willReturn($code);

        $this->expectException(BadRequestHttpException::class);
        // Le message contient le nom du gain
        $this->expectExceptionMessageMatches('/Coffret découverte 39€/');

        $this->processor->process(
            $this->makeInput('ABC1234567'),
            $this->makeOperation()
        );
    }

    /**
     * Successful claim sets is claimed and claimed on date
     * @return void
     */
    public function testSuccessfulClaimSetsIsClaimedAndClaimedOnDate(): void
    {
        $code = $this->makeCode('ABC1234567');
        $code->setIsValidated(true);

        $this->codeRepository->method('findOneBy')->willReturn($code);
        $this->em->expects($this->once())->method('flush');

        $result = $this->processor->process(
            $this->makeInput('ABC1234567'),
            $this->makeOperation()
        );

        $this->assertSame($code, $result);
        $this->assertTrue($result->isClaimed());
        $this->assertNotNull($result->getClaimedOn());
        // La date de remise doit être très proche de maintenant
        $this->assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp(),
            $result->getClaimedOn()->getTimestamp(),
            5
        );
    }
}
