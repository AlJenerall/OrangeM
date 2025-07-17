<?php

function output($status, $message, $data = null, $httpCode = 200) {
    header('Content-Type: application/json', true, $httpCode);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data ?? [],
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

function validateInputs($inputData, $requiredFields) {
    foreach ($requiredFields as $field) {
        if (empty($inputData[$field])) {
            return "The field '$field' is required.";
        }
    }
    return true;
}

function getValidatedInput() {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        output('error', 'Request payload cannot be empty.', null, 400);
    }
    $decodedInput = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        output('error', 'Invalid JSON payload: ' . json_last_error_msg(), null, 400);
    }
    return $decodedInput;
}

function validateApiKey() {
    global $apiKeys;
    $headers = getallheaders();
    if (!isset($headers['X-Api-Key'])) {
        output('error', 'Api Key token is required in header.', null, 401);
    }
    $token = trim($headers['X-Api-Key']);
    if (!in_array($token, $apiKeys)) {
        output('error', 'Unauthorized. Invalid or expired token.', null, 401);
    }
}

function sendIpnDetailsToDhruFusion($ipn_url, $orderId) {
    $eventPayload = [
        "event" => [
            "type" => "charge:confirmed",
            "data" => [
                "order_id" => $orderId
            ]
        ]
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ipn_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eventPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'status_code' => $httpCode,
        'response' => $response
    ];
}