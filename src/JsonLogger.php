<?php

declare(strict_types=1);

namespace Parli\JsonLogger;

use Psr\Log\{AbstractLogger, LoggerInterface};
use Throwable;

use function array_key_exists;
use function is_array;
use function is_object;
use function method_exists;
use function strtr;
use function get_class;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class JsonLogger extends AbstractLogger
{
    public function __construct(private LoggerInterface $writer)
    {
    }

    public function log($level, $message, array $context = []): void
    {
        if (!is_string($message)) {
            $message = (string) $message;
        }
        // Keys in the json dict are based around Datadog's reserved
        // attributes:
        // https://docs.datadoghq.com/logs/log_configuration/attributes_naming_convention/#reserved-attributes
        $data = [
            'timestamp' => date('c'), // ISO8601
            'status' => $level,
            'message' => $this->interpolate($message, $context),
        ];

        if (array_key_exists('exception', $context)) {
            $exception = $context['exception'];
            assert($exception instanceof Throwable);
            // Use datadog format rules
            // https://docs.datadoghq.com/logs/log_collection/?tab=host#attributes-and-tags
            $data['error'] = [
                'message' => $exception->getMessage(),
                'kind' => get_class($exception),
                // The built in casting deals with rendering $previous
                'stack' => (string)$exception,
            ];
        }

        $json = json_encode($data, JSON_THROW_ON_ERROR);

        // This intentionally does not pass through $context again, as it's
        // already been interpolated and all the brackets in JSON would cause
        // a wild time.
        $this->writer->log($level, $json);
    }

    /**
     * Reference interpolate() implemenation from PSR-3
     *
     * @param mixed[] $context
     */
    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            // check that the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}
