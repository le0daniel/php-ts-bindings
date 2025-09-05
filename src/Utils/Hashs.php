<?php declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Utils;

final class Hashs
{

    public static function base64UrlEncodedSha256(string $message): string
    {
        $hash = hash('sha256', $message, true);
        $encoded = base64_encode($hash);
        return rtrim(strtr(base64_encode($encoded), '+/', '-_'), '=');
    }

}