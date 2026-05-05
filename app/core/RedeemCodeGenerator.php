<?php
declare(strict_types=1);

namespace Core;

final class RedeemCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(int $length = 16, int $groupSize = 4): string
    {
        $length = max(8, min(64, $length));
        $groupSize = max(2, min(8, $groupSize));

        $alphabetLength = strlen(self::ALPHABET);
        $raw = '';
        while (strlen($raw) < $length) {
            $bytes = random_bytes($length);
            for ($i = 0; $i < strlen($bytes) && strlen($raw) < $length; $i++) {
                $index = ord($bytes[$i]) % $alphabetLength;
                $raw .= self::ALPHABET[$index];
            }
        }

        $segments = str_split(substr($raw, 0, $length), $groupSize);
        return implode('-', $segments);
    }
}
