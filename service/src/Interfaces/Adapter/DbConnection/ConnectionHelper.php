<?php

namespace Fxtm\CopyTrading\Interfaces\Adapter\DbConnection;

class ConnectionHelper
{
    public static function connectWithAttempts(int $attempts, string $dsn, string $username, string $passwd, array $options): \PDO
    {
        $lastException = null;
        $initialAttempts = $attempts;

        while ($attempts > 0) {
            try {
                return new \PDO($dsn, $username, $passwd, $options);
            } catch (\Exception $e) {
                $lastException = $e;
                $attempts--;

                sleep(random_int(1, 10));
            }
        }

        $msg = $lastException ? $lastException->getMessage() : '(no exception)';

        throw new \RuntimeException("Connection to DB {$dsn} (user {$username}) failed after {$initialAttempts} attempts: {$msg}", 0, $lastException);
    }
}
