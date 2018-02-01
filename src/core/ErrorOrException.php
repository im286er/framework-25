<?php

namespace framework\core;

/**
 * 异常、错误
 */
class ErrorOrException {

    /**
     * 致命错误处理
     */
    public static function FatalError() {
        if ($error = error_get_last()) {
            self::ErrorHandle($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * 错误处理
     * @param type $errno
     * @param type $errstr
     * @param type $errfile
     * @param type $errline
     * @return boolean
     */
    public static function ErrorHandle($errno, $errstr, $errfile, $errline) {

        $errfile = str_replace($_SERVER['DOCUMENT_ROOT'], '', $errfile);

        $errortype = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_DEPRECATED => 'Deprecated',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_USER_DEPRECATED => 'User Deprecated',
            E_STRICT => 'Runtime Notice',
            E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        ];

        // 判断错误级别，决定是否退出。

        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_USER_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                // 抛出异常，记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[{$errnostr}] : {$errstr} in File {$errfile}, Line: {$errline}";
                Log::write($s, Log::EMERG);

                $msg = "500 Internal Server Error {$errno} {$s}";

                try {
                    $json = ['ret' => 500, 'data' => null, 'msg' => $msg];
                    Response::getInstance()->clear()->contentType('application/json')->write(json_encode($json, JSON_UNESCAPED_UNICODE))->send();
                } catch (\Exception $ex) {
                    exit($msg);
                }
                break;
            case E_WARNING:
                // 记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[{$errnostr}] : {$errstr} in File {$errfile}, Line: {$errline}";
                Log::write($s, Log::WARN);
                break;
            case E_NOTICE:
                // 记录到日志
                $errnostr = isset($errortype[$errno]) ? $errortype[$errno] : 'Unknonw';
                $s = "[{$errnostr}] : {$errstr} in File {$errfile}, Line: {$errline}";
                Log::write($s, Log::NOTICE);
                break;
            default:
                break;
        }
        return false;
    }

    /**
     * 应用异常处理
     * @param type $e
     */
    public static function AppException($e) {
        $errfile = str_replace($_SERVER['DOCUMENT_ROOT'], '', $e->getFile());

        $msg = $e->getMessage() . ' File: ' . $errfile . ' [' . $e->getLine() . ']';
        Log::write($msg, Log::EMERG);

        try {
            $json = ['ret' => $e->getCode(), 'data' => null, 'msg' => $e->getMessage()];
            Response::getInstance()->clear()->contentType('application/json')->write(json_encode($json, JSON_UNESCAPED_UNICODE))->send();
        } catch (\Exception $ex) {
            exit($msg);
        }
    }

}
