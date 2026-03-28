<?php
/**
 * Modifier un événement - Secrétaire
 * Avec notifications pour les personnes concernées
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier que l'utilisateur est secrétaire
require_login();
if ($_SESSION['user_role'] != 'secretaire') {
    redirect('../index.php');
}

$page_title = "Modifier l'événement - School-Connection";
$user_id = $_SESSION['user_id'];

// Récupérer l'ID de l'événement
$id_evenement = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_evenement) {
    $_SESSION['error_message'] = "Événement non trouvé";
    redirect('calendrier.php');
}

// =============================================
// FONCTION POUR ENVOYER UNE NOTIFICATION
// =============================================

function envoyerNotification($id_utilisateur, $titre, $message, $type = 'calendar', $lien = null) {
    global $pdo;
    
    if (!$id_utilisateur) return false;
    
    try {
        // Vérifier les colonnes de la table notifications
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
// DÉTECTION DES COLONNES DES TABLES
// =============================================

// Détecter les colonnes de la table calendrier
$colsCalendrier = [];
try {
    $result = $pdo->query("SHOW COLUMNS FROM calendrier");
    if ($result) {
        $colsCalendrier = $result->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $colsCalendrier = [];
}

// Déterminer les noms des colonnes
$colId = in_array('id_evenement', $colsCalendrier) ? 'id_evenement' : (in_array('id', $colsCalendrier) ? 'id' : 'id_evenement');
$colTitre = in_array('titre', $colsCalendrier) ? 'titre' : (in_array('nom', $colsCalendrier) ? 'nom' : 'titre');
$colDescription = in_array('description', $colsCalendrier) ? 'description' : null;
$colType = in_array('type', $colsCalendrier) ? 'type' : null;
$colDateDebut = in_array('date_debut', $colsCalendrier) ? 'date_debut' : (in_array('date', $colsCalendrier) ? 'date' : null);
$colDateFin = in_array('date_fin', $colsCalendrier) ? 'date_fin' : null;
$colLieu = in_array('lieu', $colsCalendrier) ? 'lieu' : null;
$colStagiaire = in_array('id_stagiaire', $colsCalendrier) ? 'id_stagiaire' : null;
$colEncadreur = in_array('id_encadreur', $colsCalendrier) ? 'id_encadreur' : null;
$colStatut = in_array('statut', $colsCalendrier) ? 'statut' : null;

// Récupérer les stagiaires et encadreurs pour les select
$stagiaires = [];
try {
    $colsStagiaires = $pdo->query("SHOW COLUMNS FROM stagiaires")->fetchAll(PDO::FETCH_COLUMN);
    $stagIdCol = in_array('id_stagiaire', $colsStagiaires) ? 'id_stagiaire' : (in_array('id', $colsStagiaires) ? 'id' : null);
    $stagNomCol = in_array('nom', $colsStagiaires) ? 'nom' : (in_array('nom_stagiaire', $colsStagiaires) ? 'nom_stagiaire' : null);
    $stagPrenomCol = in_array('prenom', $colsStagiaires) ? 'prenom' : (in_array('prenom_stagiaire', $colsStagiaires) ? 'prenom_stagiaire' : null);
    
    if ($stagIdCol && ($stagNomCol || $stagPrenomCol)) {
        $sql = "SELECT $stagIdCol as id_stagiaire";
        if ($stagNomCol) $sql .= ", $stagNomCol as nom";
        if ($stagPrenomCol) $sql .= ", $stagPrenomCol as prenom";
        $sql .= " FROM stagiaires ORDER BY nom, prenom";
        $stmt = $pdo->query($sql);
        $stagiaires = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $stagiaires = [];
}

$encadreurs = [];
try {
    $colsEncadreurs = $pdo->query("SHOW COLUMNS FROM encadreurs")->fetchAll(PDO::FETCH_COLUMN);
    $encIdCol = in_array('id_encadreur', $colsEncadreurs) ? 'id_encadreur' : (in_array('id', $colsEncadreurs) ? 'id' : null);
    $encNomCol = in_array('nom', $colsEncadreurs) ? 'nom' : (in_array('nom_encadreur', $colsEncadreurs) ? 'nom_encadreur' : null);
    $encPrenomCol = in_array('prenom', $colsEncadreurs) ? 'prenom' : (in_array('prenom_encadreur', $colsEncadreurs) ? 'prenom_encadreur' : null);
    
    if ($encIdCol && ($encNomCol || $encPrenomCol)) {
        $sql = "SELECT $encIdCol as id_encadreur";
        if ($encNomCol) $sql .= ", $encNomCol as nom";
        if ($encPrenomCol) $sql .= ", $encPrenomCol as prenom";
        $sql .= " FROM encadreurs ORDER BY nom, prenom";
        $stmt = $pdo->query($sql);
        $encadreurs = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $encadreurs = [];
}

// =============================================
// RÉCUPÉRATION DE L'ÉVÉNEMENT AVANT MODIFICATION
// =============================================

$selectCols = ["$colId as id_evenement", "$colTitre as titre"];

if ($colDescription) $selectCols[] = "$colDescription as description";
if ($colType) $selectCols[] = "$colType as type";
if ($colDateDebut) $selectCols[] = "$colDateDebut as date_debut";
if ($colDateFin) $selectCols[] = "$colDateFin as date_fin";
if ($colLieu) $selectCols[] = "$colLieu as lieu";
if ($colStagiaire) $selectCols[] = "$colStagiaire as id_stagiaire";
if ($colEncadreur) $selectCols[] = "$colEncadreur as id_encadreur";
if ($colStatut) $selectCols[] = "$colStatut as statut";

$sql = "SELECT " . implode(', ', $selectCols) . " FROM calendrier WHERE $colId = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_evenement]);
$evenement_original = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evenement_original) {
    $_SESSION['error_message'] = "Événement non trouvé";
    redirect('calendrier.php');
}

// Définir des valeurs par défaut
if (!isset($evenement_original['type'])) $evenement_original['type'] = 'autre';
if (!isset($evenement_original['statut'])) $evenement_original['statut'] = 'confirme';
if (!isset($evenement_original['description'])) $evenement_original['description'] = '';
if (!isset($evenement_original['lieu'])) $evenement_original['lieu'] = '';

// =============================================
// TRAITEMENT DU FORMULAIRE
// =============================================

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = cleanInput($_POST['titre'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'autre';
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? null;
    $lieu = cleanInput($_POST['lieu'] ?? '');
    $id_stagiaire = !empty($_POST['id_stagiaire']) ? (int)$_POST['id_stagiaire'] : null;
    $id_encadreur = !empty($_POST['id_encadreur']) ? (int)$_POST['id_encadreur'] : null;
    $statut = $_POST['statut'] ?? 'confirme';
    
    $errors = [];
    if (empty($titre)) $errors[] = "Le titre est requis";
    if (empty($date_debut)) $errors[] = "La date de début est requise";
    
    if (empty($errors)) {
        // Construire la requête de mise à jour
        $updateFields = [];
        $params = [];
        
        if ($colTitre) { $updateFields[] = "$colTitre = ?"; $params[] = $titre; }
        if ($colDescription) { $updateFields[] = "$colDescription = ?"; $params[] = $description; }
        if ($colType) { $updateFields[] = "$colType = ?"; $params[] = $type; }
        if ($colDateDebut) { $updateFields[] = "$colDateDebut = ?"; $params[] = $date_debut; }
        if ($colDateFin) { $updateFields[] = "$colDateFin = ?"; $params[] = $date_fin; }
        if ($colLieu) { $updateFields[] = "$colLieu = ?"; $params[] = $lieu; }
        if ($colStagiaire) { $updateFields[] = "$colStagiaire = ?"; $params[] = $id_stagiaire; }
        if ($colEncadreur) { $updateFields[] = "$colEncadreur = ?"; $params[] = $id_encadreur; }
        if ($colStatut) { $updateFields[] = "$colStatut = ?"; $params[] = $statut; }
        
        $params[] = $id_evenement;
        
        $sql = "UPDATE calendrier SET " . implode(', ', $updateFields) . " WHERE $colId = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            // =============================================
            // ENVOI DES NOTIFICATIONS
            // =============================================
            
            $date_formatee = date('d/m/Y à H:i', strtotime($date_debut));
            $lieu_texte = $lieu ?: 'Non spécifié';
            
            // Construire le message de notification
            $message_notification = "📅 **$titre**\n\n📆 Date: $date_formatee\n📍 Lieu: $lieu_texte\n\n✏️ Modification effectuée par le secrétariat.";
            
            $notifications_envoyees = 0;
            $personnes_notifiees = [];
            
            // 1. Notifier le stagiaire (s'il y a un changement ou si c'est le même)
            if ($id_stagiaire) {
                $stagStmt = $pdo->prepare("SELECT id_utilisateur, nom, prenom FROM stagiaires WHERE id_stagiaire = ?");
                $stagStmt->execute([$id_stagiaire]);
                $stag = $stagStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($stag && $stag['id_utilisateur']) {
                    $lien = "dashboard/stagiaire/calendrier.php";
                    if (envoyerNotification($stag['id_utilisateur'], "📝 Événement modifié: $titre", $message_notification, 'calendar', $lien)) {
                        $notifications_envoyees++;
                        $personnes_notifiees[] = $stag['prenom'] . ' ' . $stag['nom'] . " (stagiaire)";
                    }
                }
            }
            
            // 2. Notifier l'encadreur (s'il y a un changement ou si c'est le même)
            if ($id_encadreur) {
                $encStmt = $pdo->prepare("SELECT id_utilisateur, nom, prenom FROM encadreurs WHERE id_encadreur = ?");
                $encStmt->execute([$id_encadreur]);
                $enc = $encStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($enc && $enc['id_utilisateur']) {
                    $lien = "dashboard/encadreur/calendrier.php";
                    if (envoyerNotification($enc['id_utilisateur'], "📝 Événement modifié: $titre", $message_notification, 'calendar', $lien)) {
                        $notifications_envoyees++;
                        $personnes_notifiees[] = $enc['prenom'] . ' ' . $enc['nom'] . " (encadreur)";
                    }
                }
            }
            
            // Message de succès
            $msg_succes = "Événement modifié avec succès !";
            if ($notifications_envoyees > 0) {
                $msg_succes .= "<br><i class='fas fa-bell'></i> Notification envoyée à: " . implode(', ', $personnes_notifiees);
            } else {
                $msg_succes .= "<br><i class='fas fa-info-circle'></i> Aucune personne concernée (aucun stagiaire ou encadreur sélectionné).";
            }
            
            $_SESSION['success_message'] = $msg_succes;
            redirect("evenement-voir.php?id=$id_evenement");
        } else {
            $error = "Erreur lors de la modification";
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
    
    .notification-badge {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 0.85rem;
        color: #28a745;
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
                                <i class="fas fa-edit me-2"></i>Modifier l'événement
                            </h2>
                            <p class="mb-0 mt-2 opacity-75">Modifiez les informations de l'événement</p>
                        </div>
                        <div>
                            <a href="evenement-voir.php?id=<?= $id_evenement ?>" class="btn btn-outline-light">
                                <i class="fas fa-times"></i> Annuler
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
                                <input type="text" name="titre" class="form-control" required 
                                       value="<?= htmlspecialchars($evenement_original['titre']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Type *</label>
                                <select name="type" class="form-select" required>
                                    <option value="rendez_vous" <?= $evenement_original['type'] == 'rendez_vous' ? 'selected' : '' ?>>📅 Rendez-vous</option>
                                    <option value="soutenance" <?= $evenement_original['type'] == 'soutenance' ? 'selected' : '' ?>>🎓 Soutenance</option>
                                    <option value="evaluation" <?= $evenement_original['type'] == 'evaluation' ? 'selected' : '' ?>>⭐ Évaluation</option>
                                    <option value="echeance" <?= $evenement_original['type'] == 'echeance' ? 'selected' : '' ?>>⏰ Échéance</option>
                                    <option value="reunion" <?= $evenement_original['type'] == 'reunion' ? 'selected' : '' ?>>👥 Réunion</option>
                                    <option value="formation" <?= $evenement_original['type'] == 'formation' ? 'selected' : '' ?>>📚 Formation</option>
                                    <option value="autre" <?= $evenement_original['type'] == 'autre' ? 'selected' : '' ?>>📌 Autre</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($evenement_original['description']) ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date et heure de début *</label>
                                <input type="datetime-local" name="date_debut" class="form-control" required 
                                       value="<?= date('Y-m-d\TH:i', strtotime($evenement_original['date_debut'])) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date et heure de fin</label>
                                <input type="datetime-local" name="date_fin" class="form-control" 
                                       value="<?= !empty($evenement_original['date_fin']) ? date('Y-m-d\TH:i', strtotime($evenement_original['date_fin'])) : '' ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Lieu</label>
                            <input type="text" name="lieu" class="form-control" placeholder="Salle, en ligne, etc..." 
                                   value="<?= htmlspecialchars($evenement_original['lieu']) ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user-graduate text-primary me-1"></i>Stagiaire concerné
                                </label>
                                <select name="id_stagiaire" class="form-select">
                                    <option value="">-- Aucun --</option>
                                    <?php foreach ($stagiaires as $s): ?>
                                        <option value="<?= $s['id_stagiaire'] ?>" 
                                            <?= ($evenement_original['id_stagiaire'] ?? '') == $s['id_stagiaire'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($s['prenom'] ?? '') . ' ' . ($s['nom'] ?? '')) ?>
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
                                        <option value="<?= $e['id_encadreur'] ?>" 
                                            <?= ($evenement_original['id_encadreur'] ?? '') == $e['id_encadreur'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(($e['prenom'] ?? '') . ' ' . ($e['nom'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <?php if ($colStatut): ?>
                        <div class="mb-3">
                            <label class="form-label">Statut</label>
                            <select name="statut" class="form-select">
                                <option value="propose" <?= $evenement_original['statut'] == 'propose' ? 'selected' : '' ?>>📝 Proposé</option>
                                <option value="confirme" <?= $evenement_original['statut'] == 'confirme' ? 'selected' : '' ?>>✅ Confirmé</option>
                                <option value="annule" <?= $evenement_original['statut'] == 'annule' ? 'selected' : '' ?>>❌ Annulé</option>
                                <option value="termine" <?= $evenement_original['statut'] == 'termine' ? 'selected' : '' ?>>✔️ Terminé</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-box">
                            <i class="fas fa-bell"></i>
                            <strong>Notifications</strong><br>
                            <small>Les personnes sélectionnées (stagiaire et/ou encadreur) recevront une notification 🔔 les informant de la modification.</small>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="evenement-voir.php?id=<?= $id_evenement ?>" class="btn btn-outline">
                                <i class="fas fa-arrow-left me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>