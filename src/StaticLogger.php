<?php

namespace LosMiddleware\LosLog;

class StaticLogger extends AbstractLogger
{
    /**
     * @param mixed $message
     * @param string $logFile
     * @param string $logDir
     * @throws Exception\InvalidArgumentException
     */
    public static function save($message, $logFile = 'static.log', $logDir = 'data/logs')
    {
        if ($logFile == null) {
            // Useful for just Z-Ray logging
            return;
        }

        $logger = AbstractLogger::generateFileLogger($logFile, $logDir);

        if (is_object($message) && method_exists($message, 'losLogMe')) {
            $message = json_encode($message->losLogMe());
        }

        $logger->debug($message);
    }
}
