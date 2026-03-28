<?php
/**
 * Voir une tâche - Encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_any_role(['encadreur_pro', 'encadreur_acro']);

$user_id = $_SESSION['user_id'];
$id_tache = (int)($_GET['id'] ?? 0);

if (!$id_tache) redirect('taches.php');

// Vérifier et corriger le type de la colonne note_encadreur
try {
    $col_info = $pdo->query("SHOW COLUMNS FROM taches LIKE 'note_encadreur'")->fetch();
    if ($col_info && strpos(strtolower($col_info['Type']), 'varchar') === false) {
        // La colonne n'est pas VARCHAR, on la modifie
        $pdo->exec("ALTER TABLE taches MODIFY note_encadreur VARCHAR(50) NULL");
        echo "<div style='background:#d4edda; padding:10px; margin:10px; border:1px solid green;'>✅ Colonne note_encadreur modifiée en VARCHAR</div>";
    }
} catch (Exception $e) {
    // Ignorer
}

// Récupérer la tâche
$stmt = $pdo->prepare("
    SELECT t.*, u.nom, u.prenom, u.email, u.photo, s.filiere, s.theme_stage
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

// Traitement de la notation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noter'])) {
    $note = $_POST['note_encadreur'];
    
    if (!empty($note)) {
        // Mise à jour directe
        $update = $pdo->prepare("UPDATE taches SET note_encadreur = ?, note_date = NOW() WHERE id_tache = ? AND id_encadreur = ?");
        $result = $update->execute([$note, $id_tache, $user_id]);
        
        if ($result) {
            // Recharger la tâche
            $stmt = $pdo->prepare("
                SELECT t.*, u.nom, u.prenom, u.email, u.photo, s.filiere, s.theme_stage
                FROM taches t
                JOIN stagiaires s ON t.id_stagiaire = s.id_stagiaire
                JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
                WHERE t.id_tache = ? AND t.id_encadreur = ?
            ");
            $stmt->execute([$id_tache, $user_id]);
            $tache = $stmt->fetch();
            
            $note_labels = [
                'excellent' => '⭐ Excellent',
                'bon' => '👍 Bon',
                'satisfaisant' => '✅ Satisfaisant',
                'insuffisant' => '⚠️ Insuffisant'
            ];
            
            create_notification(
                $tache['id_stagiaire'],
                '📊 Évaluation de votre tâche',
                "Votre tâche **{$tache['titre']}** a été notée : **{$note_labels[$note]}**",
                $note == 'excellent' || $note == 'bon' ? 'success' : 'info',
                "/dashboard/stagiaire/tache-voir.php?id=$id_tache"
            );
            
            $_SESSION['flash']['success'] = "Note enregistrée avec succès !";
        } else {
            $_SESSION['flash']['danger'] = "Erreur lors de l'enregistrement de la note";
        }
        redirect("tache-voir.php?id=$id_tache");
    }
}

// Traitement de la réponse au message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reponse = cleanInput($_POST['reponse']);
    if (!empty($reponse)) {
        create_notification(
            $tache['id_stagiaire'],
            '💬 Réponse de l\'encadreur',
            "Réponse concernant la tâche **{$tache['titre']}** :\n\n$reponse",
            'info',
            "/dashboard/stagiaire/tache-voir.php?id=$id_tache"
        );
        $_SESSION['flash']['success'] = "Réponse envoyée au stagiaire !";
        redirect("tache-voir.php?id=$id_tache");
    }
}

// Récupérer les messages
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE (message LIKE ? OR titre LIKE ?) 
    ORDER BY date_creation DESC LIMIT 20
");
$search = '%' . $tache['titre'] . '%';
$stmt->execute([$search, $search]);
$messages = $stmt->fetchAll();

include '../../includes/header.php';
?>

<style>
    .info-card { background: white; border-radius: 25px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 25px; }
    .task-header { background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 20px; padding: 20px; color: white; margin-bottom: 20px; }
    .stagiaire-info { display: flex; align-items: center; gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 15px; margin-bottom: 20px; }
    .stagiaire-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #667eea; }
    .badge-statut { padding: 6px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
    .badge-a_faire { background: #ffc107; color: #2d3436; }
    .badge-en_cours { background: #17a2b8; color: white; }
    .badge-termine { background: #28a745; color: white; }
    .badge-priorite { padding: 6px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
    .badge-urgente { background: #dc3545; color: white; }
    .badge-haute { background: #fd7e14; color: white; }
    .badge-moyenne { background: #ffc107; color: #2d3436; }
    .badge-basse { background: #6c757d; color: white; }
    .progression-bar { height: 12px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 15px 0; }
    .progression-fill { height: 100%; border-radius: 10px; transition: width 0.3s; }
    .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
    .info-label { width: 150px; font-weight: 600; color: #2d3436; }
    .info-value { flex: 1; color: #6c757d; }
    .message-item { background: #f8f9fa; border-radius: 12px; padding: 12px 15px; margin-bottom: 10px; border-left: 3px solid #667eea; }
    .message-stagiaire { background: #e8f4fd; border-left-color: #28a745; }
    .message-encadreur { background: #fff8e7; border-left-color: #fd7e14; }
    .reply-form { background: #f8f9fa; border-radius: 15px; padding: 15px; margin-top: 15px; }
    .reply-form textarea { border-radius: 12px; border: 1px solid #e0e0e0; resize: vertical; width: 100%; padding: 10px; }
    .reply-form button { background: linear-gradient(135deg, #28a745, #20c997); color: white; border: none; padding: 8px 20px; border-radius: 50px; margin-top: 10px; }
    .note-section { background: #fff8e7; border-radius: 15px; padding: 15px; margin-top: 20px; }
    .note-select { border: 2px solid #e9ecef; border-radius: 10px; padding: 8px 15px; }
    .note-badge { display: inline-block; padding: 5px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
    .note-excellent { background: #d4edda; color: #155724; }
    .note-bon { background: #d1ecf1; color: #0c5460; }
    .note-satisfaisant { background: #fff3cd; color: #856404; }
    .note-insuffisant { background: #f8d7da; color: #721c24; }
    .btn-back { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 10px 25px; border-radius: 50px; transition: all 0.3s; }
    .btn-back:hover { transform: translateY(-2px); color: white; }
    .alert-custom { border-radius: 12px; animation: fadeIn 0.5s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .current-note { background: #e8f4fd; border-radius: 12px; padding: 12px; margin-bottom: 15px; border-left: 4px solid #667eea; }
</style>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <?php if (isset($_SESSION['flash']['success'])): ?>
                <div class="alert alert-success alert-custom alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= $_SESSION['flash']['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash']['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['flash']['danger'])): ?>
                <div class="alert alert-danger alert-custom alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['flash']['danger'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash']['danger']); ?>
            <?php endif; ?>
            
            <div class="task-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0"><?= htmlspecialchars($tache['titre']) ?></h2>
                    <a href="taches.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
                </div>
            </div>
            
            <div class="info-card">
                <div class="stagiaire-info">
                    <img src="<?= getPhotoUrl($tache['photo']) ?>" class="stagiaire-avatar">
                    <div>
                        <h4 class="mb-0"><?= htmlspecialchars($tache['prenom'] . ' ' . $tache['nom']) ?></h4>
                        <p class="mb-0 text-muted"><?= htmlspecialchars($tache['filiere'] ?? '') ?></p>
                        <small><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($tache['email']) ?></small>
                    </div>
                    <div class="ms-auto text-end">
                        <span class="badge-statut badge-<?= $tache['statut'] ?>"><?= $tache['statut'] == 'a_faire' ? 'À faire' : ($tache['statut'] == 'en_cours' ? 'En cours' : 'Terminé') ?></span>
                        <br><span class="badge-priorite badge-<?= $tache['priorite'] ?> mt-2"><?= $tache['priorite'] ?></span>
                    </div>
                </div>
                
                <div class="info-row"><div class="info-label">Description</div><div class="info-value"><?= nl2br(htmlspecialchars($tache['description'])) ?></div></div>
                <div class="info-row"><div class="info-label">Date de création</div><div class="info-value"><?= format_date($tache['date_creation'], 'd/m/Y H:i') ?></div></div>
                <div class="info-row"><div class="info-label">Date d'échéance</div><div class="info-value"><?= format_date($tache['date_echeance'], 'd/m/Y') ?></div></div>
                <div class="info-row"><div class="info-label">Consultation</div><div class="info-value"><?= ($tache['stagiaire_vu']) ? 'Consulté le '.format_date($tache['date_vue'], 'd/m/Y H:i') : 'Non encore consulté' ?></div></div>
                
                <div class="mt-3"><div class="d-flex justify-content-between mb-1"><strong>Progression</strong><strong><?= $tache['progression'] ?>%</strong></div>
                <div class="progression-bar"><div class="progression-fill bg-<?= $tache['progression'] >= 100 ? 'success' : 'primary' ?>" style="width: <?= $tache['progression'] ?>%"></div></div></div>
                
                <!-- Section Notation -->
                <div class="note-section">
                    <h6><i class="fas fa-star me-2 text-warning"></i>Notation de la tâche</h6>
                    
                    <?php
                    $note_labels = [
                        'excellent' => ['label' => '⭐ Excellent', 'class' => 'note-excellent'],
                        'bon' => ['label' => '👍 Bon', 'class' => 'note-bon'],
                        'satisfaisant' => ['label' => '✅ Satisfaisant', 'class' => 'note-satisfaisant'],
                        'insuffisant' => ['label' => '⚠️ Insuffisant', 'class' => 'note-insuffisant']
                    ];
                    
                    // Vérifier si note_encadreur est une chaîne valide
                    $note_value = $tache['note_encadreur'];
                    $current_note = null;
                    
                    if (!empty($note_value) && $note_value !== '0' && $note_value !== 0) {
                        if (isset($note_labels[$note_value])) {
                            $current_note = $note_labels[$note_value];
                        } elseif ($note_value == 1) {
                            // Si c'est un nombre 1, on le convertit en "bon"
                            $current_note = $note_labels['bon'];
                        }
                    }
                    ?>
                    
                    <?php if ($current_note): ?>
                        <div class="current-note">
                            <strong><i class="fas fa-info-circle me-1"></i> Note actuelle :</strong>
                            <span class="note-badge <?= $current_note['class'] ?>"><?= $current_note['label'] ?></span>
                            <?php if ($tache['note_date']): ?>
                                <br><small class="text-muted">Noté le <?= format_date($tache['note_date'], 'd/m/Y H:i') ?></small>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Aucune note pour le moment.</strong>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="mt-2">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <select name="note_encadreur" class="note-select w-100">
                                    <option value="">-- <?= $current_note ? 'Modifier la note' : 'Ajouter une note' ?> --</option>
                                    <option value="excellent" <?= ($tache['note_encadreur'] ?? '') == 'excellent' ? 'selected' : '' ?>>⭐ Excellent</option>
                                    <option value="bon" <?= ($tache['note_encadreur'] ?? '') == 'bon' ? 'selected' : '' ?>>👍 Bon</option>
                                    <option value="satisfaisant" <?= ($tache['note_encadreur'] ?? '') == 'satisfaisant' ? 'selected' : '' ?>>✅ Satisfaisant</option>
                                    <option value="insuffisant" <?= ($tache['note_encadreur'] ?? '') == 'insuffisant' ? 'selected' : '' ?>>⚠️ Insuffisant</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="noter" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i><?= $current_note ? 'Modifier' : 'Enregistrer' ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Messages -->
            <div class="info-card">
                <h5><i class="fas fa-comments me-2"></i>Messages avec le stagiaire</h5>
                <?php if (empty($messages)): ?>
                    <p class="text-muted text-center py-3">Aucun message pour le moment</p>
                <?php else: ?>
                    <?php foreach ($messages as $msg): 
                        $is_from_stagiaire = strpos($msg['message'], 'Message du stagiaire') !== false || strpos($msg['message'], 'tâche') !== false;
                    ?>
                    <div class="message-item <?= $is_from_stagiaire ? 'message-stagiaire' : 'message-encadreur' ?>">
                        <small class="text-muted"><i class="far fa-clock me-1"></i> <?= time_elapsed_string($msg['date_creation']) ?></small>
                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="reply-form">
                    <form method="POST">
                        <textarea name="reponse" rows="3" placeholder="💬 Répondre au stagiaire..." required></textarea>
                        <button type="submit" name="reply_message"><i class="fas fa-paper-plane me-2"></i>Envoyer la réponse</button>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3"><a href="taches.php" class="btn btn-back"><i class="fas fa-arrow-left me-2"></i>Retour à la liste</a></div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>