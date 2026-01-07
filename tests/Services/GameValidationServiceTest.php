<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\Step;
use App\Entity\Team;
use App\Entity\TeamQrSequence;
use App\Services\GameValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class GameValidationServiceTest extends TestCase
{
    public function testQrSequenceRejectsOutOfOrderScan(): void
    {
        $team = new Team();
        $step = new Step();
        $step->setType('E');
        $step->setLetter('Z');

        $sequence1 = new TeamQrSequence();
        $sequence1->setTeam($team);
        $sequence1->setOrderNumber(1);
        $sequence1->setQrCode('CODE1');
        $sequence1->setHint('Hint1');

        $sequence2 = new TeamQrSequence();
        $sequence2->setTeam($team);
        $sequence2->setOrderNumber(2);
        $sequence2->setQrCode('CODE2');
        $sequence2->setHint('Hint2');

        $qrRepository = $this->createMock(ObjectRepository::class);
        $qrRepository->method('findBy')->willReturn([$sequence1, $sequence2]);

        $progressRepository = $this->createMock(ObjectRepository::class);
        $progressRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [TeamQrSequence::class, $qrRepository],
            [\App\Entity\TeamStepProgress::class, $progressRepository],
        ]);

        $validator = new GameValidationService($entityManager);
        $result = $validator->validateStep($team, $step, ['code' => 'CODE2']);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('QR1', (string) $result['message']);
    }

    public function testQrSequenceReturnsHintOnValidScan(): void
    {
        $team = new Team();
        $step = new Step();
        $step->setType('E');
        $step->setLetter('Z');

        $sequence1 = new TeamQrSequence();
        $sequence1->setTeam($team);
        $sequence1->setOrderNumber(1);
        $sequence1->setQrCode('CODE1');
        $sequence1->setHint('Hint1');

        $qrRepository = $this->createMock(ObjectRepository::class);
        $qrRepository->method('findBy')->willReturn([$sequence1]);

        $progressRepository = $this->createMock(ObjectRepository::class);
        $progressRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [TeamQrSequence::class, $qrRepository],
            [\App\Entity\TeamStepProgress::class, $progressRepository],
        ]);
        $entityManager->method('persist');

        $validator = new GameValidationService($entityManager);
        $result = $validator->validateStep($team, $step, ['code' => 'CODE1']);

        self::assertTrue($result['valid']);
        self::assertSame('Hint1', $result['message']);
    }
}