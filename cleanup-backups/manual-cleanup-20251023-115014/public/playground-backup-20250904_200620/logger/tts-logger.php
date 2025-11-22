<?php
/**
 * Sistema de Logging Centralizado para TTS
 * @version 1.0
 * @date 2024-11-29
 */

class TTSLogger {
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR = 3;
    
    private $logFile;
    private $logLevel;
    private $category;
    private $maxFileSize = 10485760; // 10MB
    
    public function __construct($category = 'general', $level = self::LEVEL_INFO) {
        $this->category = $category;
        $this->logLevel = $level;
        $this->logFile = $this->getLogFilePath();
        $this->checkRotation();
    }
    
    private function getLogFilePath() {
        $logDir = __DIR__ . '/../logs/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        return $logDir . $this->category . '-' . date('Y-m-d') . '.log';
    }
    
    private function checkRotation() {
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $rotatedFile = $this->logFile . '.' . time();
            rename($this->logFile, $rotatedFile);
        }
    }
    
    public function log($message, $level = self::LEVEL_INFO, $context = []) {
        if ($level < $this->logLevel) return;
        
        $levelStr = ['DEBUG', 'INFO', 'WARNING', 'ERROR'][$level];
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        
        $logEntry = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            $timestamp,
            $levelStr,
            $this->category,
            $message,
            $contextStr
        );
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // También enviar a la consola del playground si está en modo debug
        if ($level === self::LEVEL_DEBUG && isset($_GET['debug'])) {
            header('X-Debug-Log: ' . base64_encode($message));
        }
    }
    
    public function debug($message, $context = []) {
        $this->log($message, self::LEVEL_DEBUG, $context);
    }
    
    public function info($message, $context = []) {
        $this->log($message, self::LEVEL_INFO, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log($message, self::LEVEL_WARNING, $context);
    }
    
    public function error($message, $context = []) {
        $this->log($message, self::LEVEL_ERROR, $context);
    }
    
    public function getRecentLogs($lines = 100) {
        if (!file_exists($this->logFile)) return [];
        
        $file = new SplFileObject($this->logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        $logs = [];
        
        $file->seek($startLine);
        while (!$file->eof()) {
            $line = $file->fgets();
            if (trim($line)) {
                $logs[] = $this->parseLogLine($line);
            }
        }
        
        return array_reverse($logs);
    }
    
    private function parseLogLine($line) {
        if (preg_match('/\[(.*?)\] \[(.*?)\] \[(.*?)\] (.*?)(\{.*\})?$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'category' => $matches[3],
                'message' => trim($matches[4]),
                'context' => isset($matches[5]) ? json_decode($matches[5], true) : null
            ];
        }
        return ['raw' => $line];
    }
}