<?php
/**
 * API - Gestion des tâches
 */

require_once 'config.php';

// Vérifier la clé API
$user = checkApiKey();

// Récupérer la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Récupérer la liste des tâches ou une tâche spécifique
        if (isset($_GET['id'])) {
            getTask($_GET['id']);
        } else {
            getTasks();
        }
        break;
        
    case 'POST':
        // Créer une nouvelle tâche
        createTask();
        break;
        
    case 'PUT':
        // Mettre à jour une tâche
        if (isset($_GET['id'])) {
            updateTask($_GET['id']);
        } else {
            apiError('ID tâche requis', 400);
        }
        break;
        
    case 'DELETE':
        // Supprimer une tâche
        if (isset($_GET['id'])) {
            deleteTask($_GET['id']);
        } else {
            apiError('ID tâche requis', 400);
        }
        break;
        
    default:
        apiError('Méthode non autorisée', 405);
}

/**
 * Récupérer la liste des tâches
 */
function getTasks() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $stagiaire_id = isset($_GET['stagiaire_id']) ? (int)$_GET['stagiaire_id'] : null;
    $encadreur_id = isset($_GET['encadreur_id']) ? (int)$_GET['encadreur_id'] : null;
    $statut = $_GET['statut'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $where = ["1=1"];
    $params = [];
    
    if ($stagiaire_id) {
        $where[] = "t.id_stagiaire = ?";
        $params[] = $stagiaire_id;
    }
    
    if ($encadreur_id) {
        $where[] = "t.id_encadreur = ?";
        $params[] = $encadreur_id;
    }
    
    if (!empty($statut)) {
        $where[] = "t.statut = ?";
        $params[] = $statut;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM taches t WHERE $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Récupérer les tâches
    $sql = "
        SELECT 
            t.*,
            ue.nom as encadreur_nom,
            ue.prenom as encadreur_prenom,
            ue.email as encadreur_email,
            us.nom as stagiaire_nom,
            us.prenom as stagiaire_prenom,
            us.email as stagiaire_email,
            s.filiere,
            s.theme_stage
        FROM taches t
        JOIN encadreurs e ON t.id_encadreur = e.id_encadreur
        JOIN utilisateurs ue ON e.id_encadreur = ue.id_utilisateur
        JOIN stagiaires s ON t.id_stagiaire = s.id_stagiaire
        JOIN utilisateurs us ON s.id_stagiaire = us.id_utilisateur
        WHERE $whereClause
        ORDER BY 
            CASE 
                WHEN t.statut = 'termine' THEN 2 
                WHEN t.date_echeance < CURDATE() AND t.statut != 'termine' THEN 0
                ELSE 1 
            END,
            t.date_echeance ASC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    apiResponse([
        'success' => true,
        'data' => $tasks,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Récupérer une tâche spécifique
 */
function getTask($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            ue.nom as encadreur_nom,
            ue.prenom as encadreur_prenom,
            ue.email as encadreur_email,
            us.nom as stagiaire_nom,
            us.prenom as stagiaire_prenom,
            us.email as stagiaire_email,
            s.filiere,
            s.theme_stage
        FROM taches t
        JOIN encadreurs e ON t.id_encadreur = e.id_encadreur
        JOIN utilisateurs ue ON e.id_encadreur = ue.id_utilisateur
        JOIN stagiaires s ON t.id_stagiaire = s.id_stagiaire
        JOIN utilisateurs us ON s.id_stagiaire = us.id_utilisateur
        WHERE t.id_tache = ?
    ");
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        apiError('Tâche non trouvée', 404);
    }
    
    apiResponse([
        'success' => true,
        'data' => $task
    ]);
}

/**
 * Créer une tâche
 */
function createTask() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        apiError('Données invalides', 400);
    }
    
    validateRequired($data, ['id_encadreur', 'id_stagiaire', 'titre', 'date_echeance']);
    
    try {
        $pdo->beginTransaction();
        
        // Insérer la tâche
        $stmt = $pdo->prepare("
            INSERT INTO taches (
                id_encadreur, id_stagiaire, titre, description, date_echeance, 
                priorite, statut, date_creation
            ) VALUES (?, ?, ?, ?, ?, ?, 'a_faire', NOW())
        ");
        $stmt->execute([
            $data['id_encadreur'],
            $data['id_stagiaire'],
            $data['titre'],
            $data['description'] ?? null,
            $data['date_echeance'],
            $data['priorite'] ?? 'moyenne'
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        // Créer une notification pour le stagiaire
        create_notification(
            $data['id_stagiaire'],
            'Nouvelle tâche',
            "Une nouvelle tâche vous a été assignée : {$data['titre']}",
            'info',
            "/dashboard/stagiaire/tache-voir.php?id=$task_id"
        );
        
        $pdo->commit();
        
        apiResponse([
            'success' => true,
            'message' => 'Tâche créée avec succès',
            'data' => ['id' => $task_id]
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        apiError('Erreur lors de la création : ' . $e->getMessage(), 500);
    }
}

/**
 * Mettre à jour une tâche
 */
function updateTask($id) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        apiError('Données invalides', 400);
    }
    
    // Vérifier si la tâche existe
    $stmt = $pdo->prepare("SELECT * FROM taches WHERE id_tache = ?");
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        apiError('Tâche non trouvée', 404);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mettre à jour la tâche
        $updates = [];
        $params = [];
        
        $fields = ['titre', 'description', 'date_echeance', 'priorite', 'statut', 'progression', 'commentaires', 'feedback_encadreur'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        // Si le statut devient "termine", mettre à jour la date de réalisation
        if (isset($data['statut']) && $data['statut'] === 'termine' && $task['statut'] !== 'termine') {
            $updates[] = "date_realisation = NOW()";
        }
        
        $updates[] = "date_modification = NOW()";
        
        if (!empty($updates)) {
            $params[] = $id;
            $sql = "UPDATE taches SET " . implode(', ', $updates) . " WHERE id_tache = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Créer une notification si le statut change
        if (isset($data['statut']) && $data['statut'] !== $task['statut']) {
            $destinataire = ($data['statut'] === 'termine') ? $task['id_encadreur'] : $task['id_stagiaire'];
            $message = $data['statut'] === 'termine' 
                ? "La tâche '{$task['titre']}' a été marquée comme terminée"
                : "Le statut de la tâche '{$task['titre']}' a changé : {$data['statut']}";
            
            create_notification(
                $destinataire,
                'Mise à jour de tâche',
                $message,
                'info',
                "/dashboard/" . ($data['statut'] === 'termine' ? 'encadreur' : 'stagiaire') . "/tache-voir.php?id=$id"
            );
        }
        
        $pdo->commit();
        
        apiResponse([
            'success' => true,
            'message' => 'Tâche mise à jour avec succès'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        apiError('Erreur lors de la mise à jour : ' . $e->getMessage(), 500);
    }
}

/**
 * Supprimer une tâche
 */
function deleteTask($id) {
    global $pdo;
    
    // Vérifier si la tâche existe
    $stmt = $pdo->prepare("SELECT titre FROM taches WHERE id_tache = ?");
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        apiError('Tâche non trouvée', 404);
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM taches WHERE id_tache = ?");
        $stmt->execute([$id]);
        
        apiResponse([
            'success' => true,
            'message' => 'Tâche supprimée avec succès'
        ]);
        
    } catch (Exception $e) {
        apiError('Erreur lors de la suppression : ' . $e->getMessage(), 500);
    }
}
?>