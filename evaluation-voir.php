<?php
/**
 * Voir une évaluation - Encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_any_role(['encadreur_pro', 'encadreur_acro']);

$id_evaluation = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$id_evaluation) {
    redirect('evaluations.php');
}

$stmt = $pdo->prepare("
    SELECT e.*, 
           u.nom, u.prenom, u.email, u.photo,
           s.filiere, s.theme_stage, s.date_debut, s.date_fin
    FROM evaluations e
    JOIN stagiaires s ON e.id_stagiaire = s.id_stagiaire
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE e.id_evaluation = ? AND e.id_encadreur = ?
");
$stmt->execute([$id_evaluation, $user_id]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    $_SESSION['flash']['danger'] = "Évaluation non trouvée";
    redirect('evaluations.php');
}

// Calcul de la moyenne
$notes = [
    $evaluation['note_technique'],
    $evaluation['note_communication'],
    $evaluation['note_initiative'],
    $evaluation['note_ponctualite'],
    $evaluation['note_travail_equipe'],
    $evaluation['note_adaptabilite'],
    $evaluation['note_qualite_travail'],
    $evaluation['note_autonomie']
];
$notes_valides = array_filter($notes);
$moyenne = !empty($notes_valides) ? round(array_sum($notes_valides) / count($notes_valides), 1) : null;

$page_title = "Détail de l'évaluation - School-Connection";
include '../../includes/header.php';
?>

<style>
    .evaluation-card {
        background: white;
        border-radius: 25px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .evaluation-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 25px;
        color: white;
    }
    
    .moyenne-circle {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        font-size: 2rem;
        font-weight: bold;
    }
    
    .note-item {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 10px;
    }
    
    .note-label {
        font-weight: 600;
        color: #2d3436;
    }
    
    .note-value {
        font-size: 1.2rem;
        font-weight: bold;
        color: #667eea;
    }
    
    .badge-recommandation {
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: 600;
    }
    
    .badge-excellent { background: #d4edda; color: #155724; }
    .badge-bon { background: #d1ecf1; color: #0c5460; }
    .badge-satisfaisant { background: #fff3cd; color: #856404; }
    .badge-insuffisant { background: #f8d7da; color: #721c24; }
</style>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="evaluation-card">
                <div class="evaluation-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-0">Évaluation du stagiaire</h2>
                            <p class="mb-0 opacity-75">Date : <?= format_date($evaluation['date_evaluation'], 'd/m/Y') ?></p>
                        </div>
                        <a href="evaluations.php" class="btn btn-light">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                    </div>
                </div>
                
                <div class="p-4">
                    <!-- Infos stagiaire -->
                    <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                        <img src="<?= getPhotoUrl($evaluation['photo']) ?>" 
                             alt="" class="rounded-circle me-3" width="60" height="60" style="object-fit: cover;">
                        <div>
                            <h4 class="mb-0"><?= htmlspecialchars($evaluation['prenom'] . ' ' . $evaluation['nom']) ?></h4>
                            <p class="text-muted mb-0">
                                <?= htmlspecialchars($evaluation['filiere']) ?> • 
                                Stage du <?= format_date($evaluation['date_debut'], 'd/m/Y') ?> au <?= format_date($evaluation['date_fin'], 'd/m/Y') ?>
                            </p>
                        </div>
                        <div class="ms-auto text-center">
                            <?php if ($moyenne): ?>
                                <div class="moyenne-circle">
                                    <?= $moyenne ?>/10
                                </div>
                                <small class="text-muted">Moyenne générale</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Période évaluée -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="note-item">
                                <div class="note-label">Période évaluée</div>
                                <div>
                                    Du <?= format_date($evaluation['periode_debut'], 'd/m/Y') ?> 
                                    au <?= format_date($evaluation['periode_fin'], 'd/m/Y') ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="note-item">
                                <div class="note-label">Recommandation</div>
                                <div>
                                    <?php
                                    $reco_class = '';
                                    $reco_label = '';
                                    switch($evaluation['recommandation']) {
                                        case 'excellent': $reco_class = 'badge-excellent'; $reco_label = '⭐ Excellent'; break;
                                        case 'bon': $reco_class = 'badge-bon'; $reco_label = '👍 Bon'; break;
                                        case 'satisfaisant': $reco_class = 'badge-satisfaisant'; $reco_label = '✅ Satisfaisant'; break;
                                        case 'insuffisant': $reco_class = 'badge-insuffisant'; $reco_label = '⚠️ Insuffisant'; break;
                                    }
                                    ?>
                                    <span class="badge-recommandation <?= $reco_class ?>"><?= $reco_label ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grille des notes -->
                    <h5 class="mb-3">Grille d'évaluation</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="note-item d-flex justify-content-between">
                                <span class="note-label">Compétences techniques</span>
                                <span class="note-value"><?= $evaluation['note_technique'] ?: '—' ?>/10</span>
                            </div>
                            <div class="note-item d-flex justify-content-between">
                                <span class="note-label">Communication</span>
                                <span class="note-value"><?= $evaluation['note_communication'] ?: '—' ?>/10</span>
                            </div>
                            <div class="note-item d-flex justify-content-between">
                                <span class="note-label">Initiative</span>
                                <span class="note-value"><?= $evaluation['note_initiative'] ?: '—' ?>/10</span>
                            </div>
                            <div class="note-item d-flex justify-content-between">
                                <span class="note-label">Ponctualité</span>
                                <span class="note-value"><?= $evaluation['note_ponctualite'] ?: '—' ?>/10</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="note-item d-flex justify-content-between">
                                <span class="note-label">Travail en équipe</span>
                                <span class="note-value"><?= $evaluation['note_travail_equipe'] ?: '—' ?>/10</span>
                            </div>
                            <div class="note-item d-flex justify-content-between">
                                <span class="note-label">Adaptabilité</span>
                                <span class="note-value"><?= $evaluation['note_adaptabilite'] ?: '—' ?>/10</span>
                            </div>
                            <div class="note-item d-flex justify-content-between">
                                <span class="note-label">Qualité du travail</span>
                                <span class="note-value"><?= $evaluation['note_qualite_travail'] ?: '—' ?>/10</span>
                            </div>
                            <div class="note-item d-flex justify-content-between">
                                <span class="note-label">Autonomie</span>
                                <span class="note-value"><?= $evaluation['note_autonomie'] ?: '—' ?>/10</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Commentaires -->
                    <?php if ($evaluation['points_forts']): ?>
                    <div class="mb-3">
                        <h5 class="mb-2">Points forts</h5>
                        <div class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($evaluation['points_forts'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($evaluation['points_amelioration']): ?>
                    <div class="mb-3">
                        <h5 class="mb-2">Points d'amélioration</h5>
                        <div class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($evaluation['points_amelioration'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($evaluation['commentaire']): ?>
                    <div class="mb-3">
                        <h5 class="mb-2">Commentaire général</h5>
                        <div class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($evaluation['commentaire'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="evaluation-modifier.php?id=<?= $evaluation['id_evaluation'] ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="evaluations.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> Toutes les évaluations
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>