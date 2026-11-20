<?php

namespace App\Application\Game\Services;

use App\Models\Game;
use RuntimeException;

final class RoomCodeGenerator
{
    public function generate(): string
    {
        $alphabet = str_split((string) config('parchis.code_alphabet'));
        $length = (int) config('parchis.code_length');

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[array_rand($alphabet)];
            }

            if (! Game::where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('No se pudo generar un código de sala único.');
    }
}
