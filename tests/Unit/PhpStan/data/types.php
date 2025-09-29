<?php declare(strict_types=1);

use function PHPStan\Testing\assertType;

/**
 * @param Pick<object{id: string, name: string, other: string}, 'id'|'name'> $s
 * @return void
 */
function pick(object $s) {
    assertType("object{id: string, name: string}", $s);
}

/**
 * @param Pick<object{id: string, name?: string, other: string}, 'id'|'name'> $s
 * @return void
 */
function pickWithOptional(object $s) {
    assertType("object{id: string, name?: string}", $s);
}

/**
 * @param Pick<array{id: string, name: string, other: string}, 'id'|'name'> $s
 * @return void
 */
function pickArray(array $s) {
    assertType("array{id: string, name: string}", $s);
}

/**
 * @param Pick<array{id: string, name?: string, other: string}, 'id'|'name'> $s
 * @return void
 */
function pickArrayWithOptional(array $s) {
    assertType("array{id: string, name?: string}", $s);
}

/**
 * @param Pick<\Tests\Unit\PhpStan\Mocks\MyTestClass, 'id'|'name'> $s
 * @return void
 */
function pickObject(object $s) {
    assertType("object{id: string, name: string}", $s);
}

/**
 * @param Omit<object{id: string, name: string, other: string}, 'id'|'name'> $s
 * @return void
 */
function omit(object $s) {
    assertType("object{other: string}", $s);
}

/**
 * @param Omit<array{id: string, name: string, other: string}, 'id'|'name'> $s
 * @return void
 */
function omitArray($s) {
    assertType("array{other: string}", $s);
}

/**
 * @param Omit<array{id: string, name: string, other?: string}, 'id'|'name'> $s
 * @return void
 */
function omitArrayWithOptional($s) {
    assertType("array{other?: string}", $s);
}

/**
 * @param Omit<\Tests\Unit\PhpStan\Mocks\MyTestClass, 'id'|'name'> $s
 * @return void
 */
function omitObject(object $s) {
    assertType("object{other: string}", $s);
}