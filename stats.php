<?php
/**
 * API - Statistiques
 */

require_once 'config.php';

// Vérifier la clé API
$user = checkApiKey();

// Récupérer la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    apiError('Méthode non autorisée', 405);
}

// Récupérer les statistiques demandées
$type = $_GET['type'] ?? 'global';

switch ($type) {
    case 'global':
        getGlobalStats();
        break;
        
    case 'user':
        if (isset($_GET['user_id'])) {
            getUserStats($_GET['user_id']);
        } else {
            apiError('Paramètre user_id requis', 400);
        }
        break;
        
    case 'tasks':
        getTasksStats();
        break;
        
    case 'stagiaires':
        getStagiairesStats();
        break;
        
    case 'encadreurs':
        getEncadreursStats();
        break;
        
    default:
        apiError('Type de statistiques invalide', 400);
}

/**
 * Statistiques globales
 */
function getGlobalStats() {
    global $pdo;
    
    $stats = [
        'utilisateurs' => [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn(),
            'stagiaires' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'stagiaire'")->fetchColumn(),
            'encadreurs' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role LIKE 'encadreur_%'")->fetchColumn(),
            'secretaires' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'secretaire'")->fetchColumn(),
            'admins' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin'")->fetchColumn(),
            'actifs' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE est_actif = 1")->fetchColumn(),
            'bloques' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE est_bloque = 1")->fetchColumn()
        ],
        'stagiaires' => [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM stagiaires")->fetchColumn(),
            'actifs' => (int)$pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'actif'")->fetchColumn(),
            'en_attente' => (int)$pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'en_attente'")->fetchColumn(),
            'termines' => (int)$pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'termine'")->fetchColumn(),
            'abandons' => (int)$pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'abandon'")->fetchColumn()
        ],
        'encadreurs' => [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM encadreurs")->fetchColumn(),
            'disponibles' => (int)$pdo->query("SELECT COUNT(*) FROM encadreurs WHERE disponible = 1")->fetchColumn(),
            'professionnels' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'encadreur_pro'")->fetchColumn(),
            'academiques' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'encadreur_acro'")->fetchColumn(),
            'total_stagiaires_encadres' => (int)$pdo->query("SELECT SUM(stagiaires_actuels) FROM encadreurs")->fetchColumn()
        ],
        'taches' => [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM taches")->fetchColumn(),
            'a_faire' => (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE statut = 'a_faire'")->fetchColumn(),
            'en_cours' => (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE statut = 'en_cours'")->fetchColumn(),
            'terminees' => (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE statut = 'termine'")->fetchColumn(),
            'en_retard' => (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE date_echeance < CURDATE() AND statut != 'termine'")->fetchColumn()
        ],
        'documents' => [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
            'valides' => (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE est_valide = 1")->fetchColumn(),
            'en_attente' => (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE est_valide = 0")->fetchColumn()
        ],
        'attestations' => [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM attestations")->fetchColumn(),
            'mois' => (int)$pdo->query("SELECT COUNT(*) FROM attestations WHERE MONTH(date_emission) = MONTH(NOW())")->fetchColumn()
        ],
        'messages' => [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
            'non_lus' => (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE est_lu = 0")->fetchColumn()
        ]
    ];
    
    apiResponse([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * Statistiques d'un utilisateur
 */
function getUserStats($user_id) {
    global $pdo;
    
    // Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT role FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        apiError('Utilisateur non trouvé', 404);
    }
    
    $stats = [
        'user_id' => $user_id,
        'role' => $user['role']
    ];
    
    switch ($user['role']) {
        case 'stagiaire':
            $stats = array_merge($stats, [
                'taches' => [
                    'total' => (int)$pdo->prepare("SELECT COUNT(*) FROM taches WHERE id_stagiaire = ?")->execute([$user_id])->fetchColumn(),
                    'terminees' => (int)$pdo->prepare("SELECT COUNT(*) FROM taches WHERE id_stagiaire = ? AND statut = 'termine'")->execute([$user_id])->fetchColumn(),
                    'en_cours' => (int)$pdo->prepare("SELECT COUNT(*) FROM taches WHERE id_stagiaire = ? AND statut != 'termine'")->execute([$user_id])->fetchColumn(),
                    'en_retard' => (int)$pdo->prepare("SELECT COUNT(*) FROM taches WHERE id_stagiaire = ? AND date_echeance < CURDATE() AND statut != 'termine'")->execute([$user_id])->fetchColumn()
                ],
                'documents' => [
                    'total' => (int)$pdo->prepare("SELECT COUNT(*) FROM documents WHERE id_stagiaire = ?")->execute([$user_id])->fetchColumn(),
                    'valides' => (int)$pdo->prepare("SELECT COUNT(*) FROM documents WHERE id_stagiaire = ? AND est_valide = 1")->execute([$user_id])->fetchColumn()
                ],
                'rendez_vous' => [
                    'total' => (int)$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE id_stagiaire = ?")->execute([$user_id])->fetchColumn(),
                    'a_venir' => (int)$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE id_stagiaire = ? AND date_rdv >= NOW()")->execute([$user_id])->fetchColumn()
                ],
                'notifications_non_lues' => (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ? AND est_lue = 0")->execute([$user_id])->fetchColumn()
            ]);
            break;
            
        case 'encadreur_pro':
        case 'encadreur_acro':
            $stats = array_merge($stats, [
                'stagiaires' => [
                    'actifs' => (int)$pdo->prepare("SELECT COUNT(*) FROM relations_encadrement WHERE id_encadreur = ? AND statut = 'active'")->execute([$user_id])->fetchColumn(),
                    'total' => (int)$pdo->prepare("SELECT COUNT(*) FROM relations_encadrement WHERE id_encadreur = ?")->execute([$user_id])->fetchColumn()
                ],
                'taches' => [
                    'creees' => (int)$pdo->prepare("SELECT COUNT(*) FROM taches WHERE id_encadreur = ?")->execute([$user_id])->fetchColumn(),
                    'terminees' => (int)$pdo->prepare("SELECT COUNT(*) FROM taches WHERE id_encadreur = ? AND statut = 'termine'")->execute([$user_id])->fetchColumn()
                ],
                'evaluations' => [
                    'faites' => (int)$pdo->prepare("SELECT COUNT(*) FROM evaluations WHERE id_encadreur = ?")->execute([$user_id])->fetchColumn(),
                    'a_faire' => (int)$pdo->prepare("
                        SELECT COUNT(*) FROM relations_encadrement r
                        LEFT JOIN evaluations e ON r.id_stagiaire = e.id_stagiaire AND e.id_encadreur = r.id_encadreur
                        WHERE r.id_encadreur = ? AND r.statut = 'active' AND e.id_evaluation IS NULL
                    ")->execute([$user_id])->fetchColumn()
                ],
                'rendez_vous' => [
                    'total' => (int)$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE id_encadreur = ?")->execute([$user_id])->fetchColumn(),
                    'a_venir' => (int)$pdo->prepare("SELECT COUNT(*) FROM rendez_vous WHERE id_encadreur = ? AND date_rdv >= NOW()")->execute([$user_id])->fetchColumn()
                ],
                'notifications_non_lues' => (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ? AND est_lue = 0")->execute([$user_id])->fetchColumn()
            ]);
            break;
    }
    
    apiResponse([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * Statistiques des tâches
 */
function getTasksStats() {
    global $pdo;
    
    $periode = $_GET['periode'] ?? 'mois';
    
    $stats = [
        'par_statut' => [
            'labels' => ['À faire', 'En cours', 'Terminé', 'En retard'],
            'data' => [
                (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE statut = 'a_faire'")->fetchColumn(),
                (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE statut = 'en_cours'")->fetchColumn(),
                (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE statut = 'termine'")->fetchColumn(),
                (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE date_echeance < CURDATE() AND statut != 'termine'")->fetchColumn()
            ]
        ],
        'par_priorite' => [
            'labels' => ['Basse', 'Moyenne', 'Haute', 'Urgente'],
            'data' => [
                (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE priorite = 'basse'")->fetchColumn(),
                (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE priorite = 'moyenne'")->fetchColumn(),
                (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE priorite = 'haute'")->fetchColumn(),
                (int)$pdo->query("SELECT COUNT(*) FROM taches WHERE priorite = 'urgente'")->fetchColumn()
            ]
        ]
    ];
    
    // Évolution selon la période
    if ($periode === 'mois') {
        $evolution = $pdo->query("
            SELECT 
                DATE(date_creation) as date,
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termines
            FROM taches
            WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(date_creation)
            ORDER BY date
        ")->fetchAll();
    } elseif ($periode === 'semaine') {
        $evolution = $pdo->query("
            SELECT 
                DATE(date_creation) as date,
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termines
            FROM taches
            WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(date_creation)
            ORDER BY date
        ")->fetchAll();
    } else {
        $evolution = $pdo->query("
            SELECT 
                DATE_FORMAT(date_creation, '%Y-%m') as mois,
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termines
            FROM taches
            WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(date_creation, '%Y-%m')
            ORDER BY mois
        ")->fetchAll();
    }
    
    $stats['evolution'] = $evolution;
    
    apiResponse([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * Statistiques des stagiaires
 */
function getStagiairesStats() {
    global $pdo;
    
    $stats = [
        'par_filiere' => $pdo->query("
            SELECT filiere, COUNT(*) as total
            FROM stagiaires
            WHERE filiere IS NOT NULL
            GROUP BY filiere
            ORDER BY total DESC
        ")->fetchAll(),
        
        'par_niveau' => $pdo->query("
            SELECT niveau_etude, COUNT(*) as total
            FROM stagiaires
            WHERE niveau_etude IS NOT NULL
            GROUP BY niveau_etude
            ORDER BY total DESC
        ")->fetchAll(),
        
        'par_statut' => $pdo->query("
            SELECT statut_inscription, COUNT(*) as total
            FROM stagiaires
            GROUP BY statut_inscription
        ")->fetchAll(),
        
        'evolution' => $pdo->query("
            SELECT 
                DATE_FORMAT(date_debut, '%Y-%m') as mois,
                COUNT(*) as nouveaux,
                SUM(CASE WHEN statut_inscription = 'termine' THEN 1 ELSE 0 END) as termines
            FROM stagiaires
            WHERE date_debut >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(date_debut, '%Y-%m')
            ORDER BY mois
        ")->fetchAll()
    ];
    
    apiResponse([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * Statistiques des encadreurs
 */
function getEncadreursStats() {
    global $pdo;
    
    $stats = [
        'par_type' => [
            'professionnels' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'encadreur_pro'")->fetchColumn(),
            'academiques' => (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'encadreur_acro'")->fetchColumn()
        ],
        
        'par_specialite' => $pdo->query("
            SELECT specialite, COUNT(*) as total
            FROM encadreurs
            WHERE specialite IS NOT NULL
            GROUP BY specialite
            ORDER BY total DESC
            LIMIT 10
        ")->fetchAll(),
        
        'top_encadreurs' => $pdo->query("
            SELECT 
                u.id_utilisateur,
                u.nom,
                u.prenom,
                e.profession,
                e.entreprise,
                COUNT(r.id_stagiaire) as nb_stagiaires,
                (SELECT COUNT(*) FROM taches WHERE id_encadreur = e.id_encadreur) as nb_taches
            FROM encadreurs e
            JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
            LEFT JOIN relations_encadrement r ON e.id_encadreur = r.id_encadreur AND r.statut = 'active'
            GROUP BY e.id_encadreur
            ORDER BY nb_stagiaires DESC
            LIMIT 10
        ")->fetchAll()
    ];
    
    apiResponse([
        'success' => true,
        'data' => $stats
    ]);
}
?>