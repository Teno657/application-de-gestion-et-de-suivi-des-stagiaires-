<?php
/**
 * Liste des évaluations - Encadreur
 * Design moderne avec envoi de notifications
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_any_role(['encadreur_pro', 'encadreur_acro']);

$page_title = "Mes évaluations - School-Connection";
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// =============================================
// FONCTION POUR ENVOYER UNE NOTIFICATION
// =============================================
function envoyerNotificationEvaluation($pdo, $destinataire_id, $evaluation, $stagiaire, $encadreur, $type = 'stagiaire') {
    // Calcul de la moyenne
    $notes = [
        $evaluation['note_technique'], $evaluation['note_communication'], 
        $evaluation['note_initiative'], $evaluation['note_ponctualite'],
        $evaluation['note_travail_equipe'], $evaluation['note_adaptabilite'],
        $evaluation['note_qualite_travail'], $evaluation['note_autonomie']
    ];
    $notes_valides = array_filter($notes);
    $moyenne = !empty($notes_valides) ? round(array_sum($notes_valides) / count($notes_valides), 1) : null;
    
    // Construction du message
    $message = "📊 **Évaluation du stage**\n\n";
    $message .= "👨‍🎓 **Stagiaire :** " . $stagiaire['prenom'] . " " . $stagiaire['nom'] . "\n";
    $message .= "🎓 **Filière :** " . ($stagiaire['filiere'] ?? 'Non renseignée') . "\n";
    $message .= "📅 **Date d'évaluation :** " . format_date($evaluation['date_evaluation'], 'd/m/Y') . "\n\n";
    
    $message .= "📝 **Notes détaillées :**\n";
    $message .= "• Compétences techniques : " . ($evaluation['note_technique'] ?: 'Non noté') . "/10\n";
    $message .= "• Communication : " . ($evaluation['note_communication'] ?: 'Non noté') . "/10\n";
    $message .= "• Initiative : " . ($evaluation['note_initiative'] ?: 'Non noté') . "/10\n";
    $message .= "• Ponctualité : " . ($evaluation['note_ponctualite'] ?: 'Non noté') . "/10\n";
    $message .= "• Travail en équipe : " . ($evaluation['note_travail_equipe'] ?: 'Non noté') . "/10\n";
    $message .= "• Adaptabilité : " . ($evaluation['note_adaptabilite'] ?: 'Non noté') . "/10\n";
    $message .= "• Qualité du travail : " . ($evaluation['note_qualite_travail'] ?: 'Non noté') . "/10\n";
    $message .= "• Autonomie : " . ($evaluation['note_autonomie'] ?: 'Non noté') . "/10\n\n";
    
    if ($moyenne) {
        $message .= "⭐ **Moyenne générale :** " . $moyenne . "/10\n\n";
    }
    
    $message .= "💪 **Points forts :**\n" . ($evaluation['points_forts'] ?: 'Aucun renseigné') . "\n\n";
    $message .= "🎯 **Points d'amélioration :**\n" . ($evaluation['points_amelioration'] ?: 'Aucun renseigné') . "\n\n";
    $message .= "📝 **Commentaire :**\n" . ($evaluation['commentaire'] ?: 'Aucun commentaire') . "\n\n";
    
    $recommandations = [
        'excellent' => '🏆 Excellent - Très bonne performance',
        'bon' => '👍 Bon - Bonne performance',
        'satisfaisant' => '✅ Satisfaisant - Performance correcte',
        'insuffisant' => '⚠️ Insuffisant - Besoin de progression'
    ];
    $message .= "🎯 **Recommandation :** " . ($recommandations[$evaluation['recommandation']] ?? $evaluation['recommandation']) . "\n\n";
    
    $message .= "👨‍🏫 **Évaluateur :** " . $encadreur['prenom'] . " " . $encadreur['nom'] . "\n";
    $message .= "📧 Contact : " . $encadreur['email'];
    
    // Titre selon le destinataire
    $titre = ($type == 'stagiaire') ? "📋 Nouvelle évaluation reçue" : "📊 Évaluation de stage à consulter";
    $lien = ($type == 'stagiaire') ? "dashboard/stagiaire/evaluation-voir.php?id=" . $evaluation['id_evaluation'] : "dashboard/admin/evaluations.php";
    
    // Insérer la notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (id_utilisateur, titre, message, type_notification, lien, date_creation)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$destinataire_id, $titre, $message, 'evaluation', $lien]);
}

// =============================================
// TRAITEMENT DE L'ENVOI D'ÉVALUATION
// =============================================
if (isset($_POST['send_evaluation']) && isset($_POST['evaluation_id'])) {
    $evaluation_id = (int)$_POST['evaluation_id'];
    
    // Vérifier que l'évaluation appartient à l'encadreur
    $stmt = $pdo->prepare("
        SELECT e.*, u.nom, u.prenom, u.email as stagiaire_email,
               s.filiere, s.theme_stage
        FROM evaluations e
        JOIN stagiaires s ON e.id_stagiaire = s.id_stagiaire
        JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
        WHERE e.id_evaluation = ? AND e.id_encadreur = ?
    ");
    $stmt->execute([$evaluation_id, $user_id]);
    $evaluation = $stmt->fetch();
    
    if ($evaluation) {
        // Récupérer les infos de l'encadreur
        $stmt = $pdo->prepare("SELECT nom, prenom, email FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([$user_id]);
        $encadreur = $stmt->fetch();
        
        // Récupérer les infos du stagiaire
        $stmt = $pdo->prepare("SELECT nom, prenom, email, filiere FROM stagiaires s JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur WHERE s.id_stagiaire = ?");
        $stmt->execute([$evaluation['id_stagiaire']]);
        $stagiaire = $stmt->fetch();
        
        // Envoyer la notification au stagiaire
        $envoye_stagiaire = envoyerNotificationEvaluation($pdo, $evaluation['id_stagiaire'], $evaluation, $stagiaire, $encadreur, 'stagiaire');
        
        // Envoyer la notification à l'admin
        $stmt = $pdo->query("SELECT id_administrateur FROM administrateur");
        $admins = $stmt->fetchAll();
        $envoye_admin = false;
        foreach ($admins as $admin) {
            if (envoyerNotificationEvaluation($pdo, $admin['id_administrateur'], $evaluation, $stagiaire, $encadreur, 'admin')) {
                $envoye_admin = true;
            }
        }
        
        if ($envoye_stagiaire && $envoye_admin) {
            $message = "✅ Évaluation envoyée avec succès au stagiaire et à l'administrateur !";
            $message_type = "success";
            
            // Marquer l'évaluation comme envoyée (si la colonne existe)
            try {
                $pdo->prepare("UPDATE evaluations SET envoye = 1, date_envoi = NOW() WHERE id_evaluation = ?")->execute([$evaluation_id]);
            } catch (Exception $e) {}
        } else {
            $message = "⚠️ Erreur lors de l'envoi de l'évaluation";
            $message_type = "danger";
        }
    } else {
        $message = "⚠️ Évaluation non trouvée";
        $message_type = "danger";
    }
}

// =============================================
// RÉCUPÉRATION DES DONNÉES
// =============================================
$stagiaires = $pdo->prepare("
    SELECT 
        u.id_utilisateur,
        u.nom,
        u.prenom,
        u.photo,
        u.email,
        s.filiere,
        s.theme_stage,
        s.date_debut,
        s.date_fin,
        (SELECT COUNT(*) FROM evaluations WHERE id_stagiaire = s.id_stagiaire) as nb_evaluations,
        (SELECT AVG((note_technique + note_communication + note_initiative + 
                     note_ponctualite + note_travail_equipe + note_adaptabilite + 
                     note_qualite_travail + note_autonomie) / 8) 
         FROM evaluations WHERE id_stagiaire = s.id_stagiaire) as moyenne_generale,
        (SELECT MAX(date_evaluation) FROM evaluations WHERE id_stagiaire = s.id_stagiaire) as derniere_evaluation,
        (SELECT recommandation FROM evaluations WHERE id_stagiaire = s.id_stagiaire 
         ORDER BY date_evaluation DESC LIMIT 1) as derniere_recommandation,
        (SELECT id_evaluation FROM evaluations WHERE id_stagiaire = s.id_stagiaire 
         ORDER BY date_evaluation DESC LIMIT 1) as derniere_evaluation_id
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE s.id_encadreur = ?
    ORDER BY u.nom, u.prenom
");
$stagiaires->execute([$user_id]);
$liste_stagiaires = $stagiaires->fetchAll();

// Statistiques
$total_stagiaires = count($liste_stagiaires);
$total_evaluations = 0;
$moyenne_generale_totale = 0;
$stagiaires_excellents = 0;

foreach ($liste_stagiaires as $s) {
    $total_evaluations += $s['nb_evaluations'];
    if ($s['moyenne_generale']) {
        $moyenne_generale_totale += $s['moyenne_generale'];
        if ($s['moyenne_generale'] >= 8) $stagiaires_excellents++;
    }
}
$moyenne_generale_totale = $total_stagiaires > 0 ? round($moyenne_generale_totale / $total_stagiaires, 1) : 0;

include '../../includes/header.php';
?>

<style>
    /* ===== STYLES MODERNES ===== */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
    
    .evaluations-container {
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
    
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
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
        width: 55px;
        height: 55px;
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
        font-size: 2.2rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 5px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .stagiaire-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
        gap: 25px;
    }
    
    .stagiaire-card {
        background: white;
        border-radius: 25px;
        overflow: hidden;
        transition: all 0.4s;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    }
    
    .stagiaire-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }
    
    .card-header-gradient {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 20px;
        position: relative;
        color: white;
    }
    
    .stagiaire-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .stagiaire-name {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .note-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 1rem;
        gap: 5px;
    }
    
    .note-excellent { background: linear-gradient(135deg, #00b09b, #96c93d); color: white; }
    .note-bon { background: linear-gradient(135deg, #4facfe, #00f2fe); color: white; }
    .note-moyen { background: linear-gradient(135deg, #fa709a, #fee140); color: #2d3436; }
    .note-insuffisant { background: linear-gradient(135deg, #f093fb, #f5576c); color: white; }
    .note-aucune { background: #e9ecef; color: #6c757d; }
    
    .btn-send {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 50px;
        font-size: 0.8rem;
        transition: all 0.3s;
    }
    
    .btn-send:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        color: white;
    }
    
    .btn-send:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .btn-evaluer {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 12px;
        font-weight: 500;
    }
    
    .btn-evaluer:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }
    
    .btn-historique {
        border: 2px solid #667eea;
        background: transparent;
        color: #667eea;
        padding: 8px 15px;
        border-radius: 12px;
        font-weight: 500;
    }
    
    .btn-historique:hover {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-color: transparent;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 25px;
        grid-column: 1 / -1;
    }
    
    .alert-custom {
        border-radius: 15px;
        border: none;
        animation: slideInDown 0.5s ease;
    }
    
    @keyframes slideInDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
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
    
    .stagiaire-card {
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
    }
    
    .stagiaire-card:nth-child(1) { animation-delay: 0.1s; }
    .stagiaire-card:nth-child(2) { animation-delay: 0.2s; }
    .stagiaire-card:nth-child(3) { animation-delay: 0.3s; }
    .stagiaire-card:nth-child(4) { animation-delay: 0.4s; }
    .stagiaire-card:nth-child(5) { animation-delay: 0.5s; }
</style>

<div class="evaluations-container">
    <div class="container-fluid">
        <!-- En-tête -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-star me-3"></i>Mes évaluations</h1>
                    <p>Suivez la progression de vos stagiaires et évaluez leurs compétences</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="evaluation-ajouter.php" class="btn btn-light btn-lg">
                        <i class="fas fa-plus me-2"></i>Nouvelle évaluation
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Message de confirmation -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-custom alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?= $total_stagiaires ?></div>
                <div class="stat-label">Stagiaires encadrés</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?= $total_evaluations ?></div>
                <div class="stat-label">Évaluations réalisées</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-number"><?= $moyenne_generale_totale ?>/10</div>
                <div class="stat-label">Moyenne générale</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                <div class="stat-number"><?= $stagiaires_excellents ?></div>
                <div class="stat-label">Stagiaires excellents</div>
            </div>
        </div>
        
        <!-- Liste des stagiaires -->
        <div class="stagiaire-grid">
            <?php if (empty($liste_stagiaires)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-graduate"></i>
                    <h4>Aucun stagiaire assigné</h4>
                    <p>Vous n'avez pas encore de stagiaire à évaluer.</p>
                    <a href="../admin/encadreurs.php" class="btn btn-primary">Contacter l'administrateur</a>
                </div>
            <?php else: ?>
                <?php foreach ($liste_stagiaires as $stagiaire): 
                    $moyenne = $stagiaire['moyenne_generale'] ? round($stagiaire['moyenne_generale'], 1) : null;
                    $a_evaluation = $stagiaire['derniere_evaluation_id'] > 0;
                    
                    if ($moyenne) {
                        if ($moyenne >= 8) { $note_class = 'note-excellent'; $note_label = 'Excellent'; }
                        elseif ($moyenne >= 6) { $note_class = 'note-bon'; $note_label = 'Bon'; }
                        elseif ($moyenne >= 4) { $note_class = 'note-moyen'; $note_label = 'Moyen'; }
                        else { $note_class = 'note-insuffisant'; $note_label = 'Insuffisant'; }
                    }
                ?>
                <div class="stagiaire-card">
                    <div class="card-header-gradient">
                        <div class="d-flex align-items-center">
                            <img src="<?= getPhotoUrl($stagiaire['photo']) ?>" 
                                 alt="" class="stagiaire-avatar me-3">
                            <div>
                                <div class="stagiaire-name"><?= htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?></div>
                                <div class="small opacity-75"><?= htmlspecialchars($stagiaire['filiere'] ?? 'Filière non renseignée') ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-3">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Moyenne générale</span>
                                <?php if ($moyenne): ?>
                                    <span class="note-badge <?= $note_class ?>">
                                        <i class="fas fa-star"></i> <?= $moyenne ?>/10
                                    </span>
                                <?php else: ?>
                                    <span class="note-badge note-aucune">
                                        <i class="fas fa-clock"></i> Non évalué
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($moyenne): ?>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $moyenne >= 8 ? 'success' : ($moyenne >= 6 ? 'info' : ($moyenne >= 4 ? 'warning' : 'danger')) ?>" 
                                         style="width: <?= ($moyenne / 10) * 100 ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="bg-light rounded-3 p-2 text-center">
                                    <small class="text-muted d-block">Évaluations</small>
                                    <strong><?= $stagiaire['nb_evaluations'] ?></strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-light rounded-3 p-2 text-center">
                                    <small class="text-muted d-block">Dernière</small>
                                    <strong><?= $stagiaire['derniere_evaluation'] ? format_date($stagiaire['derniere_evaluation'], 'd/m/Y') : '-' ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="evaluation-ajouter.php?stagiaire=<?= $stagiaire['id_utilisateur'] ?>" 
                               class="btn btn-evaluer flex-grow-1 text-center">
                                <i class="fas fa-plus"></i> Évaluer
                            </a>
                            <a href="evaluations-stagiaire.php?id=<?= $stagiaire['id_utilisateur'] ?>" 
                               class="btn btn-historique flex-grow-1 text-center">
                                <i class="fas fa-list"></i> Historique
                            </a>
                        </div>
                        
                        <?php if ($a_evaluation): ?>
                            <div class="mt-3 pt-2 border-top">
                                <form method="POST" action="" class="text-center">
                                    <input type="hidden" name="evaluation_id" value="<?= $stagiaire['derniere_evaluation_id'] ?>">
                                    <button type="submit" name="send_evaluation" class="btn btn-send w-100">
                                        <i class="fas fa-paper-plane me-2"></i>Envoyer l'évaluation au stagiaire et à l'admin
                                    </button>
                                </form>
                                <small class="text-muted d-block text-center mt-2">
                                    <i class="fas fa-info-circle"></i> Un récapitulatif complet sera envoyé par notification
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pied de page -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="bg-white rounded-4 p-3 shadow-sm">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <i class="fas fa-bell text-primary me-2"></i>
                            <strong>Notifications envoyées</strong>
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-check-circle text-success me-1"></i> Stagiaire reçoit le récapitulatif complet
                            <span class="mx-2">•</span>
                            <i class="fas fa-check-circle text-success me-1"></i> Administrateur reçoit une copie
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>