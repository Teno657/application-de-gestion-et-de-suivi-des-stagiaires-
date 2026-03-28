<?php
/**
 * Ajouter un événement - Secrétaire
 * Version adaptée à votre structure de base de données
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier que l'utilisateur est secrétaire
require_login();
if ($_SESSION['user_role'] != 'secretaire') {
    redirect('../index.php');
}

$page_title = "Ajouter un événement - School-Connection";
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// =============================================
// RÉCUPÉRATION DES STAGIAIRES AVEC LEURS NOMS
// =============================================
$stagiaires = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id_stagiaire, u.nom, u.prenom 
        FROM stagiaires s
        JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute();
    $stagiaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stagiaires = [];
}

// =============================================
// RÉCUPÉRATION DES ENCADREURS AVEC LEURS NOMS
// =============================================
$encadreurs = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.id_encadreur, u.nom, u.prenom 
        FROM encadreurs e
        JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute();
    $encadreurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $encadreurs = [];
}

// =============================================
// DÉTECTION DES COLONNES DE LA TABLE CALENDRIER
// =============================================
$colsCalendrier = [];
try {
    $result = $pdo->query("SHOW COLUMNS FROM calendrier");
    if ($result) {
        $colsCalendrier = $result->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $colsCalendrier = [];
}

$colTitre = in_array('titre', $colsCalendrier) ? 'titre' : (in_array('nom', $colsCalendrier) ? 'nom' : 'titre');
$colDescription = in_array('description', $colsCalendrier) ? 'description' : null;
$colType = in_array('type', $colsCalendrier) ? 'type' : null;
$colDateDebut = in_array('date_debut', $colsCalendrier) ? 'date_debut' : (in_array('date', $colsCalendrier) ? 'date' : null);
$colDateFin = in_array('date_fin', $colsCalendrier) ? 'date_fin' : null;
$colLieu = in_array('lieu', $colsCalendrier) ? 'lieu' : null;
$colCreateur = in_array('createur', $colsCalendrier) ? 'createur' : (in_array('id_createur', $colsCalendrier) ? 'id_createur' : null);
$colStagiaire = in_array('id_stagiaire', $colsCalendrier) ? 'id_stagiaire' : null;
$colEncadreur = in_array('id_encadreur', $colsCalendrier) ? 'id_encadreur' : null;
$colStatut = in_array('statut', $colsCalendrier) ? 'statut' : null;

// =============================================
// FONCTION POUR ENVOYER UNE NOTIFICATION
// =============================================

function envoyerNotification($id_utilisateur, $titre, $message, $type = 'calendar', $lien = null) {
    global $pdo;
    
    if (!$id_utilisateur) return false;
    
    try {
        $colsNotifications = $pdo->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
        
        $insertFields = ['id_utilisateur', 'titre', 'message', 'type_notification', 'est_lue', 'date_creation'];
        $params = [$id_utilisateur, $titre, $message, $type, 0, date('Y-m-d H:i:s')];
        
        if (in_array('lien', $colsNotifications)) {
            $insertFields[] = 'lien';
            $params[] = $lien;
        }
        
        $placeholders = implode(',', array_fill(0, count($insertFields), '?'));
        $sql = "INSERT INTO notifications (" . implode(',', $insertFields) . ") VALUES ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Erreur notification: " . $e->getMessage());
        return false;
    }
}

// =============================================
// TRAITEMENT DU FORMULAIRE
// =============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = cleanInput($_POST['titre'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'autre';
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? null;
    $lieu = cleanInput($_POST['lieu'] ?? '');
    $id_stagiaire = !empty($_POST['id_stagiaire']) ? (int)$_POST['id_stagiaire'] : null;
    $id_encadreur = !empty($_POST['id_encadreur']) ? (int)$_POST['id_encadreur'] : null;
    
    $errors = [];
    if (empty($titre)) $errors[] = "Le titre est requis";
    if (empty($date_debut)) $errors[] = "La date de début est requise";
    
    if (empty($errors)) {
        $insertFields = [];
        $params = [];
        
        if ($colTitre) { $insertFields[] = $colTitre; $params[] = $titre; }
        if ($colDescription) { $insertFields[] = $colDescription; $params[] = $description; }
        if ($colType) { $insertFields[] = $colType; $params[] = $type; }
        if ($colDateDebut) { $insertFields[] = $colDateDebut; $params[] = $date_debut; }
        if ($colDateFin && $date_fin) { $insertFields[] = $colDateFin; $params[] = $date_fin; }
        if ($colLieu) { $insertFields[] = $colLieu; $params[] = $lieu; }
        if ($colCreateur) { $insertFields[] = $colCreateur; $params[] = $user_id; }
        if ($colStagiaire) { $insertFields[] = $colStagiaire; $params[] = $id_stagiaire; }
        if ($colEncadreur) { $insertFields[] = $colEncadreur; $params[] = $id_encadreur; }
        if ($colStatut) { $insertFields[] = $colStatut; $params[] = 'confirme'; }
        
        $placeholders = implode(',', array_fill(0, count($insertFields), '?'));
        $sql = "INSERT INTO calendrier (" . implode(',', $insertFields) . ") VALUES ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            $date_formatee = date('d/m/Y à H:i', strtotime($date_debut));
            $message_notification = "📅 **$titre**\n\n📆 Date: $date_formatee\n📍 Lieu: " . ($lieu ?: 'Non spécifié');
            
            $notifications_envoyees = 0;
            
            // Notifier le stagiaire (l'ID stagiaire = ID utilisateur)
            if ($id_stagiaire) {
                if (envoyerNotification($id_stagiaire, "📅 Nouvel événement: $titre", $message_notification, 'calendar', "dashboard/stagiaire/calendrier.php")) {
                    $notifications_envoyees++;
                }
            }
            
            // Notifier l'encadreur (l'ID encadreur = ID utilisateur)
            if ($id_encadreur) {
                if (envoyerNotification($id_encadreur, "📅 Nouvel événement: $titre", $message_notification, 'calendar', "dashboard/encadreur/calendrier.php")) {
                    $notifications_envoyees++;
                }
            }
            
            $msg_succes = "Événement créé avec succès !";
            if ($notifications_envoyees > 0) {
                $msg_succes .= " Notification envoyée à $notifications_envoyees personne(s).";
            }
            
            $_SESSION['success_message'] = $msg_succes;
            redirect('calendrier.php');
        } else {
            $error = "Erreur lors de la création de l'événement";
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

include '../../includes/header.php';
?>

<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 30px;
    }
    
    .form-container {
        background: white;
        border-radius: 30px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        animation: slideUp 0.5s ease;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 30px;
        color: white;
    }
    
    .form-body {
        padding: 40px;
    }
    
    .form-label {
        font-weight: 600;
        color: #2d3436;
        margin-bottom: 8px;
    }
    
    .form-control, .form-select {
        border: 2px solid #e0e0e0;
        border-radius: 15px;
        padding: 12px 15px;
        transition: all 0.3s;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        outline: none;
    }
    
    .btn-gradient {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-gradient:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        color: white;
    }
    
    .btn-outline {
        border: 2px solid #667eea;
        background: transparent;
        color: #667eea;
        border-radius: 50px;
        padding: 10px 25px;
        font-weight: 500;
        transition: all 0.3s;
    }
    
    .btn-outline:hover {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-color: transparent;
    }
    
    .alert-custom {
        border-radius: 15px;
        border: none;
        padding: 15px 20px;
    }
    
    .info-box {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 15px;
        margin-top: 15px;
        border-left: 4px solid #667eea;
    }
    
    .info-box i {
        color: #667eea;
        margin-right: 10px;
    }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-container">
                <div class="form-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0">
                                <i class="fas fa-plus-circle me-2"></i>Nouvel événement
                            </h2>
                            <p class="mb-0 mt-2 opacity-75">Ajoutez un événement au calendrier</p>
                        </div>
                        <div>
                            <a href="calendrier.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="form-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-custom"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Titre *</label>
                                <input type="text" name="titre" class="form-control" required value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Type *</label>
                                <select name="type" class="form-select" required>
                                    <option value="rendez_vous">📅 Rendez-vous</option>
                                    <option value="soutenance">🎓 Soutenance</option>
                                    <option value="evaluation">⭐ Évaluation</option>
                                    <option value="echeance">⏰ Échéance</option>
                                    <option value="reunion">👥 Réunion</option>
                                    <option value="formation">📚 Formation</option>
                                    <option value="autre">📌 Autre</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date et heure de début *</label>
                                <input type="datetime-local" name="date_debut" class="form-control" required value="<?= htmlspecialchars($_POST['date_debut'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date et heure de fin</label>
                                <input type="datetime-local" name="date_fin" class="form-control" value="<?= htmlspecialchars($_POST['date_fin'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Lieu</label>
                            <input type="text" name="lieu" class="form-control" placeholder="Salle, en ligne, etc..." value="<?= htmlspecialchars($_POST['lieu'] ?? '') ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user-graduate text-primary me-1"></i>Stagiaire concerné
                                </label>
                                <select name="id_stagiaire" class="form-select">
                                    <option value="">-- Aucun --</option>
                                    <?php foreach ($stagiaires as $s): ?>
                                        <option value="<?= $s['id_stagiaire'] ?>" <?= (isset($_POST['id_stagiaire']) && $_POST['id_stagiaire'] == $s['id_stagiaire']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-chalkboard-user text-primary me-1"></i>Encadreur concerné
                                </label>
                                <select name="id_encadreur" class="form-select">
                                    <option value="">-- Aucun --</option>
                                    <?php foreach ($encadreurs as $e): ?>
                                        <option value="<?= $e['id_encadreur'] ?>" <?= (isset($_POST['id_encadreur']) && $_POST['id_encadreur'] == $e['id_encadreur']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-bell"></i>
                            <strong>Notifications</strong><br>
                            <small>Les personnes sélectionnées recevront une notification dans leur cloche 🔔</small>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="calendrier.php" class="btn btn-outline">
                                <i class="fas fa-arrow-left me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-save me-2"></i>Créer l'événement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>