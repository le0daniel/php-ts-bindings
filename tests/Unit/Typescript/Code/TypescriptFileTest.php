<?php declare(strict_types=1);

use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptFile;
use Le0daniel\PhpTsBindings\Typescript\Code\TypescriptImport;

test('renders an empty file as an empty string', function () {
    expect(new TypescriptFile()->toString())->toBe('');
});

test('renders code with no imports', function () {
    expect(new TypescriptFile('export type A = 1;')->toString())->toBe("export type A = 1;\n");
});

test('always ends with exactly one newline', function (string $code) {
    expect(new TypescriptFile($code)->toString())->toBe("const a = 1;\n");
})->with([
    'none' => ['const a = 1;'],
    'one' => ["const a = 1;\n"],
    'several' => ["const a = 1;\n\n\n"],
    'leading' => ["\n\nconst a = 1;"],
]);

test('separates the imports from the code with one blank line', function () {
    $file = new TypescriptFile('const a = queryKey();', [
        TypescriptImport::values('./lib/utils', 'queryKey'),
    ]);

    expect($file->toString())->toBe(
        "import {queryKey} from './lib/utils';\n\nconst a = queryKey();\n"
    );
});

test('renders imports alone when there is no code', function () {
    $file = new TypescriptFile('', [TypescriptImport::types('./lib/types', 'Brand')]);

    expect($file->toString())->toBe("import type {Brand} from './lib/types';\n");
});

test('splits a module into a type line and a value line, type first', function () {
    $file = new TypescriptFile('', [
        new TypescriptImport('./lib/types', values: ['isBrand'], types: ['Brand']),
    ]);

    expect($file->toString())->toBe(
        "import type {Brand} from './lib/types';\n"
        . "import {isBrand} from './lib/types';\n"
    );
});

test('renders only the line it has names for', function () {
    $values = new TypescriptFile('', [TypescriptImport::values('./lib/utils', 'queryKey')]);
    $types = new TypescriptFile('', [TypescriptImport::types('./lib/types', 'Brand')]);

    expect($values->toString())->toBe("import {queryKey} from './lib/utils';\n")
        ->and($types->toString())->toBe("import type {Brand} from './lib/types';\n");
});

test('quotes module specifiers with single quotes', function () {
    // Syntax::stringLiteral() is json_encode and would produce double quotes here.
    expect(new TypescriptFile('', [TypescriptImport::types('./lib/types', 'Brand')])->toString())
        ->toContain("from './lib/types';")
        ->not->toContain('"./lib/types"');
});

test('separates names with a comma and a space and does not pad the braces', function () {
    $file = new TypescriptFile('', [TypescriptImport::types('./lib/types', ['Brand', 'Order'])]);

    expect($file->toString())->toBe("import type {Brand, Order} from './lib/types';\n");
});

test('sorts the names inside each line', function () {
    $file = new TypescriptFile('', [
        TypescriptImport::types('./lib/types', ['OrderStatus', 'Brand', 'Customer']),
    ]);

    expect($file->toString())->toBe("import type {Brand, Customer, OrderStatus} from './lib/types';\n");
});

test('sorts modules by specifier', function () {
    $file = new TypescriptFile('', [
        TypescriptImport::values('@tanstack/react-query', 'useQuery'),
        TypescriptImport::values('./lib/utils', 'queryKey'),
        TypescriptImport::types('./lib/types', 'Brand'),
    ]);

    expect($file->toString())->toBe(
        "import type {Brand} from './lib/types';\n"
        . "import {queryKey} from './lib/utils';\n"
        . "import {useQuery} from '@tanstack/react-query';\n"
    );
});

test('merges two imports of the same module given to the constructor', function () {
    $file = new TypescriptFile('', [
        TypescriptImport::types('./lib/types', 'Order'),
        TypescriptImport::types('./lib/types', 'Brand'),
    ]);

    expect($file->imports)->toHaveCount(1)
        ->and($file->toString())->toBe("import type {Brand, Order} from './lib/types';\n");
});

test('test mixed import', function () {
    $file = new TypescriptFile('', [
        TypescriptImport::mixed('./lib/types', [
            ' type Order',
            'type Brand ',
            ' SomeValue'
        ]),
    ]);

    expect($file->imports)->toHaveCount(1)
        ->and($file->toString())->toBe("import type {Brand, Order} from './lib/types';\nimport {SomeValue} from './lib/types';\n");
});

