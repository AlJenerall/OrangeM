<?php

require_once __DIR__ . '/database.php';

class OrderModel {
    private $conn;

    public function __construct($dbConfig = []) {
        // Utilise la configuration globale si rien n’est passé
        if (empty($dbConfig) && isset($GLOBALS['config'])) {
            $dbConfig = $GLOBALS['config'];
        }
        $db = new Database($dbConfig);
        $this->conn = $db->connect();
    }

    /**
     * Crée une nouvelle commande dans la base de données
     */
    public function createOrder($orderData) {
        $query = "INSERT INTO orders (
            amount, amount_gnf, currency_code, currency_om, exchange_rate,
            description, customer_name, customer_email, custom_id,
            ipn_url, success_url, fail_url, order_date,
            user_ip, user_agent, referrer
        ) VALUES (
            :amount, :amount_gnf, :currency_code, :currency_om, :exchange_rate,
            :description, :customer_name, :customer_email, :custom_id,
            :ipn_url, :success_url, :fail_url, :order_date,
            :user_ip, :user_agent, :referrer
        )";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':amount', $orderData['amount']);
        $stmt->bindParam(':amount_gnf', $orderData['amount_gnf']);
        $stmt->bindParam(':currency_code', $orderData['currency_code']);
        $stmt->bindParam(':currency_om', $orderData['currency_om']);
        $stmt->bindParam(':exchange_rate', $orderData['exchange_rate']);
        $stmt->bindParam(':description', $orderData['description']);
        $stmt->bindParam(':customer_name', $orderData['customer_name']);
        $stmt->bindParam(':customer_email', $orderData['customer_email']);
        $stmt->bindParam(':custom_id', $orderData['custom_id']);
        $stmt->bindParam(':ipn_url', $orderData['ipn_url']);
        $stmt->bindParam(':success_url', $orderData['success_url']);
        $stmt->bindParam(':fail_url', $orderData['fail_url']);
        $stmt->bindParam(':order_date', $orderData['order_date']);
        $stmt->bindParam(':user_ip', $orderData['user_ip']);
        $stmt->bindParam(':user_agent', $orderData['user_agent']);
        $stmt->bindParam(':referrer', $orderData['referrer']);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Met à jour les données d'une commande existante
     */
    public function updateOrder($orderId, $orderData) {
        $fields = [];
        $params = [];

        foreach ($orderData as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        $fields_str = implode(", ", $fields);
        $query = "UPDATE orders SET $fields_str WHERE order_id = :order_id";
        $params[':order_id'] = $orderId;

        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Récupère une commande par son ID interne
     */
    public function getOrderById($orderId) {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE order_id = :order_id");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour le statut d'une commande à partir du notif_token
     */
    public function updateStatusByNotifToken($notif_token, $status, $transaction_id = null) {
        $stmt = $this->conn->prepare("UPDATE orders SET status = ?, transaction_id = ? WHERE notif_token = ?");
        return $stmt->execute([$status, $transaction_id, $notif_token]);
    }

    /**
     * Récupère la dernière commande avec un custom_id donné et un statut spécifique
     */
    public function findByCustomIdAndStatus($customId, $status = 'pending_payment') {
        $query = "SELECT * FROM orders WHERE custom_id = :custom_id AND status = :status ORDER BY order_date DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':custom_id', $customId);
        $stmt->bindParam(':status', $status);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie qu'un notif_token existe dans la base
     */
    public function verifyNotifToken($notifToken) {
        $stmt = $this->conn->prepare('SELECT COUNT(*) FROM orders WHERE notif_token = ?');
        $stmt->execute([$notifToken]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère une commande via son notif_token
     */
    public function getOrderByNotifToken($notifToken) {
        $stmt = $this->conn->prepare('SELECT * FROM orders WHERE notif_token = ? LIMIT 1');
        $stmt->execute([$notifToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
