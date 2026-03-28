<?php
/**
 * AJAX: Recherche d'utilisateurs
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier la connexion
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$search = $_GET['q'] ?? '';
$role = $_GET['role'] ?? '';
$limit = (int)($_GET['limit'] ?? 10);

if (empty($search) || strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $query = "
        SELECT id_utilisateur, nom, prenom, email, role, photo
        FROM utilisateurs
        WHERE (nom LIKE ? OR prenom LIKE ? OR email LIKE ?)
        AND est_actif = 1
    ";
    
    $params = ["%$search%", "%$search%", "%$search%"];
    
    if (!empty($role)) {
        $query .= " AND role = ?";
        $params[] = $role;
    }
    
    $query .= " ORDER BY nom, prenom LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Formater les résultats
    foreach ($users as &$user) {
        $user['text'] = $user['prenom'] . ' ' . $user['nom'] . ' (' . get_role_label($user['role']) . ')';
        $user['photo_url'] = getPhotoUrl($user['photo']);
        $user['role_label'] = get_role_label($user['role']);
    }
    
    echo json_encode([
        'results' => $users,
        'pagination' => ['more' => false]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la recherche']);
}
?>