test('merges constructor imports with imports added later', function () {
    $file = new TypescriptFile('', [TypescriptImport::types('./lib/types', 'Order')])
        ->withImports(TypescriptImport::types('./lib/types', 'Brand'));

    expect($file->imports)->toHaveCount(1)
        ->and($file->toString())->toBe("import type {Brand, Order} from './lib/types';\n");
});

test('drops an import that names nothing', function () {
    $file = new TypescriptFile('const a = 1;', [
        new TypescriptImport('./lib/types'),
        TypescriptImport::values('./lib/utils', 'queryKey'),
    ]);

    expect($file->imports)->toHaveCount(1)
        ->and($file->toString())->toBe("import {queryKey} from './lib/utils';\n\nconst a = 1;\n");
});

test('never emits a name as both a type and a value import of one module', function () {
    $file = new TypescriptFile('', [
        TypescriptImport::types('./lib/types', ['Status', 'Order']),
        TypescriptImport::values('./lib/types', 'Status'),
    ]);

    expect($file->toString())->toBe(
        "import type {Order} from './lib/types';\n"
        . "import {Status} from './lib/types';\n"
    );
});

test('resolves every module specifier and merges what collapses onto one module', function () {
    $file = new TypescriptFile('const a = 1;', [
        TypescriptImport::types('./lib/types', 'Order'),
        TypescriptImport::values('./types', 'isOrder'),
        TypescriptImport::values('@tanstack/react-query', 'useQuery'),
    ])->withModulesResolvedBy(
        fn(string $from): string => str_starts_with($from, './lib/') ? './' . substr($from, 6) : $from,
    );

    // The two specifiers name one module once resolved, so they render as one import and not as a
    // duplicated line the resolver would otherwise have introduced.
    expect($file->imports)->toHaveCount(2)
        ->and($file->toString())->toBe(
            "import type {Order} from './types';\n"
            . "import {isOrder} from './types';\n"
            . "import {useQuery} from '@tanstack/react-query';\n"
            . "\nconst a = 1;\n"
        );
});

test('withModulesResolvedBy returns a new file and leaves the original untouched', function () {
    $original = new TypescriptFile('const a = 1;', [TypescriptImport::types('./lib/types', 'Brand')]);

    $resolved = $original->withModulesResolvedBy(fn(string $from): string => './types');

    expect($resolved)->not->toBe($original)
        ->and($original->imports[0]->from)->toBe('./lib/types')
        ->and($resolved->imports[0]->from)->toBe('./types')
        ->and($resolved->imports[0]->types)->toBe(['Brand'])
        ->and($resolved->code)->toBe('const a = 1;');
});

test('renders the same bytes whatever order the imports arrived in', function () {
    $imports = [
        TypescriptImport::values('@tanstack/react-query', 'useQuery'),
        TypescriptImport::types('./lib/types', 'Brand'),
        TypescriptImport::values('./lib/utils', 'queryKey'),
        TypescriptImport::types('./lib/types', 'Order'),
    ];

    expect(new TypescriptFile('const a = 1;', $imports)->toString())
        ->toBe(new TypescriptFile('const a = 1;', array_reverse($imports))->toString());
});

test('appending a string keeps the existing imports', function () {
    $file = new TypescriptFile('const a = 1;', [TypescriptImport::types('./lib/types', 'Brand')])
        ->append('const b = 2;');

    expect($file->imports)->toHaveCount(1)
        ->and($file->toString())->toBe(
            "import type {Brand} from './lib/types';\n\nconst a = 1;\n\nconst b = 2;\n"
        );
});

test('appending a file merges its imports', function () {
    $file = new TypescriptFile('const a = 1;', [TypescriptImport::types('./lib/types', 'Order')])
        ->append(new TypescriptFile('const b = 2;', [
            TypescriptImport::types('./lib/types', 'Brand'),
            TypescriptImport::values('./lib/utils', 'queryKey'),
        ]));

    expect($file->toString())->toBe(
        "import type {Brand, Order} from './lib/types';\n"
        . "import {queryKey} from './lib/utils';\n"
        . "\nconst a = 1;\n\nconst b = 2;\n"
    );
});

