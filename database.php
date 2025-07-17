<?php
/**
 * Classe Database - Gère la connexion à SQLite ou MySQL
 * Utilisée pour les intégrations Orange Money avec Dhru Fusion Pro
 */

class Database
{
    private $db_type = 'sqlite';

    // Config MySQL par défaut (remplaçable)
    private $host = 'localhost';
    private $db_name = 'ttyyqpmy_MyOm';
    private $username = 'ttyyqpmy_MyOmUser';
    private $password = 'MyOmPassword25@';

    // Emplacement SQLite
    private $sqlite_path = __DIR__ . '/database.sqlite';

    public $conn;

    public function __construct($config = [])
    {
        // Priorité à la config personnalisée ou globale
        $this->db_type     = $config['db_type']     ?? $this->db_type;
        $this->host        = $config['host']        ?? $this->host;
        $this->db_name     = $config['db_name']     ?? $this->db_name;
        $this->username    = $config['username']    ?? $this->username;
        $this->password    = $config['password']    ?? $this->password;
        $this->sqlite_path = $config['sqlite_path'] ?? $this->sqlite_path;
    }

    public function connect()
    {
        if ($this->conn) return $this->conn;

        try {
            if ($this->db_type === 'sqlite') {
                $dsn = 'sqlite:' . $this->sqlite_path;
                $this->conn = new PDO($dsn);
            } else {
                $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password);
            }

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initializeTables();

        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données : " . $e->getMessage());
            throw new Exception("Erreur de connexion BDD");
        }

        return $this->conn;
    }

    private function initializeTables()
    {
        $sql = '';

        if ($this->db_type === 'sqlite') {
            $sql = "
                CREATE TABLE IF NOT EXISTS orders (
                    order_id        INTEGER PRIMARY KEY AUTOINCREMENT,
                    amount          REAL,
                    amount_gnf      REAL,
                    currency_code   TEXT,
                    currency_om     TEXT DEFAULT 'GNF',
                    exchange_rate   REAL,
                    description     TEXT,
                    customer_name   TEXT,
                    customer_email  TEXT,
                    custom_id       TEXT,
                    ipn_url         TEXT,
                    success_url     TEXT,
                    fail_url        TEXT,
                    order_date      TEXT NOT NULL,
                    status          TEXT DEFAULT 'pending',
                    received_amount REAL,
                    transaction_id  TEXT,
                    pay_token       TEXT,
                    notif_token     TEXT,
                    order_id_om     TEXT,
                    api_response    TEXT,
                    received_info   TEXT,
                    ipn_response    TEXT,
                    user_ip         TEXT,
                    user_agent      TEXT,
                    referrer        TEXT
                );
            ";
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS orders (
                    order_id        INT AUTO_INCREMENT PRIMARY KEY,
                    amount          DECIMAL(10,5),
                    amount_gnf      DECIMAL(15,0),
                    currency_code   VARCHAR(10),
                    currency_om     VARCHAR(10) DEFAULT 'GNF',
                    exchange_rate   DECIMAL(10,4),
                    description     TEXT,
                    customer_name   VARCHAR(255),
                    customer_email  VARCHAR(255),
                    custom_id       VARCHAR(50),
                    ipn_url         TEXT,
                    success_url     TEXT,
                    fail_url        TEXT,
                    order_date      DATETIME NOT NULL,
                    status          VARCHAR(50) DEFAULT 'pending',
                    received_amount DECIMAL(10,5),
                    transaction_id  VARCHAR(255),
                    pay_token       VARCHAR(255),
                    notif_token     VARCHAR(255),
                    order_id_om     VARCHAR(255),
                    api_response    LONGTEXT,
                    received_info   LONGTEXT,
                    ipn_response    LONGTEXT,
                    user_ip         VARCHAR(255),
                    user_agent      TEXT,
                    referrer        TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
        }

        try {
            $this->conn->exec($sql);
            error_log("✅ Table 'orders' vérifiée/créée avec succès");
        } catch (PDOException $e) {
            error_log("❌ Erreur création table 'orders' : " . $e->getMessage());
        }
    }

    /**
     * Permet de tester manuellement la connexion
     */
    public function testConnection()
    {
        try {
            $this->connect();
            return ['status' => 'success', 'message' => 'Connexion à la base de données réussie'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Erreur de connexion : ' . $e->getMessage()];
        }
    }

    /**
     * Fermer proprement la connexion
     */
    public function close()
    {
        $this->conn = null;
    }
}

// 🔧 Config globale dispo partout
$config = [
    'db_type'     => 'sqlite', // ou 'mysql'
    'host'        => 'localhost',
    'db_name'     => 'ttyyqpmy_MyOm',
    'username'    => 'ttyyqpmy_MyOmUser',
    'password'    => 'MyOmPassword25@',
    'sqlite_path' => __DIR__ . '/database.sqlite'
];

// 📦 Objet global
$database = new Database($config);
