<?php
/**
 * AJAX: Récupérer les statistiques pour les dashboards
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

$period = $_GET['period'] ?? 'month'; // week, month, year

try {
    $stats = [];
    $current_user = get_current_user_details();
    
    switch ($_SESSION['user_role']) {
        case 'admin':
        case 'secretaire':
            // Statistiques globales
            $stats = [
                'total_stagiaires' => $pdo->query("SELECT COUNT(*) FROM stagiaires")->fetchColumn(),
                'stagiaires_actifs' => $pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'actif'")->fetchColumn(),
                'en_attente' => $pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'en_attente'")->fetchColumn(),
                'total_encadreurs' => $pdo->query("SELECT COUNT(*) FROM encadreurs")->fetchColumn(),
                'encadreurs_disponibles' => $pdo->query("SELECT COUNT(*) FROM encadreurs WHERE disponible = 1")->fetchColumn(),
                'taches_en_cours' => $pdo->query("SELECT COUNT(*) FROM taches WHERE statut NOT IN ('termine', 'annule')")->fetchColumn(),
                'taches_terminees' => $pdo->query("SELECT COUNT(*) FROM taches WHERE statut = 'termine'")->fetchColumn(),
                'messages_non_lus' => $pdo->query("SELECT COUNT(*) FROM messages WHERE id_destinataire = ? AND est_lu = 0", [$_SESSION['user_id']])->fetchColumn()
            ];
            break;
            
        case 'encadreur_pro':
        case 'encadreur_acro':
            // Statistiques pour l'encadreur
            $stats = [
                'stagiaires_actifs' => $pdo->prepare("
                    SELECT COUNT(*) FROM relations_encadrement 
                    WHERE id_encadreur = ? AND statut = 'active'
                ")->execute([$_SESSION['user_id']])->fetchColumn(),
                
                'taches_assignees' => $pdo->prepare("
                    SELECT COUNT(*) FROM taches 
                    WHERE id_encadreur = ? AND statut NOT IN ('termine', 'annule')
                ")->execute([$_SESSION['user_id']])->fetchColumn(),
                
                'taches_terminees' => $pdo->prepare("
                    SELECT COUNT(*) FROM taches 
                    WHERE id_encadreur = ? AND statut = 'termine'
                ")->execute([$_SESSION['user_id']])->fetchColumn(),
                
                'evaluations_a_faire' => $pdo->prepare("
                    SELECT COUNT(*) FROM relations_encadrement r
                    LEFT JOIN evaluations e ON r.id_stagiaire = e.id_stagiaire
                    WHERE r.id_encadreur = ? AND r.statut = 'active' AND e.id_evaluation IS NULL
                ")->execute([$_SESSION['user_id']])->fetchColumn()
            ];
            break;
            
        case 'stagiaire':
            // Statistiques pour le stagiaire
            $stats = [
                'taches_a_faire' => $pdo->prepare("
                    SELECT COUNT(*) FROM taches 
                    WHERE id_stagiaire = ? AND statut = 'a_faire'
                ")->execute([$_SESSION['user_id']])->fetchColumn(),
                
                'taches_en_cours' => $pdo->prepare("
                    SELECT COUNT(*) FROM taches 
                    WHERE id_stagiaire = ? AND statut = 'en_cours'
                ")->execute([$_SESSION['user_id']])->fetchColumn(),
                
                'taches_terminees' => $pdo->prepare("
                    SELECT COUNT(*) FROM taches 
                    WHERE id_stagiaire = ? AND statut = 'termine'
                ")->execute([$_SESSION['user_id']])->fetchColumn(),
                
                'documents_uploades' => $pdo->prepare("
                    SELECT COUNT(*) FROM documents 
                    WHERE id_utilisateur = ?
                ")->execute([$_SESSION['user_id']])->fetchColumn(),
                
                'rendez_vous' => $pdo->prepare("
                    SELECT COUNT(*) FROM rendez_vous 
                    WHERE id_stagiaire = ? AND date_rdv >= NOW()
                ")->execute([$_SESSION['user_id']])->fetchColumn()
            ];
            break;
    }
    
    // Graphique d'évolution
    $graphData = [];
    
    switch ($period) {
        case 'week':
            // Derniers 7 jours
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $label = date('d/m', strtotime("-$i days"));
                
                $graphData['labels'][] = $label;
                $graphData['connexions'][] = $pdo->prepare("
                    SELECT COUNT(*) FROM logs_activite 
                    WHERE DATE(date_action) = ? AND type_action = 'connexion'
                ")->execute([$date])->fetchColumn();
            }
            break;
            
        case 'month':
            // Derniers 30 jours
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $label = date('d/m', strtotime("-$i days"));
                
                $graphData['labels'][] = $label;
                $graphData['connexions'][] = $pdo->prepare("
                    SELECT COUNT(*) FROM logs_activite 
                    WHERE DATE(date_action) = ? AND type_action = 'connexion'
                ")->execute([$date])->fetchColumn();
            }
            break;
            
        case 'year':
            // 12 derniers mois
            for ($i = 11; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $label = date('M Y', strtotime("-$i months"));
                
                $graphData['labels'][] = $label;
                $graphData['inscriptions'][] = $pdo->prepare("
                    SELECT COUNT(*) FROM utilisateurs 
                    WHERE DATE_FORMAT(date_creation, '%Y-%m') = ?
                ")->execute([$month])->fetchColumn();
            }
            break;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'graph' => $graphData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des statistiques'
    ]);
}
?>