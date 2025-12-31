<?php

namespace App\Services;

class QrCodeService
{
    private const DEFAULT_SIZE = 360;
    private const DEFAULT_MARGIN = 12;

    public function getQrImageUrl(string $data, int $size = self::DEFAULT_SIZE, int $margin = self::DEFAULT_MARGIN): string
    {
        $encoded = urlencode($data);

        return sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%dx%d&margin=%d&data=%s',
            $size,
            $size,
            $margin,
            $encoded
        );
    }
}