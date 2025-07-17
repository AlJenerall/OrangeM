    <?php
    // Fichier IPN Orange Money
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/ipn_debug.log'); // log dans un fichier local
    error_reporting(E_ALL);

    require_once 'api_keys.php';
    require_once 'database.php';
    require_once 'OrderModel.php';

    // Log brut
    $rawInput = file_get_contents('php://input');
    error_log("=== NOTIFICATION ORANGE MONEY REÇUE ===");
    error_log("Raw input: " . $rawInput);

    // Décodage JSON
    $data = json_decode($rawInput, true);

    if (is_array($data)) {
        $status = $data['status'] ?? 'UNKNOWN';
        $notif_token = $data['notif_token'] ?? '';
        $txnid = $data['txnid'] ?? '';

        error_log("Données IPN: " . print_r($data, true));

        // Exemple de mise à jour de l'ordre
        if (!empty($notif_token)) {
            $orderModel = new OrderModel();
            if ($status === 'SUCCESS') {
                $orderModel->updateStatusByNotifToken($notif_token, 'paid', $txnid);
            } elseif ($status === 'FAILED') {
                $orderModel->updateStatusByNotifToken($notif_token, 'failed', $txnid);
            }
        } else {
            error_log("Aucun notif_token fourni !");
        }

        http_response_code(200);
        echo 'OK';
    } else {
        error_log("Erreur de décodage JSON !");
        http_response_code(400);
        echo 'Invalid JSON';
    }
    ?>
    