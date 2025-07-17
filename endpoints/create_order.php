<?php
/**
 * Entrée de paiement pour Dhru Fusion Pro via Orange Money Guinée
 */

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/../create_order_debug.log');

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../api_keys.php';
require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/../OrderModel.php';
require_once __DIR__ . '/../OrangeMoneyPayment.php';

// Définir les constantes
define('USD_TO_GNF_RATE', 8650);
define('MIN_AMOUNT_GNF', 100);
define('MAX_AMOUNT_GNF', 5000000);
define('MAX_INPUT_SIZE', 10240);

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

function convertUsdToGnf($amountUsd) {
    return round($amountUsd * USD_TO_GNF_RATE);
}

function validateAndSanitizeInput($input, $type = 'string', $maxLength = 255) {
    if ($input === null) return null;
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) ?: null;
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) ?: null;
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : 0;
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : 0.0;
        default:
            return substr(strip_tags($input), 0, $maxLength);
    }
}

error_log("--- create_order.php: Début du traitement ---");
error_log("Requête HTTP_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("Requête URI: " . $_SERVER['REQUEST_URI']);

$headers = getallheaders();
$apiKey = $headers['X-Api-Key'] ?? $headers['X-API-KEY'] ?? null;
error_log("API Key reçue: " . ($apiKey ? substr($apiKey, 0, 5) . '...' : 'Non fournie'));

// Validation de la clé API
validateApiKey();

$allowedOrigins = [
    'https://gsmeasytech.dhrufusion.in',
    'https://gsmeasytech.dhrufusion.net',
    'https://tty.yqp.mybluehost.me'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && !in_array($origin, $allowedOrigins)) {
    error_log("CSRF: Origin non autorisé - $origin");
}

$rawInput = file_get_contents('php://input', false, null, 0, MAX_INPUT_SIZE);
error_log("Raw input (payload): " . $rawInput);

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
    error_log("Tentative de lecture depuis \$_POST: " . print_r($input, true));
}

if (empty($input)) {
    error_log("Erreur: Payload vide.");
    output('error', 'Request payload cannot be empty.', ['error_code' => 'NO_DATA'], 400);
}

$requiredFields = ['amount', 'custom_id', 'customer_name', 'customer_email', 'ipn_url', 'success_url', 'fail_url'];
$missing = [];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        $missing[] = $field;
    }
}
if (!empty($missing)) {
    error_log("Champs manquants: " . implode(', ', $missing));
    output('error', 'Missing: ' . implode(', ', $missing), ['error_code' => 'MISSING_FIELDS'], 400);
}

$amountUsd = validateAndSanitizeInput($input['amount'], 'float');
if ($amountUsd <= 0 || $amountUsd > 10000) {
    error_log("Montant USD invalide: $amountUsd");
    output('error', 'Invalid amount', ['error_code' => 'INVALID_AMOUNT'], 400);
}

$amountGnf = convertUsdToGnf($amountUsd);
if ($amountGnf < MIN_AMOUNT_GNF || $amountGnf > MAX_AMOUNT_GNF) {
    error_log("Montant GNF hors limites: $amountGnf");
    output('error', 'Amount out of bounds', ['error_code' => 'AMOUNT_OUT_OF_LIMITS'], 400);
}

try {
    $orderModel = new OrderModel();
    $orderData = [
        'amount' => $amountUsd,
        'amount_gnf' => $amountGnf,
        'currency_code' => 'USD',
        'currency_om' => 'GNF',
        'exchange_rate' => USD_TO_GNF_RATE,
        'description' => validateAndSanitizeInput($input['description'] ?? 'Service', 'string', 500),
        'customer_name' => validateAndSanitizeInput($input['customer_name'], 'string', 100),
        'customer_email' => validateAndSanitizeInput($input['customer_email'], 'email') ?: 'client@example.com',
        'custom_id' => validateAndSanitizeInput($input['custom_id'], 'string', 50),
        'ipn_url' => validateAndSanitizeInput($input['ipn_url'], 'url'),
        'success_url' => validateAndSanitizeInput($input['success_url'], 'url'),
        'fail_url' => validateAndSanitizeInput($input['fail_url'], 'url'),
        'order_date' => date('Y-m-d H:i:s'),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
        'referrer' => substr($_SERVER['HTTP_REFERER'] ?? 'direct', 0, 255)
    ];

    $existingOrder = $orderModel->findByCustomIdAndStatus($orderData['custom_id'], 'pending_payment');
    if ($existingOrder && !empty($existingOrder['pay_token'])) {
        error_log("Commande déjà existante détectée : renvoi URL existante");
        output('success', 'Commande déjà existante.', [
            'order_id' => $existingOrder['order_id'],
            'redirect_url' => 'https://mpayment.orange-money.com/sx/mpayment/abstract/' . $existingOrder['pay_token'],
            'pay_token' => $existingOrder['pay_token'],
            'notif_token' => $existingOrder['notif_token']
        ], 200);
    }

    $orderId = $orderModel->createOrder($orderData);
    if (!$orderId) {
        throw new Exception('DB failure');
    }

    error_log("Commande créée en DB avec ID: " . $orderId);

    $orange_order_id = $orderData['custom_id'] . '_' . substr(time(), -6) . str_pad(mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
    $orangeData = [
        'amount' => $amountGnf,
        'currency_code' => 'GNF',
        'custom_id' => $orange_order_id,
        'description' => $orderData['description'],
        'customer_name' => $orderData['customer_name'],
        'customer_email' => $orderData['customer_email'],
        'success_url' => $orderData['success_url'],
        'fail_url' => $orderData['fail_url']
    ];

    error_log("Données envoyées à Orange Money: " . json_encode($orangeData));

    $orangeMoney = new OrangeMoneyPayment();
    $payment = null;
    for ($i = 1; $i <= 3; $i++) {
        try {
            $payment = $orangeMoney->createPayment($orangeData);
            if (!is_array($payment)) {
                $payment = json_decode(json_encode($payment), true);
            }
            error_log("Réponse Orange Money (tentative $i): " . print_r($payment, true));
            break;
        } catch (Exception $e) {
            error_log("Erreur Orange Money (tentative $i): " . $e->getMessage());
            if ($i === 3) throw $e;
            sleep($i);
        }
    }

    $paymentUrl = $payment['checkout_url'] ?? null;
    if (empty($paymentUrl)) {
        output('error', 'Lien de paiement Orange Money non reçu.', ['error_code' => 'NO_PAYMENT_URL'], 500);
    }

    $orderModel->updateOrder($orderId, [
        'pay_token' => $payment['pay_token'] ?? null,
        'notif_token' => $payment['notif_token'] ?? null,
        'order_id_om' => $orange_order_id,
        'status' => 'pending_payment',
        'api_response' => json_encode($payment)
    ]);

    error_log("Commande DB mise à jour avec les tokens Orange Money.");

    // Réponse avec order_id pour que DHRU charge Payment Checkout SendBox
    $responseData = [
        'status' => 'success',
        'order_id' => $orderId,
        'next_action' => 'display_checkout',
        'checkout_url' => '/Payment Checkout SendBox.html?order_id=' . $orderId // Ajuste le chemin si nécessaire
    ];
    error_log("=== ✅ Réponse envoyée à Dhru ===\n" . json_encode($responseData));

    output('success', 'Commande créée avec succès.', $responseData, 201);

} catch (Exception $e) {
    error_log("Erreur fatale: " . $e->getMessage());
    output('error', 'Erreur serveur: ' . $e->getMessage(), ['error_code' => 'PAYMENT_ERROR'], 500);
}

error_log("--- create_order.php: Fin du traitement ---");
?>