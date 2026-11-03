<?php

declare(strict_types=1);

final class TestRunner
{
    private int $assertions = 0;
    private int $failures = 0;

    /**
     * @param array<string, callable(TestRunner): void> $tests
     */
    public function runTests(array $tests, string $file): void
    {
        foreach ($tests as $name => $test) {
            try {
                $test($this);
                fwrite(STDOUT, "PASS {$name}\n");
            } catch (Throwable $throwable) {
                $this->failures++;
                fwrite(STDOUT, "FAIL {$name} ({$file})\n");
                fwrite(STDOUT, $throwable->getMessage() . "\n");
            }
        }
    }

    public function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $label = $message === '' ? 'Values are not identical' : $message;
            throw new RuntimeException($label . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
        }
    }

    public function true(bool $condition, string $message = 'Condition is not true'): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function contains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            $label = $message === '' ? "String does not contain '{$needle}'" : $message;
            throw new RuntimeException($label . "\nHaystack: {$haystack}");
        }
    }

    /**
     * @param callable(): void $callback
     */
    public function throws(string $expectedClass, callable $callback): void
    {
        $this->assertions++;
        try {
            $callback();
        } catch (Throwable $throwable) {
            if ($throwable instanceof $expectedClass) {
                return;
            }
            throw new RuntimeException('Expected ' . $expectedClass . ', got ' . $throwable::class);
        }

        throw new RuntimeException('Expected exception ' . $expectedClass . ' was not thrown');
    }

    public function assertions(): int
    {
        return $this->assertions;
    }

    public function failures(): int
    {
        return $this->failures;
    }
}

