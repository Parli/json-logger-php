# `parli/json-logger`

A PSR-3 JSON log formatter.
This outputs a format designed around Datadog's logging system, using their [predefined attributes](https://docs.datadoghq.com/logs/log_configuration/attributes_naming_convention/#reserved-attributes) for the structure.

## Usage

This library only formats logs, and does not write them.
It must be used in conjunction with another PSR-3 which actually writes the logs somewhere useful.
(For us, this is `stdout` due to deployment in a containerized environment.)

```php
$writer = new \SomePsr3Logger();
$logger = new \Parli\JsonLogger\JsonLogger($writer);
// ...
$logger->error('Error message {info}', [
    'info' => $someMoreInfo,
    'exception' => $someThrowable,
]);
```

Note: the log writer will not receive the `$context` that this library receives.
This library interpolates the context into the JSON message before passing a fully-formatted JSON string to the log writer.

## Exception logging

This library looks for `Throwable`s in the `exception` key of `$context`, per PSR-3 section 1.3.
If found, it will automatically fill in the error attributes for integration with Datadog's log display system.

It is RECOMMENDED that you always pass caught exceptions to the logger's `$context` (e.g. `->error('...', ['exception' => $e])`).
It is further RECOMMENDED that you *do not* do additional log interpolation.

Example:
```php
try {
    // ...
} catch (Throwable $e) {
    $logger->error('Caught exception in worker with input {input}', [
        'input' => $input,
        'exception' => $e,
    ]);
}
```

COUNTEREXAMPLE:
```php
// Do NOT do this:
try {
    // ...
} catch (Throwable $e) {
    $logger->error('Caught exception in worker with input {input} - {message}: {trace}', [
        'input' => $input,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'exception' => $e,
    ]);
}
```