test('appending a file with no imports leaves the imports alone', function () {
    $file = new TypescriptFile('const a = 1;', [TypescriptImport::types('./lib/types', 'Brand')])
        ->append(new TypescriptFile('const b = 2;'));

    expect($file->imports)->toHaveCount(1)
        ->and($file->imports[0]->types)->toBe(['Brand']);
});

test('separates appended blocks with a blank line', function () {
    $file = new TypescriptFile('export type A = 1;')
        ->append('export type B = 2;')
        ->append('export type C = 3;');

    expect($file->toString())->toBe("export type A = 1;\n\nexport type B = 2;\n\nexport type C = 3;\n");
});

test('strips the newlines around an appended block', function () {
    $file = new TypescriptFile('export type A = 1;')->append("\n\nexport type B = 2;\n\n");

    expect($file->toString())->toBe("export type A = 1;\n\nexport type B = 2;\n");
});

test('keeps the indentation of an appended block', function () {
    $file = new TypescriptFile('function a() {')->append("\n    return 1;\n");

    expect($file->toString())->toBe("function a() {\n\n    return 1;\n");
});

test('appending nothing is a no-op', function (string $code) {
    $file = new TypescriptFile('const a = 1;');

    expect($file->append($code)->toString())->toBe("const a = 1;\n");
})->with([
    'empty string' => [''],
    'newlines' => ["\n\n"],
    'whitespace' => ["  \n "],
]);

test('appending to an empty file does not start it with a blank line', function () {
    expect(new TypescriptFile()->append('const a = 1;')->toString())->toBe("const a = 1;\n")
        ->and(new TypescriptFile()->append(new TypescriptFile('const a = 1;'))->toString())
        ->toBe("const a = 1;\n");
});

test('constructing with code is the same as appending it to an empty file', function (string $code) {
    expect(new TypescriptFile($code)->toString())->toBe(new TypescriptFile()->append($code)->toString());
})->with([
    'plain' => ['const a = 1;'],
    'padded with newlines' => ["\nconst a = 1;\n\n"],
    'indented' => ["    const a = 1;"],
    'empty' => [''],
]);

test('append returns a new file and leaves the original untouched', function () {
    $original = new TypescriptFile('const a = 1;', [TypescriptImport::types('./lib/types', 'Brand')]);

    $appended = $original->append(new TypescriptFile('const b = 2;', [
        TypescriptImport::values('./lib/utils', 'queryKey'),
    ]));

    expect($appended)->not->toBe($original)
        ->and($original->code)->toBe('const a = 1;')
        ->and($original->imports)->toHaveCount(1)
        ->and($appended->imports)->toHaveCount(2);
});

test('withImports returns a new file and leaves the original untouched', function () {
    $original = new TypescriptFile('const a = 1;');

    $withImports = $original->withImports(TypescriptImport::types('./lib/types', 'Brand'));

    expect($withImports)->not->toBe($original)
        ->and($original->imports)->toBe([])
        ->and($withImports->imports)->toHaveCount(1)
        ->and($withImports->code)->toBe('const a = 1;');
});

test('renders a full file: imports, a blank line, then every block', function () {
    $file = new TypescriptFile('export type Id = number;', [
        TypescriptImport::values('./lib/utils', 'queryKey'),
        TypescriptImport::types('./lib/types', ['Order', 'Brand']),
    ])->append(new TypescriptFile(
        <<<TypeScript

        export function get(input: Id) {
            return queryKey('orders', 'get', input);
        }

        TypeScript,
        [TypescriptImport::types('./lib/types', 'OrderStatus')],
    ));

    expect($file->toString())->toBe(<<<TypeScript
    import type {Brand, Order, OrderStatus} from './lib/types';
    import {queryKey} from './lib/utils';

    export type Id = number;

    export function get(input: Id) {
        return queryKey('orders', 'get', input);
    }

    TypeScript);
});

test('casts to a string', function () {
    $file = new TypescriptFile('const a = 1;', [TypescriptImport::types('./lib/types', 'Brand')]);

    expect($file)->toBeInstanceOf(Stringable::class)
        ->and((string)$file)->toBe($file->toString());
});
