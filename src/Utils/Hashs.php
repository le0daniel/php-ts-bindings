<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final class Hashs
{

    public static function base64UrlEncodedSha256(string $message): string
    {
        $hash = hash('sha256', $message, true);
        return $hash
                |> base64_encode(...)
                |> (fn($x) => strtr($x, '+/', '-_'))
                |> (fn($x) => rtrim($x, '='));
    }

}