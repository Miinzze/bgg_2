<?php
class RateLimiter {
    private $pdo;
    private $maxAttempts = 5;
    private $lockoutTime = 900; // 15 Minuten in Sekunden
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function checkLimit($identifier) {
        // Alte Einträge löschen
        $this->cleanup();
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts, 
                   MAX(attempt_time) as last_attempt 
            FROM login_attempts 
            WHERE identifier = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $this->lockoutTime]);
        $result = $stmt->fetch();
        
        if ($result['attempts'] >= $this->maxAttempts) {
            $waitTime = $this->lockoutTime - (time() - strtotime($result['last_attempt']));
            return [
                'allowed' => false,
                'wait_seconds' => max(0, $waitTime),
                'attempts_left' => 0
            ];
        }
        
        return [
            'allowed' => true,
            'attempts_left' => $this->maxAttempts - $result['attempts']
        ];
    }
    
    public function recordAttempt($identifier, $success = false) {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (identifier, attempt_time, success) 
            VALUES (?, NOW(), ?)
        ");
        $stmt->execute([$identifier, $success ? 1 : 0]);
        
        // Bei Erfolg alle fehlgeschlagenen Versuche löschen
        if ($success) {
            $this->resetAttempts($identifier);
        }
    }
    
    public function resetAttempts($identifier) {
        $stmt = $this->pdo->prepare("
            DELETE FROM login_attempts 
            WHERE identifier = ? AND success = 0
        ");
        $stmt->execute([$identifier]);
    }
    
    private function cleanup() {
        $this->pdo->query("
            DELETE FROM login_attempts 
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    }
}