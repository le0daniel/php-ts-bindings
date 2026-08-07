<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\CodeGen\Utils;

use Le0daniel\PhpTsBindings\Server\Data\Definition;
use Le0daniel\PhpTsBindings\Server\Data\ErrorType;
use Le0daniel\PhpTsBindings\Server\Data\ServerConfiguration;
use Le0daniel\PhpTsBindings\Server\Errors\ExposedExceptions;
use Le0daniel\PhpTsBindings\Utils\Arrays;
use ReflectionException;

/**
 * The TypeScript face of the server's finite error catalogue.
 *
 * Each branch is declared once, as a named envelope in the generated types file, and Failure is the
 * union of the ones this server can produce. The shapes therefore live here rather than at every use
 * site, and a consumer can name a branch — `NotFoundError` — instead of restating its literal.
 *
 * That the catalogue is closed is what lets Failure be that union rather than take one: the only
 * thing varying per operation is which exceptions it exposed, so the names of those are the only
 * thing Failure is parameterised on.
 *
 * The runtime counterpart is Server\Errors\ErrorPresenter, and the branches below appear in its
 * resolution order. Only reachable branches are unioned: the two auth categories exist solely
 * because exceptions were mapped onto them, and a domain error only exists where an operation
 * declares an exception via #[Throws] that resolves to a name - its own `as`, or #[ExposeAs] on the
 * exception class. Everything else the server produces on its own.
 */
final readonly class ErrorTypescript
{
    /**
     * The one branch no server sends: the request never got there, so a client hands this back
     * instead. It has no ErrorType case for the same reason, and it is the only envelope whose
     * payload is a live object rather than something that came off the wire.
     */
    private const string CLIENT_ENVELOPE = 'ClientError';

    /**
     * What Failure names its type parameter. The union below is written in terms of it, so the
     * declaration and the branch that carries it cannot disagree about the name.
     */
    public const string DOMAIN_TYPE_PARAMETER = 'TDomainType';

    /**
     * What an operation exposing nothing instantiates the domain branch with. DomainError erases
     * itself on it, so such an operation's Failure has no 400 branch at all.
     */
    public const string NO_DOMAIN_TYPES = 'never';

    /**
     * Envelope name => [type parameters, declaration], in ErrorPresenter resolution order.
     *
     * The domain branch is the only one whose payload depends on the operation - the names of the
     * exceptions it exposed - so it is the only one that takes a type argument. Every other category
     * says the same thing for every operation on the server.
     *
     * Its conditional is what makes `never` mean the branch is gone rather than a 400 whose name is
     * uninhabited. The wrapping brackets keep it from distributing, so two exposed names stay one
     * member with a union under `details.type` instead of becoming two members.
     *
     * @var array<string, array{string, string}>
     */
    private const array ENVELOPES = [
        'InvalidInputError' => ['', '{code: 422, type: "INVALID_INPUT", details: {fields: Record<string, string[]>}}'],
        'AuthenticationError' => ['', '{code: 401, type: "AUTHENTICATION_ERROR"}'],
        'AuthorizationError' => ['', '{code: 403, type: "AUTHORIZATION_ERROR"}'],
        'NotFoundError' => ['', '{code: 404, type: "NOT_FOUND"}'],
        'DomainError' => ['<TType extends string>', '[TType] extends [never] ? never : {code: 400, type: "DOMAIN_ERROR", details: {type: TType}}'],
        'InternalError' => ['', '{code: 500, type: "INTERNAL_ERROR"}'],
        self::CLIENT_ENVELOPE => ['', '{code: 0, type: "CLIENT_ERROR", cause: Error}'],
    ];

    /**
     * Every name this catalogue occupies in the types file. What EmitTypes reserves, so a #[Named]
     * type claiming one of them fails instead of generating a second, conflicting declaration.
     *
     * @return list<string>
     */
    public static function envelopeNames(): array
    {
        return array_keys(self::ENVELOPES);
    }

    /**
     * The declarations, for the file that holds them. Nothing else may restate a shape: a second
     * copy is free to drift from the one operations are typed against.
     *
     * All of them are declared, including the ones this server cannot reach. They are names a
     * consumer writes a handler against, and reserving a name it might not use costs nothing -
     * whereas a union naming an unreachable branch would claim the server can produce it.
     */
    public static function envelopeDeclarations(): string
    {
        return implode("\n", Arrays::mapWithKeys(
            self::ENVELOPES,
            /** @param array{string, string} $envelope */
            static fn (string $name, array $envelope): string => "export type {$name}{$envelope[0]} = {$envelope[1]};",
        ));
    }

    /**
     * What Failure is, for this server: every category it can produce, in resolution order, closed
     * by the branch a client mints when the request never got there.
     *
     * The domain branch is unconditional here, unlike the two auth ones. It carries the type
     * parameter, and an operation exposing nothing instantiates that with `never` - which erases the
     * branch already, so gating it a second time would only mean saying `never` twice.
     */
    public static function failureUnion(ServerConfiguration $configuration): string
    {
        /** @var list<ErrorType> $categories */
        $categories = [ErrorType::INVALID_INPUT];

        if (count($configuration->unauthenticatedExceptions) !== 0) {
            $categories[] = ErrorType::AUTHENTICATION_ERROR;
        }

        if (count($configuration->unauthorizedExceptions) !== 0) {
            $categories[] = ErrorType::AUTHORIZATION_ERROR;
        }

        $categories[] = ErrorType::NOT_FOUND;
        $categories[] = ErrorType::DOMAIN_ERROR;
        $categories[] = ErrorType::INTERNAL_ERROR;

        $references = array_map(
            static fn (ErrorType $type): string => $type === ErrorType::DOMAIN_ERROR
                ? self::envelopeFor($type).'<'.self::DOMAIN_TYPE_PARAMETER.'>'
                : self::envelopeFor($type),
            $categories,
        );

        // Closed here rather than by a caller: what a client can hand back belongs to the same union
        // as what the server can, so the union has one owner and one set of tests.
        $references[] = self::CLIENT_ENVELOPE;

        return implode('|', $references);
    }

    /**
     * Exhaustive on purpose: a category added to ErrorType without an envelope to carry it fails
     * here rather than generating a union that quietly cannot describe it.
     */
    private static function envelopeFor(ErrorType $type): string
    {
        return match ($type) {
            ErrorType::INVALID_INPUT => 'InvalidInputError',
            ErrorType::AUTHENTICATION_ERROR => 'AuthenticationError',
            ErrorType::AUTHORIZATION_ERROR => 'AuthorizationError',
            ErrorType::NOT_FOUND => 'NotFoundError',
            ErrorType::DOMAIN_ERROR => 'DomainError',
            ErrorType::INTERNAL_ERROR => 'InternalError',
        };
    }

    /**
     * The literal union one operation instantiates the domain branch with, or `never` where it
     * exposes nothing and the branch is unreachable.
     *
     * @throws ReflectionException
     */
    public static function domainTypesFor(ServerConfiguration $configuration, Definition $definition): string
    {
        $exposedTypes = ExposedExceptions::exposedTypesFor($definition, $configuration);
        if (count($exposedTypes) === 0) {
            return self::NO_DOMAIN_TYPES;
        }

        return implode('|', array_map(
            static fn (string $exposedType): string => json_encode($exposedType, JSON_THROW_ON_ERROR),
            $exposedTypes,
        ));
    }
}
