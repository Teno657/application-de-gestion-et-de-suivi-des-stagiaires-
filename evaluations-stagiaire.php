<?php
/**
 * Historique des évaluations d'un stagiaire - Encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_any_role(['encadreur_pro', 'encadreur_acro']);

$page_title = "Historique des évaluations - School-Connection";
$user_id = $_SESSION['user_id'];
$stagiaire_id = (int)($_GET['id'] ?? 0);

if (!$stagiaire_id) {
    $_SESSION['flash']['danger'] = "Stagiaire non spécifié";
    redirect('evaluations.php');
}

// Vérifier que le stagiaire appartient bien à l'encadreur
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM stagiaires 
    WHERE id_stagiaire = ? AND id_encadreur = ?
");
$stmt->execute([$stagiaire_id, $user_id]);
if ($stmt->fetchColumn() == 0) {
    $_SESSION['flash']['danger'] = "Ce stagiaire ne fait pas partie de vos encadrés";
    redirect('evaluations.php');
}

// Récupérer les informations du stagiaire
$stmt = $pdo->prepare("
    SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.photo,
           s.filiere, s.theme_stage, s.date_debut, s.date_fin
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE s.id_stagiaire = ?
");
$stmt->execute([$stagiaire_id]);
$stagiaire = $stmt->fetch();

// Récupérer toutes les évaluations du stagiaire
$stmt = $pdo->prepare("
    SELECT * FROM evaluations 
    WHERE id_stagiaire = ? AND id_encadreur = ?
    ORDER BY date_evaluation DESC
");
$stmt->execute([$stagiaire_id, $user_id]);
$evaluations = $stmt->fetchAll();

// Statistiques
$total_evaluations = count($evaluations);
$moyenne_globale = 0;
$derniere_evaluation = null;

if ($total_evaluations > 0) {
    $somme_notes = 0;
    foreach ($evaluations as $e) {
        $notes = [
            $e['note_technique'], $e['note_communication'], $e['note_initiative'],
            $e['note_ponctualite'], $e['note_travail_equipe'], $e['note_adaptabilite'],
            $e['note_qualite_travail'], $e['note_autonomie']
        ];
        $notes_valides = array_filter($notes);
        if (!empty($notes_valides)) {
            $somme_notes += array_sum($notes_valides) / count($notes_valides);
        }
    }
    $moyenne_globale = round($somme_notes / $total_evaluations, 1);
    $derniere_evaluation = $evaluations[0];
}

include '../../includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
    
    .history-container {
        padding: 20px;
        background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        min-height: 100vh;
    }
    
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 25px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    
    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
        transform: rotate(45deg);
        animation: shine 8s infinite;
    }
    
    @keyframes shine {
        0% { transform: translateX(-100%) rotate(45deg); }
        20%, 100% { transform: translateX(100%) rotate(45deg); }
    }
    
    .stagiaire-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea20, #764ba220);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 1.5rem;
        color: #667eea;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .evaluation-card {
        background: white;
        border-radius: 20px;
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.3s;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .evaluation-card:hover {
        transform: translateX(5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .evaluation-header {
        background: linear-gradient(135deg, #f8f9fa, #fff);
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .evaluation-date {
        font-weight: 600;
        color: #667eea;
    }
    
    .evaluation-moyenne {
        padding: 5px 12px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .evaluation-body {
        padding: 20px;
    }
    
    .note-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .note-item {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .note-label {
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .note-value {
        font-weight: 700;
        font-size: 1.1rem;
        color: #667eea;
    }
    
    .recommandation-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .reco-excellent { background: #d4edda; color: #155724; }
    .reco-bon { background: #d1ecf1; color: #0c5460; }
    .reco-satisfaisant { background: #fff3cd; color: #856404; }
    .reco-insuffisant { background: #f8d7da; color: #721c24; }
    
    .btn-back {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 10px 25px;
        border-radius: 50px;
        transition: all 0.3s;
    }
    
    .btn-back:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 25px;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 20px;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .evaluation-card {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    .evaluation-card:nth-child(1) { animation-delay: 0.1s; }
    .evaluation-card:nth-child(2) { animation-delay: 0.2s; }
    .evaluation-card:nth-child(3) { animation-delay: 0.3s; }
    .evaluation-card:nth-child(4) { animation-delay: 0.4s; }
    .evaluation-card:nth-child(5) { animation-delay: 0.5s; }
</style>

<div class="history-container">
    <div class="container-fluid">
        <!-- En-tête -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= getPhotoUrl($stagiaire['photo']) ?>" 
                             alt="" class="stagiaire-avatar">
                        <div>
                            <h1 class="mb-0"><?= htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?></h1>
                            <p class="mb-0 opacity-75"><?= htmlspecialchars($stagiaire['filiere'] ?? 'Filière non renseignée') ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <a href="evaluations.php" class="btn btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-number"><?= $total_evaluations ?></div>
                    <div class="text-muted">Évaluations totales</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?= $moyenne_globale ?>/10</div>
                    <div class="text-muted">Moyenne générale</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-number"><?= $total_evaluations > 0 ? format_date($evaluations[0]['date_evaluation'], 'd/m/Y') : '-' ?></div>
                    <div class="text-muted">Dernière évaluation</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="stat-number"><?= htmlspecialchars($stagiaire['theme_stage'] ? substr($stagiaire['theme_stage'], 0, 20) : '-') ?>...</div>
                    <div class="text-muted">Thème de stage</div>
                </div>
            </div>
        </div>
        
        <!-- Liste des évaluations -->
        <h3 class="mb-4">
            <i class="fas fa-history me-2 text-primary"></i>
            Historique des évaluations
        </h3>
        
        <?php if (empty($evaluations)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h4>Aucune évaluation</h4>
                <p>Ce stagiaire n'a pas encore reçu d'évaluation.</p>
                <a href="evaluation-ajouter.php?stagiaire=<?= $stagiaire_id ?>" class="btn btn-primary-custom">
                    <i class="fas fa-plus"></i> Ajouter une évaluation
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($evaluations as $evaluation):
                // Calcul de la moyenne de cette évaluation
                $notes = [
                    $evaluation['note_technique'], $evaluation['note_communication'], 
                    $evaluation['note_initiative'], $evaluation['note_ponctualite'],
                    $evaluation['note_travail_equipe'], $evaluation['note_adaptabilite'],
                    $evaluation['note_qualite_travail'], $evaluation['note_autonomie']
                ];
                $notes_valides = array_filter($notes);
                $moyenne = !empty($notes_valides) ? round(array_sum($notes_valides) / count($notes_valides), 1) : null;
                
                // Classe de recommandation
                $reco_class = '';
                $reco_label = '';
                switch($evaluation['recommandation']) {
                    case 'excellent': $reco_class = 'reco-excellent'; $reco_label = '🏆 Excellent'; break;
                    case 'bon': $reco_class = 'reco-bon'; $reco_label = '👍 Bon'; break;
                    case 'satisfaisant': $reco_class = 'reco-satisfaisant'; $reco_label = '✅ Satisfaisant'; break;
                    case 'insuffisant': $reco_class = 'reco-insuffisant'; $reco_label = '⚠️ Insuffisant'; break;
                    default: $reco_class = 'reco-satisfaisant'; $reco_label = 'Satisfaisant';
                }
            ?>
            <div class="evaluation-card">
                <div class="evaluation-header">
                    <div class="evaluation-date">
                        <i class="far fa-calendar-alt me-2"></i>
                        <?= format_date($evaluation['date_evaluation'], 'd/m/Y') ?>
                    </div>
                    <div>
                        <span class="evaluation-moyenne" style="background: <?= $moyenne >= 8 ? '#d4edda' : ($moyenne >= 6 ? '#d1ecf1' : ($moyenne >= 4 ? '#fff3cd' : '#f8d7da')) ?>; color: <?= $moyenne >= 8 ? '#155724' : ($moyenne >= 6 ? '#0c5460' : ($moyenne >= 4 ? '#856404' : '#721c24')) ?>">
                            <i class="fas fa-star"></i> Moyenne : <?= $moyenne ?: 'Non noté' ?>/10
                        </span>
                        <span class="recommandation-badge <?= $reco_class ?> ms-2"><?= $reco_label ?></span>
                    </div>
                </div>
                <div class="evaluation-body">
                    <div class="note-grid">
                        <div class="note-item">
                            <span class="note-label">Compétences techniques</span>
                            <span class="note-value"><?= $evaluation['note_technique'] ?: '—' ?>/10</span>
                        </div>
                        <div class="note-item">
                            <span class="note-label">Communication</span>
                            <span class="note-value"><?= $evaluation['note_communication'] ?: '—' ?>/10</span>
                        </div>
                        <div class="note-item">
                            <span class="note-label">Initiative</span>
                            <span class="note-value"><?= $evaluation['note_initiative'] ?: '—' ?>/10</span>
                        </div>
                        <div class="note-item">
                            <span class="note-label">Ponctualité</span>
                            <span class="note-value"><?= $evaluation['note_ponctualite'] ?: '—' ?>/10</span>
                        </div>
                        <div class="note-item">
                            <span class="note-label">Travail en équipe</span>
                            <span class="note-value"><?= $evaluation['note_travail_equipe'] ?: '—' ?>/10</span>
                        </div>
                        <div class="note-item">
                            <span class="note-label">Adaptabilité</span>
                            <span class="note-value"><?= $evaluation['note_adaptabilite'] ?: '—' ?>/10</span>
                        </div>
                        <div class="note-item">
                            <span class="note-label">Qualité du travail</span>
                            <span class="note-value"><?= $evaluation['note_qualite_travail'] ?: '—' ?>/10</span>
                        </div>
                        <div class="note-item">
                            <span class="note-label">Autonomie</span>
                            <span class="note-value"><?= $evaluation['note_autonomie'] ?: '—' ?>/10</span>
                        </div>
                    </div>
                    
                    <?php if ($evaluation['points_forts']): ?>
                    <div class="mb-2">
                        <strong class="text-success"><i class="fas fa-thumbs-up me-1"></i> Points forts :</strong>
                        <span class="text-muted"><?= nl2br(htmlspecialchars($evaluation['points_forts'])) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($evaluation['points_amelioration']): ?>
                    <div class="mb-2">
                        <strong class="text-warning"><i class="fas fa-lightbulb me-1"></i> Points d'amélioration :</strong>
                        <span class="text-muted"><?= nl2br(htmlspecialchars($evaluation['points_amelioration'])) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($evaluation['commentaire']): ?>
                    <div class="mb-2">
                        <strong><i class="fas fa-comment me-1"></i> Commentaire :</strong>
                        <span class="text-muted"><?= nl2br(htmlspecialchars($evaluation['commentaire'])) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-3 text-end">
                        <a href="evaluation-voir.php?id=<?= $evaluation['id_evaluation'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> Voir le détail
                        </a>
                       
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>