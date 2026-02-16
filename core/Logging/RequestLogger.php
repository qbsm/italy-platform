<?php
namespace App\Logging;

class RequestLogger
{
    private Logger $logger;
    private string $logDir;

    public function __construct(Logger $logger, string $logDir)
    {
        $this->logger = $logger;
        $this->logDir = $logDir;
        $this->logger->initLogsSystem($this->logDir);
    }

    public function logError(string $message, array $context = []): void
    {
        $this->logger->writeToLog('errors.txt', $message . ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE), $this->logDir);
    }

    public function logInfo(string $message): void
    {
        $this->logger->writeToLog('app.txt', $message, $this->logDir);
    }
}
