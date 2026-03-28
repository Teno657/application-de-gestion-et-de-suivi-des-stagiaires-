<?php
/**
 * Liste des tâches - Encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_any_role(['encadreur_pro', 'encadreur_acro']);

$page_title = "Mes tâches - School-Connection";
$user_id = $_SESSION['user_id'];

// Récupérer les tâches des stagiaires
$stmt = $pdo->prepare("
    SELECT t.*, u.nom, u.prenom, u.photo, s.filiere
    FROM taches t
    JOIN stagiaires s ON t.id_stagiaire = s.id_stagiaire
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE t.id_encadreur = ?
    ORDER BY 
        CASE 
            WHEN t.statut = 'termine' THEN 3
            WHEN t.date_echeance < CURDATE() AND t.statut != 'termine' THEN 0
            ELSE 1
        END,
        FIELD(t.priorite, 'urgente', 'haute', 'moyenne', 'basse'),
        t.date_echeance ASC
");
$stmt->execute([$user_id]);
$taches = $stmt->fetchAll();

$total = count($taches);
$terminees = 0;
$en_cours = 0;
$a_faire = 0;

foreach ($taches as $t) {
    if ($t['statut'] == 'termine') $terminees++;
    elseif ($t['statut'] == 'en_cours') $en_cours++;
    else $a_faire++;
}

include '../../includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 25px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
    }
    
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        transition: all 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }
    
    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .task-card {
        background: white;
        border-radius: 20px;
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.3s;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .task-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .task-card.overdue {
        border-left: 4px solid #dc3545;
    }
    
    .task-header {
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .badge-statut {
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .badge-a_faire { background: #ffc107; color: #2d3436; }
    .badge-en_cours { background: #17a2b8; color: white; }
    .badge-termine { background: #28a745; color: white; }
    
    .badge-priorite {
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-urgente { background: #dc3545; color: white; }
    .badge-haute { background: #fd7e14; color: white; }
    .badge-moyenne { background: #ffc107; color: #2d3436; }
    .badge-basse { background: #6c757d; color: white; }
    
    .task-body {
        padding: 20px;
    }
    
    .stagiaire-info {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .stagiaire-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .progression-bar {
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .progression-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s;
    }
    
    .btn-action {
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
        transition: all 0.3s;
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 25px;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
</style>

<div class="container-fluid py-4">
    <div class="page-header">
        <h1><i class="fas fa-tasks me-3"></i>Tâches des stagiaires</h1>
        <p>Créez, modifiez et suivez les tâches de vos stagiaires</p>
    </div>
    
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-number"><?= $total ?></div><div>Total</div></div>
        <div class="stat-card"><div class="stat-number text-warning"><?= $a_faire ?></div><div>À faire</div></div>
        <div class="stat-card"><div class="stat-number text-info"><?= $en_cours ?></div><div>En cours</div></div>
        <div class="stat-card"><div class="stat-number text-success"><?= $terminees ?></div><div>Terminées</div></div>
    </div>
    
    <?php if (empty($taches)): ?>
        <div class="empty-state">
            <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
            <h4>Aucune tâche</h4>
            <p>Créez votre première tâche en cliquant sur "Nouvelle tâche"</p>
            <a href="tache-ajouter.php" class="btn btn-primary mt-3">
                <i class="fas fa-plus"></i> Nouvelle tâche
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($taches as $tache): 
            $est_retard = strtotime($tache['date_echeance']) < time() && $tache['statut'] != 'termine';
            $est_termine = $tache['statut'] == 'termine';
        ?>
        <div class="task-card <?= $est_retard ? 'overdue' : '' ?>">
            <div class="task-header">
                <div>
                    <span class="badge-statut badge-<?= $tache['statut'] ?>">
                        <?= $tache['statut'] == 'a_faire' ? 'À faire' : ($tache['statut'] == 'en_cours' ? 'En cours' : 'Terminé') ?>
                    </span>
                    <span class="badge-priorite badge-<?= $tache['priorite'] ?> ms-2">
                        <?= $tache['priorite'] ?>
                    </span>
                </div>
                <div><small class="text-muted">Échéance : <?= format_date($tache['date_echeance'], 'd/m/Y') ?></small></div>
            </div>
            <div class="task-body">
                <div class="stagiaire-info">
                    <img src="<?= getPhotoUrl($tache['photo']) ?>" class="stagiaire-avatar">
                    <div>
                        <strong><?= htmlspecialchars($tache['prenom'] . ' ' . $tache['nom']) ?></strong>
                        <br>
                        <small class="text-muted"><?= htmlspecialchars($tache['filiere']) ?></small>
                    </div>
                </div>
                <h5><?= htmlspecialchars($tache['titre']) ?></h5>
                <p class="text-muted small"><?= nl2br(htmlspecialchars(substr($tache['description'], 0, 100))) ?>...</p>
                <div class="progression-bar">
                    <div class="progression-fill bg-<?= $tache['progression'] >= 100 ? 'success' : 'primary' ?>" style="width: <?= $tache['progression'] ?>%"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small>Progression : <?= $tache['progression'] ?>%</small>
                    <div class="action-buttons">
                        <a href="tache-modifier.php?id=<?= $tache['id_tache'] ?>" class="btn btn-sm btn-warning btn-action" title="Modifier">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="javascript:void(0)" onclick="supprimerTache(<?= $tache['id_tache'] ?>)" class="btn btn-sm btn-danger btn-action" title="Supprimer">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                        <a href="tache-voir.php?id=<?= $tache['id_tache'] ?>" class="btn btn-sm btn-primary btn-action" title="Voir">
                            <i class="fas fa-eye"></i> Voir
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function supprimerTache(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette tâche ? Cette action est irréversible.')) {
        window.location.href = 'tache-supprimer.php?id=' + id;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>