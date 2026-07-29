<?php
class AgentLogger
{
    private static string $logPath = '';

    public static function init(): void
    {
        self::$logPath = __DIR__ . '/../../storage/logs/';
        if (!is_dir(self::$logPath)) {
            @mkdir(self::$logPath, 0755, true);
        }
    }

    public static function info(string $msg): void
    {
        self::write('INFO', $msg);
    }

    public static function warn(string $msg): void
    {
        self::write('WARN', $msg);
    }

    public static function error(string $msg): void
    {
        self::write('ERROR', $msg);
    }

    public static function security(string $msg): void
    {
        self::write('SECURITY', $msg);
    }

    private static function write(string $level, string $msg): void
    {
        if (!self::$logPath) self::init();
        $line = date('Y-m-d H:i:s') . " [$level] $msg" . PHP_EOL;
        @file_put_contents(self::$logPath . 'agents.log', $line, FILE_APPEND | LOCK_EX);
    }
}
