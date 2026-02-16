<?php
namespace App\Logging;

class Logger
{
    public function writeToLog(string $filename, string $message, string $logDirectory): void
    {
        if (!is_dir($logDirectory)) {
            if (!@mkdir($logDirectory, 0755, true) && !is_dir($logDirectory)) {
                error_log("Не удалось создать директорию для логов: {$logDirectory} | Сообщение: {$message}");
                return;
            }
        }
        $filepath = rtrim($logDirectory, '/') . '/' . $filename;
        $timestamp = date('d-m-Y H:i:s');
        $logEntry = "[{$timestamp}] " . $message . "\n";

        if (is_writable($logDirectory) || (!file_exists($filepath) && is_writable(dirname($filepath)))) {
            @file_put_contents($filepath, $logEntry, FILE_APPEND);
        } else {
            error_log("Директория/файл для логов не доступна для записи: {$filepath} | Сообщение: {$message}");
        }
    }

    public function initLogsSystem(string $logDirectory): void
    {
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0755, true);
        }

        $logHtaccessPath = rtrim($logDirectory, '/') . '/.htaccess';
        if (!file_exists($logHtaccessPath)) {
            $logHtaccessContent = "# Запрещаем просмотр директории\nOptions -Indexes\n\n# Запрещаем доступ ко всем файлам\n<FilesMatch \".*\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>\n\n# Защищаем .htaccess файл\n<Files \".htaccess\">\nOrder Allow,Deny\nDeny from all\n</Files>";
            @file_put_contents($logHtaccessPath, $logHtaccessContent);
            @chmod($logHtaccessPath, 0644);
        }
    }
}
