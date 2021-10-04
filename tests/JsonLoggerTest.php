<?php

declare(strict_types=1);

namespace Parli\JsonLogger;

use Psr\Log\{LoggerInterface, LogLevel};
use UnexpectedValueException;

/**
 * @covers Parli\Tools\Loggers\JsonLogger
 */
class JsonLoggerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array{string, string}[]
     */
    private array $logEntries = [];

    public function testJsonIsLogged(): void
    {
        $jl = $this->getLogger();
        $jl->info('foo {bar}', ['bar' => 'baz']);

        $this->assertCount(1, $this->logEntries);
        [$level, $message] = $this->logEntries[0];
        $this->assertSame(LogLevel::INFO, $level);
        $data = json_decode($message, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('info', $data['status'], 'status');
        $this->assertEqualsWithDelta(time(), strtotime($data['timestamp']), 2, 'timestamp');
        $this->assertSame('foo baz', $data['message'], 'message');
    }

    public function testExceptionLogging(): void
    {
        $e = new UnexpectedValueException('No good');
        $jl = $this->getLogger();
        $jl->error('it broke! {err}', ['err' => 'oops', 'exception' => $e]);

        $this->assertCount(1, $this->logEntries);
        [$level, $message] = $this->logEntries[0];
        $this->assertSame(LogLevel::ERROR, $level);
        $data = json_decode($message, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('error', $data['status'], 'status');
        $this->assertEqualsWithDelta(time(), strtotime($data['timestamp']), 2, 'timestamp');
        $this->assertSame('it broke! oops', $data['message'], 'message');

        // The exception should provide additional data simply by being present
        $errInfo = $data['error'];
        $this->assertSame('No good', $errInfo['message'], 'error.message');
        $this->assertSame('UnexpectedValueException', $errInfo['kind'], 'error.kind');
        $this->assertSame((string) $e, $errInfo['stack'], 'error.stack');
    }

    private function getLogger(): JsonLogger
    {
        $writer = $this->createMock(LoggerInterface::class);
        $writer->method('log')
            ->willReturnCallback([$this, 'log']);
        return new JsonLogger($writer);
    }

    /**
     * Callback for mock loggers
     *
     * @param mixed[] $context
     */
    public function log(string $level, string $message, array $context): void
    {
        $this->assertEmpty($context, 'Context should never make it to the writer');
        $this->logEntries[] = [$level, $message];
    }
}
