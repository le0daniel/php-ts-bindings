<?php

declare(strict_types=1);

namespace Tests\Unit\Reflection\Mocks;

/**
 * Every property shape PropertiesReflector must classify. Write-only virtual properties cannot
 * combine with asymmetric visibility (a PHP compile error), so no property can be both unwritable
 * and unreadable from public scope.
 */
final class PropertyVisibilityShowcase
{
    public string $plain;

    protected string $protectedProp;

    private string $privateProp;

    public readonly string $readonlyProp;

    public private(set) string $privateSet = 'a';

    public protected(set) string $protectedSet = 'b';

    public string $virtualGetOnly {
        get => $this->plain;
    }

    public string $virtualSetOnly {
        set {
            $this->plain = $value;
        }
    }

    public string $virtualGetSet {
        get => $this->plain;
        set {
            $this->plain = $value;
        }
    }

    public string $backedWithSetHook {
        set => trim($value);
    }

    public function __construct()
    {
        $this->readonlyProp = 'readonly';
        $this->privateProp = 'private';
        $this->protectedProp = 'protected';
    }
}
