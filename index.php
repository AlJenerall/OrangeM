<?php
// Définir la constante ROOTDIR une seule fois
if (!defined('ROOTDIR')) {
    define('ROOTDIR', __DIR__);
}

// Configuration des erreurs et logs
ini_set('display_errors', 0); // Désactiver l'affichage pour la production
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', ROOTDIR . '/checkout_debug.log');

// Fonction pour logger l'accès
function logAccess($action) {
    $timestamp = date('Y-m-d H:i:s');
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'direct';
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    error_log("[$timestamp] Accès à $action | IP: $remoteAddr | User-Agent: $userAgent | Referrer: $referrer | Query: $queryString");
}

$action = $_GET['action'] ?? 'checkout';
switch ($action) {
    case 'create_order':
        require_once ROOTDIR . '/endpoints/create_order.php';
        break;
    case 'ipn':
        require_once ROOTDIR . '/endpoints/ipn.php';
        break;
    case 'get_order':
        require_once ROOTDIR . '/endpoints/get_order.php';
        break;
    default:
        logAccess('checkout.html');
        header('Content-Type: text/html; charset=UTF-8');
        readfile(ROOTDIR . '/checkout.html');
        break;
}
?>