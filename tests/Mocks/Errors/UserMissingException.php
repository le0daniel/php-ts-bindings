<?php declare(strict_types=1);

namespace Tests\Mocks\Errors;

/**
 * A subclass of a configured exception: it matches because the presenter uses instanceof.
 */
final class UserMissingException extends RecordMissingException
{
}
