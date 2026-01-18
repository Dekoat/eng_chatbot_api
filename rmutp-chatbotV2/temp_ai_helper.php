<?php
class AIHelper {
    private $apiUrl;
    private $timeout;
    private $enabled;
    
    public function __construct($apiUrl = 'http://localhost:5000', $timeout = 3) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->timeout = $timeout;
        $this->enabled = $this->checkHealth();
    }
    
    public function checkHealth() {
        try {
            $ch = curl_init($this->apiUrl . '/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return isset($data['status']) && $data['status'] === 'healthy';
            }
            return false;
        } catch (Exception $e) { return false; }
    }
    
    public function predictIntent($question) {
        if (!$this->enabled || empty(trim($question))) return null;
        try {
            $ch = curl_init($this->apiUrl . '/predict');
            $payload = json_encode(['question' => $question], JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode !== 200) return null;
            $result = json_decode($response, true);
            return $result && isset($result['intent']) ? ['intent' => $result['intent'], 'confidence' => floatval($result['confidence']), 'alternatives' => $result['alternatives'] ?? []] : null;
        } catch (Exception $e) { return null; }
    }
    
    public function isEnabled() { return $this->enabled; }
    
    public function mapIntentToCategory($intent) {
        $mapping = ['ask_tuition' => 'tuition', 'ask_staff' => 'staff', 'ask_admission' => 'admission', 'ask_loan' => 'loan', 'ask_department' => 'department', 'ask_facility' => 'facility', 'ask_grade' => 'grade', 'ask_news' => 'news', 'ask_contact' => 'contact', 'other' => null];
        return $mapping[$intent] ?? null;
    }
}
