<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Team;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TeamPinService
{
    private const PIN_TTL_SECONDS = 60;

    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * @return array{pin:string,expires_at:int}
     */
    public function getCurrentPinPayload(Team $team): array
    {
        $cacheKey = sprintf('team_pin_%d', $team->getId());

        return $this->cache->get($cacheKey, function (ItemInterface $item): array {
            $item->expiresAfter(self::PIN_TTL_SECONDS);
            $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', self::PIN_TTL_SECONDS));

            return [
                'pin' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'expires_at' => $expiresAt->getTimestamp(),
            ];
        });
    }

    public function isPinValid(Team $team, string $pin): bool
    {
        $payload = $this->getCurrentPinPayload($team);

        return hash_equals($payload['pin'], trim($pin));
    }
}