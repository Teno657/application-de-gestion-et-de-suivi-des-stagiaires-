<?php
/**
 * Modifier une tâche - Encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_any_role(['encadreur_pro', 'encadreur_acro']);

$page_title = "Modifier une tâche - School-Connection";
$user_id = $_SESSION['user_id'];
$id_tache = (int)($_GET['id'] ?? 0);
$error = '';

if (!$id_tache) {
    redirect('taches.php');
}

// Récupérer la tâche
$stmt = $pdo->prepare("
    SELECT t.*, u.nom, u.prenom, s.filiere
    FROM taches t
    JOIN stagiaires s ON t.id_stagiaire = s.id_stagiaire
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE t.id_tache = ? AND t.id_encadreur = ?
");
$stmt->execute([$id_tache, $user_id]);
$tache = $stmt->fetch();

if (!$tache) {
    $_SESSION['flash']['danger'] = "Tâche non trouvée";
    redirect('taches.php');
}

// Récupérer les stagiaires
$stagiaires = $pdo->prepare("
    SELECT u.id_utilisateur, u.nom, u.prenom, u.photo, s.filiere
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE s.id_encadreur = ?
    ORDER BY u.nom, u.prenom
");
$stagiaires->execute([$user_id]);
$liste_stagiaires = $stagiaires->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $id_stagiaire = (int)($_POST['id_stagiaire'] ?? 0);
        $titre = cleanInput($_POST['titre'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $date_echeance = $_POST['date_echeance'] ?? '';
        $priorite = $_POST['priorite'] ?? 'moyenne';
        $statut = $_POST['statut'] ?? 'a_faire';
        
        $errors = [];
        if ($id_stagiaire <= 0) $errors[] = 'Veuillez sélectionner un stagiaire';
        if (empty($titre)) $errors[] = 'Le titre est requis';
        if (empty($date_echeance)) $errors[] = 'La date d\'échéance est requise';
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE taches 
                    SET id_stagiaire = ?, titre = ?, description = ?, 
                        date_echeance = ?, priorite = ?, statut = ?
                    WHERE id_tache = ? AND id_encadreur = ?
                ");
                $stmt->execute([$id_stagiaire, $titre, $description, $date_echeance, $priorite, $statut, $id_tache, $user_id]);
                
                // Notification au stagiaire
                create_notification(
                    $id_stagiaire,
                    '✏️ Tâche modifiée',
                    "La tâche **$titre** a été modifiée par votre encadreur.\n📅 Nouvelle échéance : " . format_date($date_echeance, 'd/m/Y'),
                    'info',
                    "/dashboard/stagiaire/tache-voir.php?id=$id_tache"
                );
                
                $_SESSION['flash']['success'] = "Tâche modifiée avec succès !";
                redirect("tache-voir.php?id=$id_tache");
            } catch (Exception $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

include '../../includes/header.php';
?>

<style>
    .form-card {
        background: white;
        border-radius: 25px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .card-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 20px;
        color: white;
    }
    .stagiaire-card {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 10px;
    }
    .stagiaire-card:hover, .stagiaire-card.selected {
        border-color: #667eea;
        background: #f8f9ff;
        transform: translateY(-2px);
    }
    .stagiaire-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
    }
    .priority-btn {
        flex: 1;
        text-align: center;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        cursor: pointer;
        transition: all 0.3s;
    }
    .priority-btn.selected {
        border-color: currentColor;
        transform: translateY(-2px);
    }
    .priority-basse { color: #6c757d; }
    .priority-moyenne { color: #ffc107; }
    .priority-haute { color: #fd7e14; }
    .priority-urgente { color: #dc3545; }
    .btn-primary-custom {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
    }
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        color: white;
    }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-card">
                <div class="card-header">
                    <h2 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier la tâche</h2>
                    <p class="mb-0 opacity-75">Modifiez les informations de la tâche</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-user-graduate me-2 text-primary"></i>Stagiaire</label>
                            <div class="row">
                                <?php foreach ($liste_stagiaires as $s): ?>
                                <div class="col-md-6">
                                    <div class="stagiaire-card <?= $tache['id_stagiaire'] == $s['id_utilisateur'] ? 'selected' : '' ?>" onclick="selectStagiaire(this, <?= $s['id_utilisateur'] ?>)">
                                        <img src="<?= getPhotoUrl($s['photo']) ?>" class="stagiaire-avatar">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($s['filiere'] ?? '') ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="id_stagiaire" id="id_stagiaire" value="<?= $tache['id_stagiaire'] ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-heading me-2 text-primary"></i>Titre</label>
                            <input type="text" class="form-control" name="titre" value="<?= htmlspecialchars($tache['titre']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-align-left me-2 text-primary"></i>Description</label>
                            <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($tache['description']) ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="far fa-calendar-alt me-2 text-primary"></i>Date d'échéance</label>
                                <input type="date" class="form-control" name="date_echeance" value="<?= $tache['date_echeance'] ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="fas fa-flag me-2 text-primary"></i>Priorité</label>
                                <div class="d-flex gap-2">
                                    <div class="priority-btn priority-basse <?= $tache['priorite'] == 'basse' ? 'selected' : '' ?>" onclick="selectPriority('basse')"><i class="fas fa-arrow-down"></i> Basse</div>
                                    <div class="priority-btn priority-moyenne <?= $tache['priorite'] == 'moyenne' ? 'selected' : '' ?>" onclick="selectPriority('moyenne')"><i class="fas fa-minus"></i> Moyenne</div>
                                    <div class="priority-btn priority-haute <?= $tache['priorite'] == 'haute' ? 'selected' : '' ?>" onclick="selectPriority('haute')"><i class="fas fa-arrow-up"></i> Haute</div>
                                    <div class="priority-btn priority-urgente <?= $tache['priorite'] == 'urgente' ? 'selected' : '' ?>" onclick="selectPriority('urgente')"><i class="fas fa-exclamation-triangle"></i> Urgente</div>
                                </div>
                                <input type="hidden" name="priorite" id="priorite" value="<?= $tache['priorite'] ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-chart-simple me-2 text-primary"></i>Statut</label>
                            <select name="statut" class="form-select">
                                <option value="a_faire" <?= $tache['statut'] == 'a_faire' ? 'selected' : '' ?>>📝 À faire</option>
                                <option value="en_cours" <?= $tache['statut'] == 'en_cours' ? 'selected' : '' ?>>🔄 En cours</option>
                                <option value="termine" <?= $tache['statut'] == 'termine' ? 'selected' : '' ?>>✅ Terminé</option>
                            </select>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="taches.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectStagiaire(element, id) {
    document.querySelectorAll('.stagiaire-card').forEach(card => card.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('id_stagiaire').value = id;
}
function selectPriority(priority) {
    document.querySelectorAll('.priority-btn').forEach(btn => btn.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('priorite').value = priority;
}
</script>

<?php include '../../includes/footer.php'; ?>