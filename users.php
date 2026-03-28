<?php
/**
 * API - Gestion des utilisateurs
 */

require_once 'config.php';

// Vérifier la clé API
$user = checkApiKey();

// Récupérer la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Récupérer la liste des utilisateurs ou un utilisateur spécifique
        if (isset($_GET['id'])) {
            getUser($_GET['id']);
        } else {
            getUsers();
        }
        break;
        
    case 'POST':
        // Créer un nouvel utilisateur
        createUser();
        break;
        
    case 'PUT':
        // Mettre à jour un utilisateur
        if (isset($_GET['id'])) {
            updateUser($_GET['id']);
        } else {
            apiError('ID utilisateur requis', 400);
        }
        break;
        
    case 'DELETE':
        // Supprimer un utilisateur
        if (isset($_GET['id'])) {
            deleteUser($_GET['id']);
        } else {
            apiError('ID utilisateur requis', 400);
        }
        break;
        
    default:
        apiError('Méthode non autorisée', 405);
}

/**
 * Récupérer la liste des utilisateurs
 */
function getUsers() {
    global $pdo;
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $role = $_GET['role'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($role)) {
        $where[] = "role = ?";
        $params[] = $role;
    }
    
    if (!empty($search)) {
        $where[] = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Compter le total
    $countSql = "SELECT COUNT(*) FROM utilisateurs WHERE $whereClause";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Récupérer les utilisateurs
    $sql = "
        SELECT 
            id_utilisateur,
            nom,
            prenom,
            email,
            telephone,
            adresse,
            photo,
            role,
            est_actif,
            est_bloque,
            date_creation,
            date_modification
        FROM utilisateurs
        WHERE $whereClause
        ORDER BY date_creation DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Ajouter les informations spécifiques selon le rôle
    foreach ($users as &$u) {
        if ($u['role'] === 'stagiaire') {
            $stmt = $pdo->prepare("
                SELECT filiere, niveau_etude, etablissement, theme_stage, date_debut, date_fin, statut_inscription
                FROM stagiaires WHERE id_stagiaire = ?
            ");
            $stmt->execute([$u['id_utilisateur']]);
            $u['details'] = $stmt->fetch();
        } elseif (strpos($u['role'], 'encadreur') !== false) {
            $stmt = $pdo->prepare("
                SELECT profession, specialite, entreprise, bio, disponible, stagiaires_actuels, max_stagiaires
                FROM encadreurs WHERE id_encadreur = ?
            ");
            $stmt->execute([$u['id_utilisateur']]);
            $u['details'] = $stmt->fetch();
        }
    }
    
    apiResponse([
        'success' => true,
        'data' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Récupérer un utilisateur spécifique
 */
function getUser($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            id_utilisateur,
            nom,
            prenom,
            email,
            telephone,
            adresse,
            photo,
            role,
            est_actif,
            est_bloque,
            date_creation,
            date_modification
        FROM utilisateurs
        WHERE id_utilisateur = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        apiError('Utilisateur non trouvé', 404);
    }
    
    // Ajouter les informations spécifiques selon le rôle
    if ($user['role'] === 'stagiaire') {
        $stmt = $pdo->prepare("
            SELECT * FROM stagiaires WHERE id_stagiaire = ?
        ");
        $stmt->execute([$id]);
        $user['details'] = $stmt->fetch();
    } elseif (strpos($user['role'], 'encadreur') !== false) {
        $stmt = $pdo->prepare("
            SELECT * FROM encadreurs WHERE id_encadreur = ?
        ");
        $stmt->execute([$id]);
        $user['details'] = $stmt->fetch();
    } elseif ($user['role'] === 'secretaire') {
        $stmt = $pdo->prepare("
            SELECT * FROM secretaires WHERE id_secretaire = ?
        ");
        $stmt->execute([$id]);
        $user['details'] = $stmt->fetch();
    }
    
    apiResponse([
        'success' => true,
        'data' => $user
    ]);
}

/**
 * Créer un utilisateur
 */
function createUser() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        apiError('Données invalides', 400);
    }
    
    validateRequired($data, ['nom', 'prenom', 'email', 'password', 'role']);
    
    // Vérifier si l'email existe déjà
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetchColumn() > 0) {
        apiError('Cet email est déjà utilisé', 409);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Hasher le mot de passe
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insérer l'utilisateur
        $stmt = $pdo->prepare("
            INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, telephone, adresse, photo, role, ip_inscription)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['email'],
            $hashed_password,
            $data['telephone'] ?? null,
            $data['adresse'] ?? null,
            $data['photo'] ?? 'default-avatar.png',
            $data['role'],
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Ajouter les informations spécifiques selon le rôle
        switch ($data['role']) {
            case 'stagiaire':
                $stmt = $pdo->prepare("
                    INSERT INTO stagiaires (id_stagiaire, filiere, niveau_etude, etablissement, theme_stage, date_debut, date_fin, statut_inscription)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $data['filiere'] ?? '',
                    $data['niveau_etude'] ?? '',
                    $data['etablissement'] ?? null,
                    $data['theme_stage'] ?? '',
                    $data['date_debut'] ?? null,
                    $data['date_fin'] ?? null,
                    $data['statut_inscription'] ?? 'en_attente'
                ]);
                break;
                
            case 'encadreur_pro':
            case 'encadreur_acro':
                $stmt = $pdo->prepare("
                    INSERT INTO encadreurs (id_encadreur, profession, specialite, entreprise, bio, disponible, max_stagiaires)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $data['profession'] ?? '',
                    $data['specialite'] ?? '',
                    $data['entreprise'] ?? '',
                    $data['bio'] ?? null,
                    $data['disponible'] ?? 1,
                    $data['max_stagiaires'] ?? 5
                ]);
                break;
                
            case 'secretaire':
                $stmt = $pdo->prepare("
                    INSERT INTO secretaires (id_secretaire, service, matricule)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $data['service'] ?? null,
                    $data['matricule'] ?? null
                ]);
                break;
        }
        
        $pdo->commit();
        
        apiResponse([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => ['id' => $user_id]
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        apiError('Erreur lors de la création : ' . $e->getMessage(), 500);
    }
}

/**
 * Mettre à jour un utilisateur
 */
function updateUser($id) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        apiError('Données invalides', 400);
    }
    
    // Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        apiError('Utilisateur non trouvé', 404);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Mettre à jour l'utilisateur
        $updates = [];
        $params = [];
        
        $fields = ['nom', 'prenom', 'telephone', 'adresse', 'photo', 'est_actif', 'est_bloque', 'raison_blocage'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (!empty($updates)) {
            $params[] = $id;
            $sql = "UPDATE utilisateurs SET " . implode(', ', $updates) . " WHERE id_utilisateur = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Mettre à jour les informations spécifiques selon le rôle
        switch ($user['role']) {
            case 'stagiaire':
                $updates = [];
                $params = [];
                
                $fields = ['filiere', 'niveau_etude', 'etablissement', 'theme_stage', 'date_debut', 'date_fin', 'statut_inscription'];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $id;
                    $sql = "UPDATE stagiaires SET " . implode(', ', $updates) . " WHERE id_stagiaire = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                break;
                
            case 'encadreur_pro':
            case 'encadreur_acro':
                $updates = [];
                $params = [];
                
                $fields = ['profession', 'specialite', 'entreprise', 'bio', 'disponible', 'max_stagiaires'];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $id;
                    $sql = "UPDATE encadreurs SET " . implode(', ', $updates) . " WHERE id_encadreur = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                break;
                
            case 'secretaire':
                $updates = [];
                $params = [];
                
                $fields = ['service', 'matricule'];
                foreach ($fields as $field) {
                    if (isset($data[$field])) {
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $id;
                    $sql = "UPDATE secretaires SET " . implode(', ', $updates) . " WHERE id_secretaire = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                break;
        }
        
        $pdo->commit();
        
        apiResponse([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        apiError('Erreur lors de la mise à jour : ' . $e->getMessage(), 500);
    }
}

/**
 * Supprimer un utilisateur
 */
function deleteUser($id) {
    global $pdo;
    
    // Vérifier si l'utilisateur existe
    $stmt = $pdo->prepare("SELECT nom, prenom FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        apiError('Utilisateur non trouvé', 404);
    }
    
    try {
        // Supprimer l'utilisateur (les contraintes CASCADE feront le reste)
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([$id]);
        
        apiResponse([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès'
        ]);
        
    } catch (Exception $e) {
        apiError('Erreur lors de la suppression : ' . $e->getMessage(), 500);
    }
}
?>