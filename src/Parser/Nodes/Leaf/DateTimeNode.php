<?php

declare(strict_types=1);

namespace Le0daniel\PhpTsBindings\Parser\Nodes\Leaf;

use DateTimeImmutable;
use DateTimeInterface;
use Le0daniel\PhpTsBindings\Data\Value;
use Le0daniel\PhpTsBindings\Executor\Contracts\ExecutionContext;
use Le0daniel\PhpTsBindings\Executor\Data\Issue;
use Le0daniel\PhpTsBindings\Executor\Data\IssueMessage;
use Le0daniel\PhpTsBindings\Parser\Contracts\LeafNode;
use Le0daniel\PhpTsBindings\Parser\Contracts\NodeInterface;
use Le0daniel\PhpTsBindings\Utils\PHPExport;
use Override;
use Throwable;

final readonly class DateTimeNode implements LeafNode, NodeInterface
{
    /**
     * The shapes accepted when no format was written: two zone spellings times two precisions.
     *
     * `P` renders UTC as `+00:00`, lowercase `p` renders it as `Z` - the shape `Date.toISOString()`
     * produces - and both render a real offset unchanged. So each precision needs both spellings.
     * `v` is exactly three fractional digits. Six digit microseconds are deliberately out of the
     * set; a client that sends them writes the format out.
     *
     * The candidates overlap rather than partition - a non UTC offset matches both the `P` and the
     * `p` variant of its precision - but overlapping candidates always yield the same instant and
     * the same timezone, because `P` and `p` differ only in how they render UTC. The order is
     * therefore a performance detail and never a semantic one, and RFC3339_EXTENDED leads because
     * it is what serializeValue() writes: a value that left this library matches on the first try.
     *
     * @var non-empty-list<non-empty-string>
     */
    private const array ISO8601_INPUT_FORMATS = [
        DateTimeInterface::RFC3339_EXTENDED, // 2026-08-18T11:00:32.778+00:00
        'Y-m-d\TH:i:s.vp',                   // 2026-08-18T11:00:32.778Z
        DateTimeInterface::ATOM,             // 2026-08-18T11:00:32+00:00
        'Y-m-d\TH:i:sp',                     // 2026-08-18T11:00:32Z
    ];

    /**
     * @param  class-string<DateTimeInterface>  $dateTimeClass
     * @param  string|null  $format  null is the ISO-8601 default: every shape in
     *                               ISO8601_INPUT_FORMATS is accepted on input, RFC3339_EXTENDED is
     *                               written on output. A written format is exact in both
     *                               directions - it is a contract its author spelled out, so it is
     *                               never widened.
     */
    public function __construct(
        public string $dateTimeClass,
        public ?string $format = null,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        // No format written, no generic shown. Two nodes that behave differently must not share a
        // diagnostic label, even though the label is not what the optimizer hashes.
        return $this->format === null
            ? $this->dateTimeClass
            : $this->dateTimeClass."<{$this->format}>";
    }

    #[Override]
    public function exportPhpCode(): string
    {
        $className = PHPExport::absolute(self::class);
        $dateTimeClass = PHPExport::absolute($this->dateTimeClass);

        // Only the constructor default is omitted. A written ATOM is a different node than no
        // format at all - it parses strictly - so it has to survive into the cached AST.
        $format = $this->format === null
            ? ''
            : ','.PHPExport::export($this->format);

        return "new {$className}({$dateTimeClass}::class{$format})";
    }

    #[Override]
    public function parseValue(mixed $value, ExecutionContext $context): DateTimeInterface|Value
    {
        if (! is_string($value)) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => 'Expected value of type string, got: '.gettype($value),
                ]
            ));

            return Value::INVALID;
        }

        $parsed = $this->parseExactly($value);

        if ($parsed === null) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => "Expected {$this->expectation()}, got: {$value}",
                ]
            ));

            return Value::INVALID;
        }

        try {
            // @phpstan-ignore-next-line
            return $this->dateTimeClass::createFromInterface($parsed);
        } catch (Throwable $exception) {
            $context->addIssue(Issue::fromThrowable($exception));

            return Value::INVALID;
        }
    }

    /**
     * @return string|Value::INVALID
     */
    #[Override]
    public function serializeValue(mixed $value, ExecutionContext $context): string|Value
    {
        if (! $value instanceof DateTimeInterface) {
            $context->addIssue(new Issue(
                IssueMessage::INVALID_TYPE,
                [
                    'message' => 'Expected instance of DateTimeInterface, got: '.gettype($value),
                ],
            ));

            return Value::INVALID;
        }

        // Lenient in, one shape out, so the generated TypeScript `string` has a single meaning and
        // anything written here parses back in. Sub-second precision beyond milliseconds is
        // accepted on input and truncated here; a client that needs it on the wire writes
        // DateTimeString<'Y-m-d\TH:i:s.up'> and gets it in both directions.
        return $value->format($this->format ?? DateTimeInterface::RFC3339_EXTENDED);
    }

    /**
     * The first accepted format the value matches exactly, or null if it matches none.
     *
     * The trailing `|` resets every field the format did not parse to a zero-like value. Without
     * it, `Y-m-d` inherits the current clock time and the result is not deterministic.
     *
     * createFromFormat() is lenient: it accepts `2025-1-1` for `Y-m-d` without so much as a
     * warning, and rolls `2025-02-30` over into March. Re-formatting the result and comparing it to
     * the input is the only check that holds the value to the format exactly, and it is what keeps
     * a list of candidates from widening into "anything vaguely date shaped".
     */
    private function parseExactly(string $value): ?DateTimeImmutable
    {
        foreach ($this->acceptedInputFormats() as $format) {
            $parsed = DateTimeImmutable::createFromFormat("{$format}|", $value);

            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @return non-empty-list<string>
     */
    private function acceptedInputFormats(): array
    {
        return $this->format === null
            ? self::ISO8601_INPUT_FORMATS
            : [$this->format];
    }

    /**
     * Four PHP format strings mean nothing to the frontend developer who sent the bad value, so the
     * default names the standard and shows it instead. A written format keeps the exact wording it
     * has always had, because there the format is what the author asked for.
     */
    private function expectation(): string
    {
        return $this->format === null
            ? "an ISO-8601 date time string, such as '2026-08-18T11:00:32.778Z' or '2026-08-18T11:00:32+00:00'"
            : "a date string of format '{$this->format}'";
    }
}
