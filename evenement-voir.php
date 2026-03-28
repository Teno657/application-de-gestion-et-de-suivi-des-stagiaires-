<?php
/**
 * Voir un événement - Secrétaire
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier que l'utilisateur est secrétaire
require_login();
if ($_SESSION['user_role'] != 'secretaire') {
    redirect('../index.php');
}

$page_title = "Détail de l'événement - School-Connection";
$user_id = $_SESSION['user_id'];

// Récupérer l'ID de l'événement
$id_evenement = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_evenement) {
    $_SESSION['error_message'] = "Événement non trouvé";
    redirect('calendrier.php');
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

// Déterminer les noms des colonnes avec valeurs par défaut
$colId = 'id_evenement';
$colTitre = 'titre';
$colDescription = null;
$colType = null;
$colDateDebut = null;
$colDateFin = null;
$colLieu = null;
$colCreateur = null;
$colStagiaire = null;
$colEncadreur = null;
$colStatut = null;

if (in_array('id_evenement', $colsCalendrier)) $colId = 'id_evenement';
elseif (in_array('id', $colsCalendrier)) $colId = 'id';

if (in_array('titre', $colsCalendrier)) $colTitre = 'titre';
elseif (in_array('nom', $colsCalendrier)) $colTitre = 'nom';

if (in_array('description', $colsCalendrier)) $colDescription = 'description';
if (in_array('type', $colsCalendrier)) $colType = 'type';
if (in_array('date_debut', $colsCalendrier)) $colDateDebut = 'date_debut';
elseif (in_array('date', $colsCalendrier)) $colDateDebut = 'date';
if (in_array('date_fin', $colsCalendrier)) $colDateFin = 'date_fin';
if (in_array('lieu', $colsCalendrier)) $colLieu = 'lieu';
if (in_array('createur', $colsCalendrier)) $colCreateur = 'createur';
elseif (in_array('id_createur', $colsCalendrier)) $colCreateur = 'id_createur';
if (in_array('id_stagiaire', $colsCalendrier)) $colStagiaire = 'id_stagiaire';
if (in_array('id_encadreur', $colsCalendrier)) $colEncadreur = 'id_encadreur';
if (in_array('statut', $colsCalendrier)) $colStatut = 'statut';

// =============================================
// RÉCUPÉRATION DE L'ÉVÉNEMENT
// =============================================

$selectCols = ["$colId as id_evenement", "$colTitre as titre"];

if ($colDescription) $selectCols[] = "$colDescription as description";
if ($colType) $selectCols[] = "$colType as type";
if ($colDateDebut) $selectCols[] = "$colDateDebut as date_debut";
if ($colDateFin) $selectCols[] = "$colDateFin as date_fin";
if ($colLieu) $selectCols[] = "$colLieu as lieu";
if ($colCreateur) $selectCols[] = "$colCreateur as id_createur";
if ($colStagiaire) $selectCols[] = "$colStagiaire as id_stagiaire";
if ($colEncadreur) $selectCols[] = "$colEncadreur as id_encadreur";
if ($colStatut) $selectCols[] = "$colStatut as statut";

$sql = "SELECT " . implode(', ', $selectCols) . " FROM calendrier WHERE $colId = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_evenement]);
$evenement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evenement) {
    $_SESSION['error_message'] = "Événement non trouvé";
    redirect('calendrier.php');
}

// Définir des valeurs par défaut pour les champs manquants
if (!isset($evenement['type'])) $evenement['type'] = 'autre';
if (!isset($evenement['statut'])) $evenement['statut'] = 'confirme';
if (!isset($evenement['description'])) $evenement['description'] = '';
if (!isset($evenement['lieu'])) $evenement['lieu'] = '';

// =============================================
// RÉCUPÉRATION DES INFORMATIONS COMPLÉMENTAIRES
// =============================================

// Récupérer le nom du créateur
$createur_nom = '';
$createur_prenom = '';
if (!empty($evenement['id_createur'])) {
    try {
        $userStmt = $pdo->prepare("SELECT nom, prenom FROM utilisateurs WHERE id_utilisateur = ?");
        $userStmt->execute([$evenement['id_createur']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $createur_nom = $user['nom'] ?? '';
            $createur_prenom = $user['prenom'] ?? '';
        }
    } catch (Exception $e) {
        $createur_nom = '';
        $createur_prenom = '';
    }
}

// Récupérer le nom du stagiaire
$stagiaire_nom = '';
$stagiaire_prenom = '';
if (!empty($evenement['id_stagiaire'])) {
    try {
        $stagStmt = $pdo->prepare("SELECT nom, prenom FROM stagiaires WHERE id_stagiaire = ?");
        $stagStmt->execute([$evenement['id_stagiaire']]);
        $stag = $stagStmt->fetch(PDO::FETCH_ASSOC);
        if ($stag) {
            $stagiaire_nom = $stag['nom'] ?? '';
            $stagiaire_prenom = $stag['prenom'] ?? '';
        }
    } catch (Exception $e) {
        $stagiaire_nom = '';
        $stagiaire_prenom = '';
    }
}

// Récupérer le nom de l'encadreur
$encadreur_nom = '';
$encadreur_prenom = '';
if (!empty($evenement['id_encadreur'])) {
    try {
        $encStmt = $pdo->prepare("SELECT nom, prenom FROM encadreurs WHERE id_encadreur = ?");
        $encStmt->execute([$evenement['id_encadreur']]);
        $enc = $encStmt->fetch(PDO::FETCH_ASSOC);
        if ($enc) {
            $encadreur_nom = $enc['nom'] ?? '';
            $encadreur_prenom = $enc['prenom'] ?? '';
        }
    } catch (Exception $e) {
        $encadreur_nom = '';
        $encadreur_prenom = '';
    }
}

// =============================================
// GESTION DES ACTIONS
// =============================================

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Supprimer l'événement
        if ($_POST['action'] == 'supprimer') {
            $stmt = $pdo->prepare("DELETE FROM calendrier WHERE $colId = ?");
            if ($stmt->execute([$id_evenement])) {
                $_SESSION['success_message'] = "Événement supprimé avec succès";
                redirect('calendrier.php');
            } else {
                $error = "Erreur lors de la suppression";
            }
        }
        
        // Modifier le statut (seulement si la colonne existe)
        if ($_POST['action'] == 'modifier_statut' && $colStatut) {
            $nouveau_statut = $_POST['statut'];
            $stmt = $pdo->prepare("UPDATE calendrier SET $colStatut = ? WHERE $colId = ?");
            if ($stmt->execute([$nouveau_statut, $id_evenement])) {
                $evenement['statut'] = $nouveau_statut;
                $success = "Statut mis à jour avec succès";
            }
        }
    }
}

// Types d'événements avec icônes et couleurs
$types = [
    'rendez_vous' => ['label' => '📅 Rendez-vous', 'color' => '#4cc9f0'],
    'soutenance' => ['label' => '🎓 Soutenance', 'color' => '#f72585'],
    'evaluation' => ['label' => '⭐ Évaluation', 'color' => '#f4a261'],
    'echeance' => ['label' => '⏰ Échéance', 'color' => '#2a9d8f'],
    'reunion' => ['label' => '👥 Réunion', 'color' => '#e9c46a'],
    'formation' => ['label' => '📚 Formation', 'color' => '#9c27b0'],
    'autre' => ['label' => '📌 Autre', 'color' => '#6c757d']
];

$type_info = $types[$evenement['type']] ?? $types['autre'];

// Statuts
$statuts = [
    'propose' => ['label' => '📝 Proposé', 'class' => 'status-propose'],
    'confirme' => ['label' => '✅ Confirmé', 'class' => 'status-confirme'],
    'annule' => ['label' => '❌ Annulé', 'class' => 'status-annule'],
    'termine' => ['label' => '✔️ Terminé', 'class' => 'status-termine']
];

$statut_actuel = $evenement['statut'] ?? 'confirme';
$statut_info = $statuts[$statut_actuel] ?? $statuts['confirme'];

include '../../includes/header.php';
?>

<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 30px;
    }
    
    .event-container {
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
    
    .event-header {
        background: linear-gradient(135deg, <?= $type_info['color'] ?>, #764ba2);
        padding: 30px;
        color: white;
    }
    
    .event-body {
        padding: 40px;
    }
    
    .info-section {
        background: #f8f9fa;
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .info-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6c757d;
        margin-bottom: 5px;
    }
    
    .info-value {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3436;
    }
    
    .status-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .status-propose { background: #fff3cd; color: #856404; }
    .status-confirme { background: #d4edda; color: #155724; }
    .status-annule { background: #f8d7da; color: #721c24; }
    .status-termine { background: #d1ecf1; color: #0c5460; }
    
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
    
    .btn-danger-custom {
        background: #dc3545;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-danger-custom:hover {
        background: #c82333;
        transform: translateY(-3px);
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
    
    .type-badge {
        display: inline-block;
        padding: 10px 25px;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 600;
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="event-container">
                <div class="event-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="type-badge">
                                <?= $type_info['label'] ?>
                            </span>
                            <h1 class="mt-3 mb-0"><?= htmlspecialchars($evenement['titre']) ?></h1>
                        </div>
                        <div>
                            <a href="calendrier.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="event-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <!-- Date et lieu -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">
                                    <i class="far fa-calendar-alt"></i> Date et heure
                                </div>
                                <div class="info-value">
                                    <?php if (!empty($evenement['date_debut'])): ?>
                                        <?= date('d/m/Y à H:i', strtotime($evenement['date_debut'])) ?>
                                        <?php if (!empty($evenement['date_fin']) && $evenement['date_fin'] != '0000-00-00 00:00:00'): ?>
                                            <br><small class="text-muted">Jusqu'au <?= date('d/m/Y à H:i', strtotime($evenement['date_fin'])) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non spécifiée</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-section">
                                <div class="info-label">
                                    <i class="fas fa-map-marker-alt"></i> Lieu
                                </div>
                                <div class="info-value">
                                    <?= !empty($evenement['lieu']) ? htmlspecialchars($evenement['lieu']) : '<span class="text-muted">Non spécifié</span>' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <?php if (!empty($evenement['description'])): ?>
                    <div class="info-section">
                        <div class="info-label">
                            <i class="fas fa-align-left"></i> Description
                        </div>
                        <div class="info-value">
                            <?= nl2br(htmlspecialchars($evenement['description'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Participants -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-section">
                                <div class="info-label">
                                    <i class="fas fa-user"></i> Créé par
                                </div>
                                <div class="info-value">
                                    <?= htmlspecialchars($createur_prenom . ' ' . $createur_nom) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-section">
                                <div class="info-label">
                                    <i class="fas fa-user-graduate"></i> Stagiaire
                                </div>
                                <div class="info-value">
                                    <?php if (!empty($stagiaire_prenom)): ?>
                                        <?= htmlspecialchars($stagiaire_prenom . ' ' . $stagiaire_nom) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Aucun</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-section">
                                <div class="info-label">
                                    <i class="fas fa-chalkboard-user"></i> Encadreur
                                </div>
                                <div class="info-value">
                                    <?php if (!empty($encadreur_prenom)): ?>
                                        <?= htmlspecialchars($encadreur_prenom . ' ' . $encadreur_nom) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Aucun</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statut et actions -->
                    <div class="info-section">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="info-label">Statut actuel</div>
                                <div class="info-value">
                                    <span class="status-badge <?= $statut_info['class'] ?>">
                                        <?= $statut_info['label'] ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($colStatut): ?>
                            <div class="col-md-6">
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="modifier_statut">
                                        <select name="statut" class="form-select" style="width: auto; display: inline-block;">
                                            <option value="propose" <?= $statut_actuel == 'propose' ? 'selected' : '' ?>>📝 Proposé</option>
                                            <option value="confirme" <?= $statut_actuel == 'confirme' ? 'selected' : '' ?>>✅ Confirmé</option>
                                            <option value="annule" <?= $statut_actuel == 'annule' ? 'selected' : '' ?>>❌ Annulé</option>
                                            <option value="termine" <?= $statut_actuel == 'termine' ? 'selected' : '' ?>>✔️ Terminé</option>
                                        </select>
                                        <button type="submit" class="btn btn-outline">Modifier</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer définitivement cet événement ?')">
                                        <input type="hidden" name="action" value="supprimer">
                                        <button type="submit" class="btn btn-danger-custom">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-md-6">
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer définitivement cet événement ?')">
                                        <input type="hidden" name="action" value="supprimer">
                                        <button type="submit" class="btn btn-danger-custom">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="calendrier.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Retour au calendrier
                        </a>
                        <a href="evenement-modifier.php?id=<?= $id_evenement ?>" class="btn btn-gradient">
                            <i class="fas fa-edit"></i> Modifier l'événement
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>