<?php
/**
 * AJAX: Récupérer la liste des encadreurs
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

$filiere = $_GET['filiere'] ?? '';
$search = $_GET['search'] ?? '';

try {
    $query = "
        SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.photo,
               e.profession, e.specialite, e.entreprise, e.disponible,
               e.stagiaires_actuels, e.max_stagiaires
        FROM utilisateurs u
        JOIN encadreurs e ON u.id_utilisateur = e.id_encadreur
        WHERE u.role IN ('encadreur_pro', 'encadreur_acro')
        AND u.est_actif = 1
    ";
    
    $params = [];
    
    if (!empty($filiere)) {
        $query .= " AND e.specialite LIKE ?";
        $params[] = "%$filiere%";
    }
    
    if (!empty($search)) {
        $query .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR e.profession LIKE ? OR e.specialite LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $query .= " ORDER BY e.disponible DESC, u.nom ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $encadreurs = $stmt->fetchAll();
    
    // Formater les données
    foreach ($encadreurs as &$encadreur) {
        $encadreur['photo_url'] = getPhotoUrl($encadreur['photo']);
        $encadreur['nom_complet'] = $encadreur['prenom'] . ' ' . $encadreur['nom'];
        $encadreur['places_disponibles'] = $encadreur['max_stagiaires'] - $encadreur['stagiaires_actuels'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $encadreurs
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des encadreurs'
    ]);
}
?>