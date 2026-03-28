<?php
/**
 * API - Vérifier si un utilisateur peut être supprimé
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

header('Content-Type: application/json');

$user_id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? '';

if (!$user_id || !$type) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$response = ['success' => true, 'can_delete' => true, 'message' => ''];

try {
    switch ($type) {
        case 'stagiaire':
            // Vérifier si le stagiaire a des tâches en cours
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM taches WHERE id_stagiaire = ? AND statut != 'termine'");
            $stmt->execute([$user_id]);
            $taches_en_cours = $stmt->fetchColumn();
            
            if ($taches_en_cours > 0) {
                $response['can_delete'] = false;
                $response['message'] = "Ce stagiaire a $taches_en_cours tâche(s) en cours.";
            }
            break;
            
        case 'encadreur':
            // Vérifier si l'encadreur a des stagiaires actifs
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM relations_encadrement WHERE id_encadreur = ? AND statut = 'active'");
            $stmt->execute([$user_id]);
            $stagiaires_actifs = $stmt->fetchColumn();
            
            if ($stagiaires_actifs > 0) {
                $response['can_delete'] = false;
                $response['message'] = "Cet encadreur a $stagiaires_actifs stagiaire(s) actif(s).";
            }
            
            // Vérifier si l'encadreur a des tâches en cours
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM taches WHERE id_encadreur = ? AND statut != 'termine'");
            $stmt->execute([$user_id]);
            $taches_en_cours = $stmt->fetchColumn();
            
            if ($taches_en_cours > 0 && $response['can_delete']) {
                $response['message'] = "Cet encadreur a $taches_en_cours tâche(s) en cours.";
            }
            break;
            
        case 'secretaire':
            // Les secrétaires n'ont pas de contraintes particulières
            break;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>