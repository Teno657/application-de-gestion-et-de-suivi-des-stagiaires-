<?php
/**
 * Ajouter une tâche - Encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_any_role(['encadreur_pro', 'encadreur_acro']);

$page_title = "Ajouter une tâche - School-Connection";
$user_id = $_SESSION['user_id'];
$error = '';

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
        
        $errors = [];
        if ($id_stagiaire <= 0) $errors[] = 'Veuillez sélectionner un stagiaire';
        if (empty($titre)) $errors[] = 'Le titre est requis';
        if (empty($date_echeance)) $errors[] = 'La date d\'échéance est requise';
        if (strtotime($date_echeance) < strtotime(date('Y-m-d'))) $errors[] = 'La date ne peut pas être dans le passé';
        
        if (empty($errors)) {
            try {
                // Vérifier si les colonnes existent
                $columns = $pdo->query("SHOW COLUMNS FROM taches")->fetchAll(PDO::FETCH_COLUMN);
                $has_stagiaire_vu = in_array('stagiaire_vu', $columns);
                
                if ($has_stagiaire_vu) {
                    $stmt = $pdo->prepare("
                        INSERT INTO taches (id_encadreur, id_stagiaire, titre, description, date_echeance, priorite, statut, progression, stagiaire_vu)
                        VALUES (?, ?, ?, ?, ?, ?, 'a_faire', 0, 0)
                    ");
                    $stmt->execute([$user_id, $id_stagiaire, $titre, $description, $date_echeance, $priorite]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO taches (id_encadreur, id_stagiaire, titre, description, date_echeance, priorite, statut, progression)
                        VALUES (?, ?, ?, ?, ?, ?, 'a_faire', 0)
                    ");
                    $stmt->execute([$user_id, $id_stagiaire, $titre, $description, $date_echeance, $priorite]);
                }
                
                $tache_id = $pdo->lastInsertId();
                
                // Notification au stagiaire
                create_notification(
                    $id_stagiaire,
                    '📋 Nouvelle tâche',
                    "Nouvelle tâche : **$titre**\n📅 Échéance : " . format_date($date_echeance, 'd/m/Y') . "\n⭐ Priorité : $priorite",
                    'task',
                    "/dashboard/stagiaire/tache-voir.php?id=$tache_id"
                );
                
                $_SESSION['flash']['success'] = "✅ Tâche créée avec succès !";
                redirect("tache-voir.php?id=$tache_id");
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
    .form-card { background: white; border-radius: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
    .card-header { background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px; color: white; }
    .stagiaire-card { display: flex; align-items: center; gap: 15px; padding: 15px; border: 2px solid #e9ecef; border-radius: 15px; cursor: pointer; transition: all 0.3s; margin-bottom: 10px; }
    .stagiaire-card:hover, .stagiaire-card.selected { border-color: #667eea; background: #f8f9ff; transform: translateY(-2px); }
    .stagiaire-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
    .priority-btn { flex: 1; text-align: center; padding: 12px; border: 2px solid #e9ecef; border-radius: 15px; cursor: pointer; transition: all 0.3s; }
    .priority-btn.selected { border-color: currentColor; transform: translateY(-2px); }
    .priority-basse { color: #6c757d; }
    .priority-moyenne { color: #ffc107; }
    .priority-haute { color: #fd7e14; }
    .priority-urgente { color: #dc3545; }
    .btn-primary-custom { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 12px 30px; border-radius: 50px; font-weight: 600; }
    .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102,126,234,0.3); color: white; }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-card">
                <div class="card-header">
                    <h2 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nouvelle tâche</h2>
                    <p class="mb-0 opacity-75">Assignez une tâche à votre stagiaire</p>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fas fa-user-graduate me-2 text-primary"></i>Stagiaire</label>
                            <div class="row">
                                <?php if (empty($liste_stagiaires)): ?>
                                    <div class="alert alert-warning">Vous n'avez pas encore de stagiaire assigné.</div>
                                <?php else: ?>
                                    <?php foreach ($liste_stagiaires as $s): ?>
                                    <div class="col-md-6">
                                        <div class="stagiaire-card" onclick="selectStagiaire(this, <?= $s['id_utilisateur'] ?>)">
                                            <img src="<?= getPhotoUrl($s['photo']) ?>" class="stagiaire-avatar">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($s['filiere'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="id_stagiaire" id="id_stagiaire" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-heading me-2 text-primary"></i>Titre</label>
                            <input type="text" class="form-control" name="titre" required placeholder="Ex: Rédiger le rapport de stage">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-align-left me-2 text-primary"></i>Description</label>
                            <textarea class="form-control" name="description" rows="4" placeholder="Décrivez la tâche, les objectifs..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="far fa-calendar-alt me-2 text-primary"></i>Date d'échéance</label>
                                <input type="date" class="form-control" name="date_echeance" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="fas fa-flag me-2 text-primary"></i>Priorité</label>
                                <div class="d-flex gap-2">
                                    <div class="priority-btn priority-basse" onclick="selectPriority('basse')"><i class="fas fa-arrow-down"></i> Basse</div>
                                    <div class="priority-btn priority-moyenne selected" onclick="selectPriority('moyenne')"><i class="fas fa-minus"></i> Moyenne</div>
                                    <div class="priority-btn priority-haute" onclick="selectPriority('haute')"><i class="fas fa-arrow-up"></i> Haute</div>
                                    <div class="priority-btn priority-urgente" onclick="selectPriority('urgente')"><i class="fas fa-exclamation-triangle"></i> Urgente</div>
                                </div>
                                <input type="hidden" name="priorite" id="priorite" value="moyenne">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="taches.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary-custom" <?= empty($liste_stagiaires) ? 'disabled' : '' ?>>
                                <i class="fas fa-save me-2"></i>Créer la tâche
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