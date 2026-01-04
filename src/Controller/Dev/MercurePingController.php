<?php

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;

final class MercurePingController extends AbstractController
{
    #[Route('/_dev/mercure/ping', name: 'dev_mercure_ping', methods: ['GET'])]
    public function ping(HubInterface $hub): JsonResponse
    {
        $topic = '/test';

        $payload = [
            'msg' => 'pong',
            'at'  => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $hub->publish(new Update(
            $topic,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            false // public
        ));

        return $this->json(['ok' => true, 'published_to' => $topic, 'payload' => $payload]);
    }
}
