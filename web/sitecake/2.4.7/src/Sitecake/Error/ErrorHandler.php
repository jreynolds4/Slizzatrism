<?php

namespace Sitecake\Error;

use Sitecake\Log\Log;

class ErrorHandler
{
    const SUPPRESS_NONE = 0;

    const SUPPRESS_NEXT = 1;

    const SUPPRESS_ALL = 2;

    protected static $config;

    protected static $suppressed = self::SUPPRESS_NONE;

    public static function register($config = [])
    {
        self::$config = $config;

        $level = E_ALL & ~E_DEPRECATED & ~E_STRICT;

        if (isset($config['error.level']) && !empty($config['error.level'])) {
            $level = $config['error.level'];
        }

        // Set error reporting level
        error_reporting($level);
        // Set error handler
        set_error_handler('\\Sitecake\\Error\\ErrorHandler::handleError', $level);
        // Set Exception handler
        set_exception_handler('\\Sitecake\\Error\\ErrorHandler::wrapAndHandleException');
        ini_set('display_errors', 'Off');
        ini_set('display_warnings', 'Off');

        // Register shutdown function
        register_shutdown_function(function () {
            $error = error_get_last();
            if (!is_array($error)) {
                return;
            }

            switch ($error['type']) {
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    self::handleFatalError(
                        $error['type'],
                        $error['message'],
                        $error['file'],
                        $error['line']
                    );
                    break;
            }
        });
    }

    /**
     * Display/Log a fatal error.
     *
     * @param int $code Code of error
     * @param string $description Error description
     * @param string $file File on which error occurred
     * @param int $line Line that triggered the error
     *
     * @return bool
     */
    public static function handleFatalError($code, $description, $file, $line)
    {
        http_response_code(500);

        $data = [
            'code' => $code,
            'description' => $description,
            'file' => $file,
            'line' => $line,
            'error' => 'Fatal Error',
        ];

        self::logError(LOG_ERR, $data);

        self::displayError($data);

        return true;
    }

    /**
     * Checks the passed exception type. If it is an instance of `Error`
     * then, it wraps the passed object inside another Exception object
     * for backwards compatibility purposes.
     *
     * @param \Exception|\Error $exception The exception to handle
     * @return void
     * @throws \Exception
     */
    public static function wrapAndHandleException($exception)
    {
        if ($exception instanceof \Error) {
            $exception = new PHP7ErrorException($exception);
        }
        self::handleException($exception);
    }

    /**
     * Log an error.
     *
     * @param string $level The level name of the log.
     * @param array $data Array of error data.
     *
     * @return bool
     */
    protected static function logError($level, $data = [])
    {
        if ($level instanceof \Exception) {
            return Log::write('error', self::getMessage($level));
        }

        $message = sprintf(
            '%s (%s): %s in [%s, line %s]',
            $data['error'],
            $data['code'],
            $data['description'],
            $data['file'],
            $data['line']
        );

        ob_start();
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $trace = ob_get_contents();
        ob_end_clean();

        $message .= "\nTrace:\n" . print_r($trace, true) . "\n";
        $message .= "\n\n";

        return Log::write(strtolower($level), $message);
    }

    /**
     * Generates a formatted error message
     *
     * @param \Exception $exception Exception instance
     *
     * @return string Formatted message
     */
    protected static function getMessage(\Exception $exception)
    {
        $message = sprintf(
            "(%s) - %s",
            get_class($exception),
            $exception->getMessage()
        );

        $message .= "\nStack Trace:\n" . $exception->getTraceAsString() . "\n\n";

        return $message;
    }

    /**
     * Depending on second parameter method displays or returns formatted error message if it's not suppressed
     *
     * @param      $error
     * @param bool $return
     *
     * @return string|null
     */
    protected static function displayError($error, $return = false)
    {
        if (self::$suppressed != self::SUPPRESS_NONE && !$return) {
            if (self::$suppressed == self::SUPPRESS_NEXT) {
                self::$suppressed = self::SUPPRESS_NONE;
            }

            return '';
        }

        if (PHP_SAPI == 'cli') {
            return var_dump($error);
        }

        $traceStr =
            "<pre class=\"sitecake-error\" style=\"background: #E4E4E4;border-radius:5px;margin:10px;padding:15px;\">";
        $traceStr .= sprintf(
            '<p style="margin:0;">' .
            '<strong>%s:</strong> %s ' .
            '<strong>in file</strong> %s ' .
            '<strong>on line</strong> (#%s)' .
            '</p>',
            $error['error'],
            self::hideServerInfo($error['description']),
            self::hideServerInfo($error['file']),
            $error['line']
        );

        /*$trace = debug_backtrace();
        $traceStr .= "<div>";
        for($i = 0; $i < count($trace); $i++)
        {
            $traceStr .= '#' . $i . ' ';
            if($trace[$i]['type'])
            {
                $traceStr .= $trace[$i]['class'] . $trace[$i]['type'] . $trace[$i]['function'];
            }
            else
            {
                $traceStr .= $trace[$i]['function'];
            }
            $traceStr .= sprintf('() - %s, line %s', $trace[$i]['file'], $trace[$i]['line']) . '<br />';
        }*/

        $traceStr .= "</div>";

        $traceStr .= "</pre>";

        if ($return) {
            return $traceStr;
        }

        if (self::$config['debug']) {
            echo $traceStr;
        }

        return null;
    }

