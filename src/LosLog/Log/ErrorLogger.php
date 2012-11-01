<?php
/**
 * Logs errors and exceptions
 *
 * @package    LosLos\Log
 * @author     Leandro Silva <lansoweb@hotmail.com>
 * @copyright  2011-2012 Leandro Silva
 */
namespace LosLog\Log;
use Zend\Log\Logger;

use LosLog\Log\AbstractLogger;

use Zend\Log;

/**
 * Logs errors and exceptions
 *
 * @package    LosLos\Log
 * @author     Leandro Silva <lansoweb@hotmail.com>
 * @copyright  2011-2012 Leandro Silva
 */
class ErrorLogger extends AbstractLogger
{

    /**
     * Registers an error handler for PHP errors
     *
     * @param LosLog\Log\ErrorLogger $logger
     * @return boolean Returna always false to enable other handlers, including the default
     * @throws Exception\InvalidArgumentException if logger is null
     */
    public static function registerErrorHandler (Logger $logger)
    {
        // Only register once per instance
        if (self::$registeredErrorHandler) {
            return false;
        }

        if ($logger === null) {
            throw new \Zend\Log\Exception\InvalidArgumentException('Invalid Logger specified');
        }

        $errorHandlerMap = array(
                E_NOTICE => self::NOTICE,
                E_USER_NOTICE => self::NOTICE,
                E_WARNING => self::WARN,
                E_CORE_WARNING => self::WARN,
                E_USER_WARNING => self::WARN,
                E_ERROR => self::ERR,
                E_USER_ERROR => self::ERR,
                E_CORE_ERROR => self::ERR,
                E_RECOVERABLE_ERROR => self::ERR,
                E_STRICT => self::DEBUG,
                E_DEPRECATED => self::DEBUG,
                E_USER_DEPRECATED => self::DEBUG
        );

        set_error_handler(
                function  ($errno, $errstr, $errfile, $errline, $errcontext) use(
                $errorHandlerMap, $logger)
                {
                    $errorLevel = error_reporting();

                    if ($errorLevel && $errno) {
                        if (isset($errorHandlerMap[$errno])) {
                            $priority = $errorHandlerMap[$errno];
                        } else {
                            $priority = \Zend\Log\Logger::INFO;
                        }
                        $logger->log($priority,'Error: ' . $errstr . ' in ' . $errfile .' in line ' . $errline);
                    }
                });

        register_shutdown_function(
                function() use($logger) {
                    $error = error_get_last();
                    if (null === $error) {
                        return false;
                    }
                    $priority = \Zend\Log\Logger::ERR;
                    $logger->log($priority,'Error: ' . $error['message'] . ' in ' . $error['file'] . ' in line ' .
                                     $error['line']);
                });

        self::$registeredErrorHandler = true;

        return false;
    }

    /**
     * Registers an exception handler
     *
     * @param LosLog\Log\ErrorLogger $logger
     * @return boolean Returna always false to enable other handlers, including the default
     * @throws Exception\InvalidArgumentException if logger is null
     */
    public static function registerExceptionHandler (Logger $logger)
    {
        // Only register once per instance
        if (self::$registeredExceptionHandler) {
            return false;
        }

        if ($logger === null) {
            throw new \Zend\Log\Exception\InvalidArgumentException('Invalid Logger specified');
        }

        set_exception_handler(
                function  ($exception) use( $logger) {
                    /* var $exception Exception */
                    $extra = array(
                            'file' => $exception->getFile(),
                            'line' => $exception->getLine(),
                            'trace' => $exception->getTrace()
                    );
                    if (isset($exception->xdebug_message)) {
                        $extra['xdebug'] = $exception->xdebug_message;
                    }

                    $msg = '';
                    $prev = $exception->getPrevious();
                    while ($prev != null) {
                        $msg .= PHP_EOL . 'Previous: ' . $prev->getMessage() .
                                 ' in ' . $prev->getFile() . ' in line ' .
                                 $prev->getLine() . '.';
                        $prev = $prev->getPrevious();
                    }
                    $logger->log(ErrorLogger::ERR,
                            'Exception: ' . $exception->getMessage() . ' in ' .
                                     $exception->getFile() . ' in line ' .
                                     $exception->getLine() . '.' . $msg . PHP_EOL .
                                     'Trace:' . PHP_EOL .
                                     $exception->getTraceAsString());
                });
        self::$registeredExceptionHandler = true;

        return false;
    }

    /**
     * Logs any dispatch exceptions
     *
     * @param \Zend\Mvc\MvcEvent $e
     */
    public function dispatchError (\Zend\Mvc\MvcEvent $e)
    {
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        // Do nothing if the result is a response object
        $result = $e->getResult();
        if ($result instanceof \Zend\Stdlib\ResponseInterface) {
            return;
        }

        switch ($error) {
            case \Zend\Mvc\Application::ERROR_CONTROLLER_NOT_FOUND:
            case \Zend\Mvc\Application::ERROR_CONTROLLER_INVALID:
            case \Zend\Mvc\Application::ERROR_ROUTER_NO_MATCH:
                // Specifically not handling these
                return;

            case \Zend\Mvc\Application::ERROR_EXCEPTION:
            default:
                $exception = $e->getParam('exception');
                $msg = '';
                $prev = $exception->getPrevious();
                while ($prev != null) {
                    $msg .= PHP_EOL . 'Previous: ' . $prev->getMessage() . ' in ' .
                             $prev->getFile() . ' in line ' . $prev->getLine() .
                             '.';
                    $prev = $prev->getPrevious();
                }
                $this->log(ErrorLogger::ERR,
                        'Dispatch: ' . $exception->getMessage() . ' in ' .
                                 $exception->getFile() . ' in line ' .
                                 $exception->getLine() . '.' . $msg . PHP_EOL .
                                 'Trace:' . PHP_EOL .
                                 $exception->getTraceAsString());
                break;
        }
    }

    /**
     * Registers the handlers for errors and exceptions
     *
     * @param string $arq
     */
    public static function registerHandlers($logFile = 'error.log', $logDir = 'data/logs')
    {
        $logger = new self($logFile, $logDir);
        self::registerErrorHandler($logger);
        self::registerExceptionHandler($logger);
    }
}
