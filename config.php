<?php
/**
 * Configuration de l'API
 */

// Autoriser l'accès depuis n'importe quelle origine (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-API-Key");

// Gérer les requêtes OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Charger la configuration principale
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

// Fonction pour envoyer une réponse JSON
function apiResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Fonction pour envoyer une erreur
function apiError($message, $status = 400, $details = null) {
    $response = [
        'success' => false,
        'error' => $message
    ];
    if ($details) {
        $response['details'] = $details;
    }
    apiResponse($response, $status);
}

// Fonction pour vérifier la clé API
function checkApiKey() {
    $headers = getallheaders();
    $api_key = $headers['X-API-Key'] ?? $_GET['api_key'] ?? '';
    
    if (empty($api_key)) {
        apiError('Clé API requise', 401);
    }
    
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.*, a.niveau_acces 
        FROM utilisateurs u
        JOIN administrateurs a ON u.id_utilisateur = a.id_admin
        WHERE a.api_key = ? AND u.est_actif = 1
    ");
    $stmt->execute([$api_key]);
    $user = $stmt->fetch();
    
    if (!$user) {
        apiError('Clé API invalide', 401);
    }
    
    return $user;
}

// Fonction pour valider les paramètres requis
function validateRequired($params, $required) {
    $missing = [];
    foreach ($required as $field) {
        if (!isset($params[$field]) || empty($params[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        apiError('Paramètres requis manquants : ' . implode(', ', $missing), 400);
    }
}
?>