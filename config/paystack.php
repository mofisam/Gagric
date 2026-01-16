<?php
// config/paystack.php - UPDATED VERSION

class PaystackConfig {
    const SECRET_KEY = 'sk_test_41008269e1c6f30a68e89226ebe8bf9628c9e3ae';
    const PUBLIC_KEY = 'pk_test_3d8772ab51c1407f1302d2fffc114220b0b1d9ee';
    const BASE_URL = 'https://api.paystack.co';
}

class PaystackAPI {
    public static function initializeTransaction($email, $amount, $reference) {
        $url = PaystackConfig::BASE_URL . '/transaction/initialize';
        
        $data = [
            'email' => $email,
            'amount' => $amount * 100, // Convert to kobo
            'reference' => $reference,
            'currency' => DEFAULT_CURRENCY,
            'callback_url' => BASE_URL . '/buyer/cart/payment.php' // For redirect fallback
        ];
        
        return self::makeRequest($url, $data);
    }
    
    public static function verifyTransaction($reference) {
        $url = PaystackConfig::BASE_URL . '/transaction/verify/' . $reference;
        return self::makeRequest($url, [], 'GET');
    }
    
    private static function makeRequest($url, $data = [], $method = 'POST') {
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . PaystackConfig::SECRET_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false, // For testing only, remove in production
            CURLOPT_TIMEOUT => 30
        ];
        
        if ($method === 'POST' && !empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("cURL Error: " . curl_error($ch));
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // Log for debugging
        error_log("Paystack HTTP Code: {$httpCode}");
        error_log("Paystack Full Response: " . $response);
        
        return $result;
    }
}
?>