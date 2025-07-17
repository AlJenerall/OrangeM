<?php
/**
 * Classe Orange Money Payment pour Dhru Fusion Pro
 * Configuration PRODUCTION pour Orange Money Guinée
 * Nouvelle application avec merchant_key d405b74d
 */
class OrangeMoneyPayment {
    private $config;
    private $accessToken = null;

    public function __construct() {
        $this->config = [
            'merchant_key'    => '268671bb',  // Nouveau merchant key
            'auth_url'        => 'https://api.orange.com/oauth/v3/token',
            'api_base'        => 'https://api.orange.com/orange-money-webpay/dev/v1', // URL GUINÉE
        ];
    }

    private function getAccessToken() {
        if ($this->accessToken) return $this->accessToken;
        
        // Nouveau Authorization Header
        $authorizationHeader = 'Basic bWNwem5pS0ZjTUlEUlVKSWwzdnB2RVA2czV6QUJXUVY6aHNqUDY2SkdYeFBITEJGSzVkOWxMb09qcHh3OHFwamZ5R3JRWkw2QlBoZUo==';
        
        $ch = curl_init($this->config['auth_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authorizationHeader,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        if (!$response) throw new Exception("Erreur de connexion OAuth2: " . curl_error($ch));
        
        $data = json_decode($response, true);
        curl_close($ch);
        
        if (!isset($data['access_token'])) {
            throw new Exception("Token OAuth2 non reçu: " . $response);
        }
        
        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }

    public function createPayment($orderData) {
        // Vérification des champs requis
        if (empty($orderData['amount']) || empty($orderData['custom_id'])) {
            throw new Exception("Champs requis manquants: amount=" . ($orderData['amount'] ?? 'missing') . ", custom_id=" . ($orderData['custom_id'] ?? 'missing'));
        }
        
        // Validation du montant
        $amount = intval($orderData['amount']);
        if ($amount <= 0) {
            throw new Exception("Le montant doit être supérieur à 0. Montant reçu: " . $orderData['amount']);
        }
        
        $token = $this->getAccessToken();
        
        // Construction de l'URL IPN
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $notif_url = $protocol . "://$_SERVER[HTTP_HOST]/orange_money/ipn.php";
        
        // Payload avec order_id (reçu de create_order.php)
        $body = [
            'merchant_key' => $this->config['merchant_key'],
            'currency'     => 'OUV',  // GUINÉE PRODUCTION
            'order_id'     => (string)$orderData['custom_id'], // ID unique généré par create_order.php
            'amount'       => $amount,
            'return_url'   => $orderData['success_url'] ?: ($protocol . "://$_SERVER[HTTP_HOST]/orange_money/success.php"),
            'cancel_url'   => $orderData['fail_url'] ?: ($protocol . "://$_SERVER[HTTP_HOST]/orange_money/cancel.php"),
            'notif_url'    => $notif_url,
            'lang'         => 'fr',
            'reference'    => (string)$orderData['custom_id'], // Même ID unique
        ];
        
        // Debug
        error_log("=== ORANGE MONEY NOUVELLE APPLICATION ===");
        $fullUrl = $this->config['api_base'] . '/webpayment';
        error_log("URL: " . $fullUrl);
        error_log("Merchant Key: " . $this->config['merchant_key']);
        error_log("Token: " . substr($token, 0, 10) . "...");
        error_log("Order ID unique: " . $orderData['custom_id']);
        error_log("Payload: " . json_encode($body));
        
        // Appel à l'API Orange Money
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (!$response) {
            throw new Exception("Erreur de connexion OrangeMoney: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        // Debug de la réponse
        error_log("Code HTTP: $httpCode");
        error_log("Réponse Orange Money: " . $response);
        
        $data = json_decode($response, true);
        
        if (!isset($data['payment_url'])) {
            $errorMessage = "Echec création paiement Orange Money";
            if (isset($data['message'])) {
                $errorMessage .= ": " . $data['message'];
            }
            if (isset($data['code'])) {
                $errorMessage .= " (Code: " . $data['code'] . ")";
            }
            $errorMessage .= " - HTTP: $httpCode - Réponse: " . $response;
            
            throw new Exception($errorMessage);
        }
        
        return [
            'pay_token'    => $data['pay_token'] ?? null,
            'notif_token'  => $data['notif_token'] ?? null,
            'order_id'     => $body['order_id'],
            'checkout_url' => $data['payment_url']
        ];
    }

    /**
     * Méthode pour vérifier le statut d'un paiement
     */
    public function getPaymentStatus($payToken) {
        $token = $this->getAccessToken();
        
        $ch = curl_init($this->config['api_base'] . '/payment/' . $payToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return null;
    }

    /**
     * Méthode pour traiter les notifications IPN d'Orange Money
     */
    public function processIpnNotification($notificationData) {
        $status = $notificationData['status'] ?? 'unknown';
        $payToken = $notificationData['pay_token'] ?? null;
        $orderId = $notificationData['order_id'] ?? null;
        
        error_log("=== NOTIFICATION ORANGE MONEY ===");
        error_log("Status: $status");
        error_log("Pay Token: $payToken");
        error_log("Order ID: $orderId");
        error_log("Données complètes: " . json_encode($notificationData));
        
        return [
            'status' => $status,
            'pay_token' => $payToken,
            'order_id' => $orderId,
            'processed' => true
        ];
    }
}
?>
