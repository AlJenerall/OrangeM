<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/ipn_debug.log');
error_reporting(E_ALL);

require_once 'api_keys.php';
require_once 'database.php';
require_once 'OrderModel.php';

error_log("===== 📥 RÉCEPTION IPN ORANGE MONEY =====");
error_log("Méthode HTTP: " . $_SERVER['REQUEST_METHOD']);
error_log("IP appelante: " . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Rejet : méthode non autorisée.");
    http_response_code(405);
    exit('Méthode non autorisée');
}

$headers = getallheaders();
$apiKey = $headers['X-API-KEY'] ?? $headers['X-Api-Key'] ?? null;
if ($apiKey && !in_array($apiKey, $apiKeys)) {
    error_log("Clé API invalide reçue: " . substr($apiKey, 0, 6) . '...');
    http_response_code(403);
    exit('Clé API invalide');
}

$rawInput = file_get_contents('php://input');
error_log("📦 Raw input: " . $rawInput);

$data = json_decode($rawInput, true);
if (!is_array($data)) {
    error_log("⛔ JSON invalide !");
    http_response_code(400);
    exit('Données JSON invalides');
}

// Détecter si c'est un payload initial ou une notification
if (isset($data['custom_id']) && !isset($data['notif_token'])) {
    error_log("ℹ️ Payload initial détecté (pré-notification), ignoré pour l'instant.");
    http_response_code(200);
    echo 'OK'; // Accepter pour éviter des boucles
    exit;
}

$status = strtoupper($data['status'] ?? 'PENDING');
$notif_token = $data['notif_token'] ?? '';
$txnid = $data['txnid'] ?? null;
$amount = $data['amount'] ?? null;

error_log("✅ Données IPN reçues: status=$status | notif_token=$notif_token | txnid=$txnid | amount=$amount");

try {
    $orderModel = new OrderModel();
    if (!$orderModel) {
        throw new Exception("Échec de l'instanciation de OrderModel");
    }
} catch (Exception $e) {
    error_log("❌ Erreur instanciation OrderModel: " . $e->getMessage());
    http_response_code(500);
    exit('Erreur serveur');
}

if (empty($notif_token)) {
    error_log("❌ notif_token manquant !");
    http_response_code(400);
    exit('notif_token requis');
}

if (!$orderModel->verifyNotifToken($notif_token)) {
    error_log("❌ notif_token invalide ou non trouvé: $notif_token");
    http_response_code(400);
    exit('notif_token invalide');
}

$order = $orderModel->getOrderByNotifToken($notif_token);
if (!$order) {
    error_log("❌ Aucune commande trouvée pour notif_token: $notif_token");
    http_response_code(404);
    exit('Commande non trouvée');
}
error_log("IPN URL enregistrée pour cette commande: " . ($order['ipn_url'] ?? 'inconnue'));

if ($amount && $order['amount_gnf'] != $amount) {
    error_log("⚠️ Montant incohérent: attendu {$order['amount_gnf']}, reçu $amount");
}

switch ($status) {
    case 'SUCCESS':
        $orderModel->updateStatusByNotifToken($notif_token, 'paid', $txnid);
        error_log("✅ Paiement confirmé pour notif_token=$notif_token | txnid=$txnid");
        break;

    case 'FAILED':
    case 'CANCELLED':
        $orderModel->updateStatusByNotifToken($notif_token, 'failed', $txnid);
        error_log("❌ Paiement échoué pour notif_token=$notif_token | txnid=$txnid");
        break;

    default:
        error_log("ℹ️ Statut non reconnu ($status) pour notif_token=$notif_token, laissé inchangé");
        break;
}

if (!empty($order['ipn_url'])) {
    $dhruResp = sendIpnDetailsToDhruFusion($order['ipn_url'], $order['order_id']);
    error_log("IPN relay vers Dhru: code={$dhruResp['status_code']} body={$dhruResp['response']}");
}

http_response_code(200);
echo 'OK';
error_log("Réponse 'OK' renvoyée à Orange Money.");