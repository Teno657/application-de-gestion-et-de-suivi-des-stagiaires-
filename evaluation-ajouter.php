<?php
/**
 * Ajouter une évaluation - Encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_any_role(['encadreur_pro', 'encadreur_acro']);

$page_title = "Ajouter une évaluation - School-Connection";
$user_id = $_SESSION['user_id'];
$error = '';

$stagiaire_id = (int)($_GET['stagiaire'] ?? 0);

// Récupérer la liste des stagiaires de l'encadreur
$stagiaires = $pdo->prepare("
    SELECT u.id_utilisateur, u.nom, u.prenom, s.filiere, s.theme_stage
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE s.id_encadreur = ?
    ORDER BY u.nom, u.prenom
");
$stagiaires->execute([$user_id]);
$liste_stagiaires = $stagiaires->fetchAll();

$evaluation = [
    'id_stagiaire' => $stagiaire_id,
    'date_evaluation' => date('Y-m-d'),
    'periode_debut' => date('Y-m-d', strtotime('-1 month')),
    'periode_fin' => date('Y-m-d'),
    'note_technique' => '',
    'note_communication' => '',
    'note_initiative' => '',
    'note_ponctualite' => '',
    'note_travail_equipe' => '',
    'note_adaptabilite' => '',
    'note_qualite_travail' => '',
    'note_autonomie' => '',
    'commentaire' => '',
    'points_forts' => '',
    'points_amelioration' => '',
    'recommandation' => 'satisfaisant'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $data = [
            'id_stagiaire' => (int)($_POST['id_stagiaire'] ?? 0),
            'date_evaluation' => $_POST['date_evaluation'] ?? date('Y-m-d'),
            'periode_debut' => $_POST['periode_debut'] ?? null,
            'periode_fin' => $_POST['periode_fin'] ?? null,
            'note_technique' => $_POST['note_technique'] !== '' ? (float)$_POST['note_technique'] : null,
            'note_communication' => $_POST['note_communication'] !== '' ? (float)$_POST['note_communication'] : null,
            'note_initiative' => $_POST['note_initiative'] !== '' ? (float)$_POST['note_initiative'] : null,
            'note_ponctualite' => $_POST['note_ponctualite'] !== '' ? (float)$_POST['note_ponctualite'] : null,
            'note_travail_equipe' => $_POST['note_travail_equipe'] !== '' ? (float)$_POST['note_travail_equipe'] : null,
            'note_adaptabilite' => $_POST['note_adaptabilite'] !== '' ? (float)$_POST['note_adaptabilite'] : null,
            'note_qualite_travail' => $_POST['note_qualite_travail'] !== '' ? (float)$_POST['note_qualite_travail'] : null,
            'note_autonomie' => $_POST['note_autonomie'] !== '' ? (float)$_POST['note_autonomie'] : null,
            'commentaire' => cleanInput($_POST['commentaire'] ?? ''),
            'points_forts' => cleanInput($_POST['points_forts'] ?? ''),
            'points_amelioration' => cleanInput($_POST['points_amelioration'] ?? ''),
            'recommandation' => $_POST['recommandation'] ?? 'satisfaisant'
        ];
        
        $errors = [];
        
        if ($data['id_stagiaire'] <= 0) $errors[] = 'Veuillez sélectionner un stagiaire';
        
        if ($data['id_stagiaire'] > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE id_stagiaire = ? AND id_encadreur = ?");
            $stmt->execute([$data['id_stagiaire'], $user_id]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Ce stagiaire ne fait pas partie de vos encadrés';
            }
        }
        
        if (empty($errors)) {
            try {
                $sql = "
                    INSERT INTO evaluations (
                        id_stagiaire, id_encadreur, date_evaluation, periode_debut, periode_fin,
                        note_technique, note_communication, note_initiative, note_ponctualite,
                        note_travail_equipe, note_adaptabilite, note_qualite_travail, note_autonomie,
                        commentaire, points_forts, points_amelioration, recommandation
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['id_stagiaire'],
                    $user_id,
                    $data['date_evaluation'],
                    $data['periode_debut'],
                    $data['periode_fin'],
                    $data['note_technique'],
                    $data['note_communication'],
                    $data['note_initiative'],
                    $data['note_ponctualite'],
                    $data['note_travail_equipe'],
                    $data['note_adaptabilite'],
                    $data['note_qualite_travail'],
                    $data['note_autonomie'],
                    $data['commentaire'],
                    $data['points_forts'],
                    $data['points_amelioration'],
                    $data['recommandation']
                ]);
                
                $evaluation_id = $pdo->lastInsertId();
                
                create_notification(
                    $data['id_stagiaire'],
                    '📝 Nouvelle évaluation',
                    "Vous avez reçu une nouvelle évaluation. Consultez vos résultats.",
                    'success',
                    "dashboard/stagiaire/evaluation-voir.php?id=$evaluation_id"
                );
                
                $_SESSION['flash']['success'] = "Évaluation créée avec succès";
                redirect("evaluation-voir.php?id=$evaluation_id");
                
            } catch (Exception $e) {
                $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

include '../../includes/header.php';
?>

<style>
    .form-section {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .form-section h5 {
        color: #667eea;
        margin-bottom: 20px;
        font-weight: 600;
        border-left: 4px solid #667eea;
        padding-left: 15px;
    }
    
    .note-input {
        background: white;
        border: 1px solid #e0e0e0;
        transition: all 0.3s;
    }
    
    .note-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Nouvelle évaluation</h1>
                    <p class="text-muted">Évaluez les compétences d'un stagiaire</p>
                </div>
                <a href="evaluations.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-4">
                            <label class="form-label">Stagiaire</label>
                            <select name="id_stagiaire" class="form-select" required>
                                <option value="">-- Sélectionnez un stagiaire --</option>
                                <?php foreach ($liste_stagiaires as $s): ?>
                                    <option value="<?= $s['id_utilisateur'] ?>" <?= $stagiaire_id == $s['id_utilisateur'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?> - <?= htmlspecialchars($s['filiere']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Date d'évaluation</label>
                                <input type="date" name="date_evaluation" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Période début</label>
                                <input type="date" name="periode_debut" class="form-control" value="<?= date('Y-m-d', strtotime('-1 month')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Période fin</label>
                                <input type="date" name="periode_fin" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5>Grille d'évaluation (notes sur 10)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <?php
                                    $notes = [
                                        'note_technique' => 'Compétences techniques',
                                        'note_communication' => 'Communication',
                                        'note_initiative' => 'Initiative',
                                        'note_ponctualite' => 'Ponctualité'
                                    ];
                                    foreach ($notes as $name => $label): ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?= $label ?></label>
                                        <input type="number" name="<?= $name ?>" class="form-control note-input" min="0" max="10" step="0.5">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php
                                    $notes2 = [
                                        'note_travail_equipe' => 'Travail en équipe',
                                        'note_adaptabilite' => 'Adaptabilité',
                                        'note_qualite_travail' => 'Qualité du travail',
                                        'note_autonomie' => 'Autonomie'
                                    ];
                                    foreach ($notes2 as $name => $label): ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?= $label ?></label>
                                        <input type="number" name="<?= $name ?>" class="form-control note-input" min="0" max="10" step="0.5">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h5>Commentaires</h5>
                            <div class="mb-3">
                                <label class="form-label">Points forts</label>
                                <textarea name="points_forts" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Points d'amélioration</label>
                                <textarea name="points_amelioration" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Commentaire général</label>
                                <textarea name="commentaire" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Recommandation</label>
                            <select name="recommandation" class="form-select">
                                <option value="excellent">⭐ Excellent</option>
                                <option value="bon">👍 Bon</option>
                                <option value="satisfaisant" selected>✅ Satisfaisant</option>
                                <option value="insuffisant">⚠️ Insuffisant</option>
                            </select>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="evaluations.php" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">Enregistrer l'évaluation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>