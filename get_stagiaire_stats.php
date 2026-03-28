<?php
/**
 * AJAX - Récupérer les statistiques d'un stagiaire
 */

// Activer l'affichage des erreurs pour le debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Définir l'en-tête JSON
header('Content-Type: application/json');

try {
    // Vérifier que l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non connecté'
        ]);
        exit;
    }
    
    // Vérifier le rôle
    $role = $_SESSION['user_role'];
    if ($role != 'encadreur_pro' && $role != 'encadreur_acro') {
        echo json_encode([
            'success' => false,
            'message' => 'Accès non autorisé. Rôle: ' . $role
        ]);
        exit;
    }
    
    // Récupérer l'ID du stagiaire
    $stagiaire_id = (int)($_GET['id'] ?? 0);
    
    if (!$stagiaire_id) {
        echo json_encode([
            'success' => false,
            'message' => 'ID stagiaire manquant'
        ]);
        exit;
    }
    
    $encadreur_id = $_SESSION['user_id'];
    
    // Vérifier que le stagiaire appartient bien à cet encadreur
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM stagiaires 
        WHERE id_stagiaire = ? AND id_encadreur = ?
    ");
    $stmt->execute([$stagiaire_id, $encadreur_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'êtes pas autorisé à voir les statistiques de ce stagiaire'
        ]);
        exit;
    }
    
    // Vérifier d'abord la structure de la table evaluations pour savoir quelles colonnes existent
    $colonnes_evaluations = [];
    try {
        $stmt = $pdo->query("DESCRIBE evaluations");
        $colonnes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($colonnes as $colonne) {
            $colonnes_evaluations[] = $colonne['Field'];
        }
    } catch (PDOException $e) {
        // Si la table n'existe pas, on continue sans
        $colonnes_evaluations = [];
    }
    
    // Construire les sous-requêtes pour les évaluations en fonction des colonnes disponibles
    $eval_nb = "(SELECT COUNT(*) FROM evaluations WHERE id_stagiaire = s.id_stagiaire) as nb_evaluations";
    $eval_note = "NULL as note_moyenne";
    $eval_recommandation = "NULL as derniere_recommandation";
    
    if (in_array('note', $colonnes_evaluations)) {
        $eval_note = "(SELECT AVG(note) FROM evaluations WHERE id_stagiaire = s.id_stagiaire) as note_moyenne";
    }
    if (in_array('recommandation', $colonnes_evaluations)) {
        $eval_recommandation = "(SELECT recommandation FROM evaluations WHERE id_stagiaire = s.id_stagiaire ORDER BY date_evaluation DESC LIMIT 1) as derniere_recommandation";
    }
    
    // Récupérer les informations du stagiaire
    $sql = "
        SELECT 
            s.id_stagiaire,
            s.date_debut,
            s.date_fin,
            s.statut_inscription,
            u.nom,
            u.prenom,
            u.email,
            u.telephone,
            (SELECT COUNT(*) FROM taches WHERE id_stagiaire = s.id_stagiaire) as nb_taches,
            (SELECT COUNT(*) FROM taches WHERE id_stagiaire = s.id_stagiaire AND statut = 'termine') as taches_terminees,
            (SELECT COUNT(*) FROM taches WHERE id_stagiaire = s.id_stagiaire AND statut NOT IN ('termine', 'annule')) as taches_en_cours,
            (SELECT COUNT(*) FROM taches WHERE id_stagiaire = s.id_stagiaire AND statut = 'annule') as taches_annulees,
            (SELECT COUNT(*) FROM taches WHERE id_stagiaire = s.id_stagiaire AND date_echeance < CURDATE() AND statut != 'termine') as taches_en_retard,
            (SELECT COUNT(*) FROM taches WHERE id_stagiaire = s.id_stagiaire AND date_echeance >= CURDATE() AND statut != 'termine') as taches_a_venir,
            (SELECT AVG(progression) FROM taches WHERE id_stagiaire = s.id_stagiaire) as progression_moyenne,
            (SELECT COUNT(*) FROM documents WHERE id_stagiaire = s.id_stagiaire) as nb_documents,
            (SELECT COUNT(*) FROM documents WHERE id_stagiaire = s.id_stagiaire AND (type_document = 'cv' OR type_document LIKE '%cv%')) as nb_cv,
            (SELECT COUNT(*) FROM documents WHERE id_stagiaire = s.id_stagiaire AND (type_document = 'rapport' OR type_document LIKE '%rapport%')) as nb_rapports,
            (SELECT COUNT(*) FROM rendez_vous WHERE id_stagiaire = s.id_stagiaire) as nb_rdv,
            (SELECT COUNT(*) FROM rendez_vous WHERE id_stagiaire = s.id_stagiaire AND statut = 'confirme') as rdv_confirme,
            (SELECT COUNT(*) FROM rendez_vous WHERE id_stagiaire = s.id_stagiaire AND statut = 'termine') as rdv_termine,
            (SELECT COUNT(*) FROM rendez_vous WHERE id_stagiaire = s.id_stagiaire AND date_rdv > NOW() AND statut = 'confirme') as rdv_a_venir,
            $eval_nb,
            $eval_note,
            $eval_recommandation
        FROM stagiaires s
        INNER JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
        WHERE s.id_stagiaire = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$stagiaire_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stats) {
        echo json_encode([
            'success' => false,
            'message' => 'Stagiaire non trouvé'
        ]);
        exit;
    }
    
    // Calculer le taux de complétion
    $taux_completion = 0;
    if ($stats['nb_taches'] > 0) {
        $taux_completion = round(($stats['taches_terminees'] / $stats['nb_taches']) * 100);
    }
    
    // Calculer les jours
    $jours_restants = 0;
    $jours_ecoules = 0;
    $duree_totale = 0;
    $progression_stage = 0;
    
    if ($stats['date_fin'] && $stats['date_fin'] > date('Y-m-d')) {
        $date_fin = new DateTime($stats['date_fin']);
        $aujourdhui = new DateTime();
        $jours_restants = $date_fin->diff($aujourdhui)->days;
    }
    
    if ($stats['date_debut'] && $stats['date_debut'] <= date('Y-m-d')) {
        $date_debut = new DateTime($stats['date_debut']);
        $aujourdhui = new DateTime();
        $jours_ecoules = $date_debut->diff($aujourdhui)->days;
    }
    
    if ($stats['date_debut'] && $stats['date_fin']) {
        $date_debut = new DateTime($stats['date_debut']);
        $date_fin = new DateTime($stats['date_fin']);
        $duree_totale = $date_debut->diff($date_fin)->days;
        if ($duree_totale > 0) {
            $progression_stage = round(($jours_ecoules / $duree_totale) * 100);
            if ($progression_stage > 100) $progression_stage = 100;
        }
    }
    
    // Déterminer la couleur de la note
    $note_couleur = 'secondary';
    $note_moyenne = $stats['note_moyenne'] ? round($stats['note_moyenne'], 1) : null;
    if ($note_moyenne >= 16) {
        $note_couleur = 'success';
    } elseif ($note_moyenne >= 12) {
        $note_couleur = 'info';
    } elseif ($note_moyenne >= 10) {
        $note_couleur = 'warning';
    } elseif ($note_moyenne > 0) {
        $note_couleur = 'danger';
    }
    
    // Retourner les données
    echo json_encode([
        'success' => true,
        'stats' => [
            'nom' => $stats['prenom'] . ' ' . $stats['nom'],
            'email' => $stats['email'],
            'telephone' => $stats['telephone'] ?? 'Non renseigné',
            'nb_taches' => (int)$stats['nb_taches'],
            'taches_terminees' => (int)$stats['taches_terminees'],
            'taches_en_cours' => (int)$stats['taches_en_cours'],
            'taches_annulees' => (int)$stats['taches_annulees'],
            'taches_en_retard' => (int)$stats['taches_en_retard'],
            'taches_a_venir' => (int)$stats['taches_a_venir'],
            'taux_completion' => $taux_completion,
            'progression_moyenne' => round($stats['progression_moyenne'] ?? 0),
            'nb_documents' => (int)$stats['nb_documents'],
            'nb_cv' => (int)$stats['nb_cv'],
            'nb_rapports' => (int)$stats['nb_rapports'],
            'nb_rdv' => (int)$stats['nb_rdv'],
            'rdv_confirme' => (int)$stats['rdv_confirme'],
            'rdv_termine' => (int)$stats['rdv_termine'],
            'rdv_a_venir' => (int)$stats['rdv_a_venir'],
            'nb_evaluations' => (int)$stats['nb_evaluations'],
            'note_moyenne' => $note_moyenne,
            'note_couleur' => $note_couleur,
            'derniere_recommandation' => $stats['derniere_recommandation'] ?? 'Non évalué',
            'date_debut' => $stats['date_debut'],
            'date_fin' => $stats['date_fin'],
            'jours_restants' => $jours_restants,
            'jours_ecoules' => $jours_ecoules,
            'duree_totale' => $duree_totale,
            'progression_stage' => $progression_stage,
            'statut_inscription' => $stats['statut_inscription'] ?? 'en_attente'
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>