    /**
     * Transforms path to hide server information
     *
     * @param string $content
     *
     * @return string
     */
    protected static function hideServerInfo($content)
    {
        $content = str_replace(self::$config['BASE_DIR'], '[SITE]', $content);

        return str_replace(DIRECTORY_SEPARATOR, '/', $content);
    }

    /**
     * Set as the default error handler by Sitecake.
     *
     * This function will log errors to Log, when debug == false.
     *
     * @param int $code Code of error
     * @param string $description Error description
     * @param string|null $file File on which error occurred
     * @param int|null $line Line that triggered the error
     * @param array|null $context Context
     *
     * @return bool True if error was handled
     */
    public static function handleError($code, $description, $file = null, $line = null, $context = null)
    {
        if (error_reporting() === 0) {
            return false;
        }

        list($error, $log) = self::mapErrorCode($code);
        if ($log === LOG_ERR) {
            return self::handleFatalError($code, $description, $file, $line);
        }
        $data = [
            'level' => $log,
            'code' => $code,
            'error' => $error,
            'description' => $description,
            'file' => $file,
            'line' => $line,
        ];

        self::logError($log, $data);

        self::displayError($data);

        return true;
    }

    /**
     * Map an error code into an Error word, and log location.
     *
     * @param int $code Error code to map
     *
     * @return array Array of error word, and log location.
     */
    protected static function mapErrorCode($code)
    {
        $levelMap = [
            E_PARSE => 'error',
            E_ERROR => 'error',
            E_CORE_ERROR => 'error',
            E_COMPILE_ERROR => 'error',
            E_USER_ERROR => 'error',
            E_WARNING => 'warning',
            E_USER_WARNING => 'warning',
            E_COMPILE_WARNING => 'warning',
            E_RECOVERABLE_ERROR => 'warning',
            E_NOTICE => 'notice',
            E_USER_NOTICE => 'notice',
            E_STRICT => 'strict',
            E_DEPRECATED => 'deprecated',
            E_USER_DEPRECATED => 'deprecated',
        ];
        $logMap = [
            'error' => LOG_ERR,
            'warning' => LOG_WARNING,
            'notice' => LOG_NOTICE,
            'strict' => LOG_NOTICE,
            'deprecated' => LOG_NOTICE,
        ];

        $error = $levelMap[$code];
        $log = $logMap[$error];

        return [ucfirst($error), $log];
    }

    /**
     * Handle uncaught exceptions.
     *
     * Uses a template method provided by subclasses to display errors in an
     * environment appropriate way.
     *
     * @param \Exception $exception Exception instance.
     * @param bool $return Weather exception string should be returned or displayed. Displayed by default
     *
     * @return string|null Formatted error message if $return is true, null otherwise
     * @throws \Exception When renderer class not found
     * @see http://php.net/manual/en/function.set-exception-handler.php
     */
    public static function handleException(\Exception $exception, $return = false)
    {
        $trace = $exception->getTrace();
        $data = [
            'function' => $trace[0]['function'] ? $trace[0]['function'] : false,
            'class' => $trace[0]['class'] ? $trace[0]['class'] : false,
            'type' => $trace[0]['type'] ? $trace[0]['type'] : false,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'description' => $exception->getMessage(),
            'error' => get_class($exception)
        ];

        self::logError($exception);

        if ($return == true) {
            return self::displayError($data, true);
        }

        self::displayError($data, $return);

        return null;
    }

    /**
     * Suppresses displaying of next or all error messages
     *
     * @param int $level Level of suppression.
     *                   Indicates weather all or just next message should be suppressed for displaying.
     */
    public static function suppress($level = self::SUPPRESS_NEXT)
    {
        self::$suppressed = $level;
    }
}
