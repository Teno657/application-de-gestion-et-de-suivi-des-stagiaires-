<?php
/**
 * Dashboard Stagiaire - Version Ultra Moderne
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('stagiaire');

$page_title = "Mon Dashboard - School-Connection";
$user_id = $_SESSION['user_id'];

// Récupérer les informations du stagiaire
$stmt = $pdo->prepare("
    SELECT u.*, s.filiere, s.niveau_etude, s.theme_stage, s.date_debut, s.date_fin, s.statut_inscription,
           e.id_encadreur, eu.nom as encadreur_nom, eu.prenom as encadreur_prenom, eu.photo as encadreur_photo,
           e.profession, e.entreprise
    FROM utilisateurs u
    JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire
    LEFT JOIN relations_encadrement r ON s.id_stagiaire = r.id_stagiaire AND r.statut = 'active'
    LEFT JOIN encadreurs e ON r.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs eu ON e.id_encadreur = eu.id_utilisateur
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$user_id]);
$stagiaire = $stmt->fetch();

// Récupérer les statistiques des tâches
$taches_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termine,
        SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN statut = 'a_faire' THEN 1 ELSE 0 END) as a_faire,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN date_echeance < CURDATE() AND statut != 'termine' THEN 1 ELSE 0 END) as en_retard
    FROM taches
    WHERE id_stagiaire = ?
");
$taches_stats->execute([$user_id]);
$stats_taches = $taches_stats->fetch();

// Récupérer les 5 dernières tâches
$taches_recentes = $pdo->prepare("
    SELECT t.*, u.nom as encadreur_nom, u.prenom as encadreur_prenom
    FROM taches t
    LEFT JOIN encadreurs e ON t.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    WHERE t.id_stagiaire = ?
    ORDER BY 
        CASE 
            WHEN t.statut = 'termine' THEN 2 
            WHEN t.date_echeance < CURDATE() AND t.statut != 'termine' THEN 0
            ELSE 1 
        END,
        t.date_echeance ASC
    LIMIT 5
");
$taches_recentes->execute([$user_id]);
$liste_taches = $taches_recentes->fetchAll();

// Récupérer les prochains rendez-vous
$rendez_vous = $pdo->prepare("
    SELECT r.*, u.nom as encadreur_nom, u.prenom as encadreur_prenom
    FROM rendez_vous r
    JOIN encadreurs e ON r.id_encadreur = e.id_encadreur
    JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    WHERE r.id_stagiaire = ? AND r.date_rdv >= NOW() AND r.statut != 'annule'
    ORDER BY r.date_rdv ASC
    LIMIT 3
");
$rendez_vous->execute([$user_id]);
$prochains_rdv = $rendez_vous->fetchAll();

// Récupérer les documents récents
$documents = $pdo->prepare("
    SELECT * FROM documents 
    WHERE id_stagiaire = ? 
    ORDER BY date_upload DESC
    LIMIT 4
");
$documents->execute([$user_id]);
$docs_recents = $documents->fetchAll();

// Récupérer les notifications non lues
$notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE id_utilisateur = ? AND est_lue = 0
    ORDER BY date_creation DESC
    LIMIT 5
");
$notifications->execute([$user_id]);
$notifs = $notifications->fetchAll();

// Calculer la progression du stage
$debut = new DateTime($stagiaire['date_debut']);
$fin = new DateTime($stagiaire['date_fin']);
$aujourdhui = new DateTime();
$total_jours = $debut->diff($fin)->days;
$jours_ecoules = $debut->diff($aujourdhui)->days;
$progression_stage = $total_jours > 0 ? min(100, round(($jours_ecoules / $total_jours) * 100)) : 0;

include '../../includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        position: relative;
        overflow-x: hidden;
    }
    
    /* Particules animées */
    .particle {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        pointer-events: none;
        z-index: 0;
    }
    
    .particle-1 {
        width: 400px;
        height: 400px;
        top: -200px;
        right: -200px;
        background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
        animation: float 20s ease-in-out infinite;
    }
    
    .particle-2 {
        width: 500px;
        height: 500px;
        bottom: -250px;
        left: -250px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        animation: float 25s ease-in-out infinite reverse;
    }
    
    .particle-3 {
        width: 300px;
        height: 300px;
        top: 30%;
        left: 10%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 15s ease-in-out infinite;
    }
    
    @keyframes float {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        33% { transform: translate(30px, -30px) rotate(120deg); }
        66% { transform: translate(-20px, 20px) rotate(240deg); }
    }
    
    /* Conteneur principal */
    .dashboard-container {
        position: relative;
        z-index: 10;
        padding: 30px;
    }
    
    /* Cartes glassmorphism */
    .glass-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 30px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
        position: relative;
        height: 100%;
    }
    
    .glass-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(
            45deg,
            transparent,
            rgba(255, 255, 255, 0.2),
            transparent
        );
        transform: rotate(45deg);
        animation: shine 6s infinite;
        pointer-events: none;
    }
    
    @keyframes shine {
        0% { transform: translateX(-100%) rotate(45deg); }
        20%, 100% { transform: translateX(100%) rotate(45deg); }
    }
    
    .glass-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 30px 60px rgba(67, 97, 238, 0.3);
        border-color: rgba(67, 97, 238, 0.5);
    }
    
    /* Carte de bienvenue */
    .welcome-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 30px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
    }
    
    .welcome-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 200%;
        background: rgba(255, 255, 255, 0.1);
        transform: rotate(45deg);
        animation: shine 8s infinite;
    }
    
    .welcome-title {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 10px;
        text-shadow: 2px 2px 20px rgba(0,0,0,0.3);
    }
    
    .welcome-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    
    .welcome-stats {
        display: flex;
        gap: 30px;
        margin-top: 20px;
    }
    
    .welcome-stat {
        text-align: center;
    }
    
    .welcome-stat-value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }
    
    .welcome-stat-label {
        font-size: 0.9rem;
        opacity: 0.8;
    }
    
    /* Stat cards */
    .stat-card {
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 5px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .stat-label {
        color: #666;
        font-weight: 500;
    }
    
    .stat-change {
        margin-top: 10px;
        font-size: 0.9rem;
    }
    
    .stat-change.positive {
        color: #4cc9f0;
    }
    
    .stat-change.negative {
        color: #f72585;
    }
    
    /* Progress bar */
    .progress-container {
        margin-top: 15px;
    }
    
    .progress-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        color: #666;
    }
    
    .progress-bar-bg {
        width: 100%;
        height: 8px;
        background: rgba(0, 0, 0, 0.05);
        border-radius: 4px;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 4px;
        transition: width 1s cubic-bezier(0.2, 0.9, 0.3, 1);
    }
    
    /* Task list */
    .task-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .task-item {
        padding: 15px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
    
    .task-item:hover {
        background: rgba(102, 126, 234, 0.05);
        transform: translateX(5px);
    }
    
    .task-item:last-child {
        border-bottom: none;
    }
    
    .task-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    
    .task-title {
        font-weight: 600;
        color: #333;
    }
    
    .task-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .task-badge.urgent {
        background: #f72585;
        color: white;
    }
    
    .task-badge.high {
        background: #f8961e;
        color: white;
    }
    
    .task-badge.medium {
        background: #4cc9f0;
        color: white;
    }
    
    .task-badge.low {
        background: #4361ee;
        color: white;
    }
    
    .task-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
        color: #999;
    }
    
    .task-date {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .task-date.overdue {
        color: #f72585;
        font-weight: 600;
    }
    
    /* Document grid */
    .document-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        padding: 15px;
    }
    
    .document-item {
        background: rgba(102, 126, 234, 0.05);
        border-radius: 15px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
        border: 1px solid rgba(102, 126, 234, 0.1);
    }
    
    .document-item:hover {
        background: rgba(102, 126, 234, 0.1);
        transform: translateY(-3px);
        border-color: #667eea;
    }
    
    .document-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f72585, #b5179e);
        color: white;
    }
    
    .document-info {
        flex: 1;
    }
    
    .document-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 3px;
    }
    
    .document-meta {
        font-size: 0.8rem;
        color: #999;
    }
    
    /* Notification list */
    .notification-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .notification-item {
        padding: 15px 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        gap: 15px;
        transition: all 0.3s ease;
    }
    
    .notification-item:hover {
        background: rgba(102, 126, 234, 0.05);
    }
    
    .notification-item.unread {
        background: rgba(102, 126, 234, 0.1);
    }
    
    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        font-size: 1.2rem;
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }
    
    .notification-message {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .notification-time {
        color: #999;
        font-size: 0.8rem;
    }
    
    /* Quick actions */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-top: 30px;
    }
    
    .action-btn {
        background: white;
        border: 1px solid rgba(102, 126, 234, 0.2);
        border-radius: 20px;
        padding: 20px 15px;
        text-align: center;
        color: #333;
        text-decoration: none;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .action-btn:hover {
        transform: translateY(-8px) scale(1.05);
        border-color: #667eea;
        box-shadow: 0 15px 30px rgba(102, 126, 234, 0.3);
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .action-btn:hover .action-icon {
        color: white;
        -webkit-text-fill-color: white;
    }
    
    .action-icon {
        font-size: 2rem;
        margin-bottom: 10px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        transition: all 0.3s ease;
    }
    
    .action-title {
        font-size: 1rem;
        font-weight: 600;
    }
    
    /* Section titles */
    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        color: #667eea;
    }
    
    /* Encadreur card */
    .encadreur-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 30px;
        padding: 25px;
        color: white;
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .encadreur-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 3px solid white;
        object-fit: cover;
    }
    
    .encadreur-info {
        flex: 1;
    }
    
    .encadreur-name {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .encadreur-profil {
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .encadreur-contact {
        display: flex;
        gap: 15px;
        margin-top: 10px;
    }
    
    .encadreur-contact a {
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9rem;
        padding: 5px 10px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        transition: all 0.3s ease;
    }
    
    .encadreur-contact a:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }
    
    /* Empty states */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .empty-state p {
        margin-bottom: 15px;
    }
    
    .empty-state a {
        display: inline-block;
        padding: 10px 20px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        text-decoration: none;
        border-radius: 30px;
        transition: all 0.3s ease;
    }
    
    .empty-state a:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }
    
    /* Badges */
    .badge-custom {
        padding: 5px 15px;
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .badge-success {
        background: rgba(76, 201, 240, 0.1);
        color: #4cc9f0;
    }
    
    .badge-warning {
        background: rgba(248, 150, 30, 0.1);
        color: #f8961e;
    }
    
    .badge-danger {
        background: rgba(247, 37, 133, 0.1);
        color: #f72585;
    }
    
    .badge-info {
        background: rgba(67, 97, 238, 0.1);
        color: #4361ee;
    }
    
    /* Responsive */
    @media (max-width: 1200px) {
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 15px;
        }
        
        .welcome-title {
            font-size: 1.8rem;
        }
        
        .welcome-stats {
            flex-wrap: wrap;
        }
        
        .stat-card {
            padding: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
        }
        
        .encadreur-card {
            flex-direction: column;
            text-align: center;
        }
        
        .encadreur-contact {
            justify-content: center;
        }
        
        .document-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Particules -->
<div class="particle particle-1"></div>
<div class="particle particle-2"></div>
<div class="particle particle-3"></div>

<div class="dashboard-container">
    <!-- Carte de bienvenue -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="welcome-title">
                    👋 Bonjour, <?= e($stagiaire['prenom']) ?> !
                </h1>
                <p class="welcome-subtitle">
                    <?php if ($stagiaire['statut_inscription'] === 'actif'): ?>
                        Bienvenue dans votre espace personnel. Suivez votre progression et gérez vos activités.
                    <?php elseif ($stagiaire['statut_inscription'] === 'en_attente'): ?>
                        Votre inscription est en attente de validation. Vous pourrez bientôt accéder à toutes les fonctionnalités.
                    <?php else: ?>
                        Votre stage est terminé. Consultez vos attestations et documents.
                    <?php endif; ?>
                </p>
                <div class="welcome-stats">
                    <div class="welcome-stat">
                        <div class="welcome-stat-value"><?= $stats_taches['total'] ?? 0 ?></div>
                        <div class="welcome-stat-label">Tâches</div>
                    </div>
                    <div class="welcome-stat">
                        <div class="welcome-stat-value"><?= count($prochains_rdv) ?></div>
                        <div class="welcome-stat-label">Rendez-vous</div>
                    </div>
                    <div class="welcome-stat">
                        <div class="welcome-stat-value"><?= count($docs_recents) ?></div>
                        <div class="welcome-stat-label">Documents</div>
                    </div>
                </div>
            </div>
           <div class="col-md-4 text-end d-flex align-items-center justify-content-end">
    <div class="text-center me-3">
     
           
    </div>
   
</div>
        </div>
    </div>
    
    <!-- Stats cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="glass-card stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats_taches['total'] ?? 0 ?></div>
                    <div class="stat-label">Tâches totales</div>
                    <?php if (($stats_taches['en_retard'] ?? 0) > 0): ?>
                        <div class="stat-change negative">
                            <i class="fas fa-exclamation-circle"></i> <?= $stats_taches['en_retard'] ?> en retard
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats_taches['termine'] ?? 0 ?></div>
                    <div class="stat-label">Tâches terminées</div>
                    <?php 
                    $taux_reussite = ($stats_taches['total'] ?? 0) > 0 ? round(($stats_taches['termine'] / $stats_taches['total']) * 100) : 0;
                    ?>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> <?= $taux_reussite ?>% de réussite
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f72585);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats_taches['en_cours'] ?? 0 ?></div>
                    <div class="stat-label">Tâches en cours</div>
                    <div class="stat-change">
                        <i class="fas fa-spinner fa-spin"></i> <?= $stats_taches['a_faire'] ?? 0 ?> à faire
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="glass-card stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= count($prochains_rdv) ?></div>
                    <div class="stat-label">Rendez-vous</div>
                    <?php if (count($prochains_rdv) > 0): ?>
                        <div class="stat-change positive">
                            Prochain: <?= format_date($prochains_rdv[0]['date_rdv'], 'd/m') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Progression du stage -->
    <div class="glass-card mb-4 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">
                <i class="fas fa-chart-line text-primary me-2"></i>Progression du stage
            </h5>
            <span class="badge-custom badge-info"><?= $progression_stage ?>%</span>
        </div>
        
        <div class="progress-bar-bg mb-2">
            <div class="progress-fill" style="width: <?= $progression_stage ?>%;"></div>
        </div>
        
        <div class="row text-center mt-4">
            <div class="col-4">
                <div class="fw-bold text-primary"><?= format_date($stagiaire['date_debut'], 'd/m/Y') ?></div>
                <small class="text-muted">Début</small>
            </div>
            <div class="col-4">
                <?php
                $reste = $total_jours - $jours_ecoules;
                $pourcentage = $reste > 0 ? 'text-success' : 'text-danger';
                ?>
                <div class="fw-bold <?= $pourcentage ?>"><?= $reste ?> jours</div>
                <small class="text-muted">Restants</small>
            </div>
            <div class="col-4">
                <div class="fw-bold text-primary"><?= format_date($stagiaire['date_fin'], 'd/m/Y') ?></div>
                <small class="text-muted">Fin</small>
            </div>
        </div>
    </div>
    
    <!-- Encadreur -->
    <?php if ($stagiaire['id_encadreur']): ?>
    <div class="glass-card mb-4">
        <div class="encadreur-card">
            <img src="<?= getPhotoUrl($stagiaire['encadreur_photo'] ?? '') ?>" alt="Encadreur" class="encadreur-avatar">
            <div class="encadreur-info">
                <div class="encadreur-name">
                    <?= e($stagiaire['encadreur_prenom'] . ' ' . $stagiaire['encadreur_nom']) ?>
                </div>
                <div class="encadreur-profil">
                    <?= e($stagiaire['profession']) ?> - <?= e($stagiaire['entreprise']) ?>
                </div>
                <div class="encadreur-contact">
                    <a href="../messagerie/nouvelle.php?destinataire=<?= $stagiaire['id_encadreur'] ?>">
                        <i class="fas fa-envelope"></i> Message
                    </a>
                    <a href="rendez-vous-demander.php?encadreur=<?= $stagiaire['id_encadreur'] ?>">
                        <i class="fas fa-calendar-plus"></i> Rendez-vous
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="glass-card mb-4 p-4 text-center">
        <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
        <h5>Vous n'avez pas encore d'encadreur</h5>
        <p class="text-muted mb-3">Choisissez un encadreur pour commencer votre stage</p>
        <a href="encadreur-choisir.php" class="btn btn-primary">
            <i class="fas fa-search"></i> Choisir un encadreur
        </a>
    </div>
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- Tâches récentes -->
        <div class="col-lg-6">
            <div class="glass-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="section-title mb-0">
                            <i class="fas fa-tasks"></i>Tâches récentes
                        </h5>
                        <a href="taches.php" class="btn btn-sm btn-outline-primary">
                            Voir tout <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($liste_taches): ?>
                        <div class="task-list">
                            <?php foreach ($liste_taches as $tache): ?>
                                <div class="task-item">
                                    <div class="task-header">
                                        <span class="task-title"><?= e(truncate($tache['titre'], 40)) ?></span>
                                        <span class="task-badge <?= 
                                            $tache['priorite'] === 'urgente' ? 'urgent' : 
                                            ($tache['priorite'] === 'haute' ? 'high' : 
                                            ($tache['priorite'] === 'moyenne' ? 'medium' : 'low'))
                                        ?>">
                                            <?= $tache['priorite'] ?>
                                        </span>
                                    </div>
                                    <div class="task-footer">
                                        <span class="task-date <?= strtotime($tache['date_echeance']) < time() && $tache['statut'] != 'termine' ? 'overdue' : '' ?>">
                                            <i class="far fa-calendar"></i>
                                            <?= format_date($tache['date_echeance']) ?>
                                            <?php if (strtotime($tache['date_echeance']) < time() && $tache['statut'] != 'termine'): ?>
                                                <span class="ms-1">(En retard)</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="badge bg-<?= get_status_badge($tache['statut']) ?>">
                                            <?= $tache['statut'] ?>
                                        </span>
                                    </div>
                                    <div class="progress-container mt-2">
                                        <div class="progress-info">
                                            <small>Progression</small>
                                            <small><?= $tache['progression'] ?>%</small>
                                        </div>
                                        <div class="progress-bar-bg">
                                            <div class="progress-fill" style="width: <?= $tache['progression'] ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <p>Aucune tâche pour le moment</p>
                            <a href="taches.php">Voir toutes les tâches</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Prochains rendez-vous -->
        <div class="col-lg-6">
            <div class="glass-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="section-title mb-0">
                            <i class="fas fa-calendar-check"></i>Prochains rendez-vous
                        </h5>
                        <a href="rendez-vous.php" class="btn btn-sm btn-outline-primary">
                            Voir tout <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($prochains_rdv): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($prochains_rdv as $rdv): ?>
                                <div class="list-group-item d-flex align-items-center gap-3 p-3">
                                    <div class="text-center" style="min-width: 60px;">
                                        <div class="fw-bold text-primary"><?= format_date($rdv['date_rdv'], 'd') ?></div>
                                        <small class="text-muted"><?= format_date($rdv['date_rdv'], 'M') ?></small>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= e($rdv['titre']) ?></h6>
                                        <small class="text-muted d-block">
                                            <i class="far fa-clock me-1"></i> <?= format_date($rdv['date_rdv'], 'H:i') ?> 
                                            (<?= $rdv['duree'] ?> min)
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-chalkboard-teacher me-1"></i> 
                                            Avec <?= e($rdv['encadreur_prenom'] . ' ' . $rdv['encadreur_nom']) ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= get_status_badge($rdv['statut']) ?>">
                                        <?= $rdv['statut'] ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar"></i>
                            <p>Aucun rendez-vous planifié</p>
                            <a href="rendez-vous-demander.php">Demander un rendez-vous</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4 mt-2">
        <!-- Documents récents -->
        <div class="col-lg-6">
            <div class="glass-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="section-title mb-0">
                            <i class="fas fa-file-alt"></i>Documents récents
                        </h5>
                        <a href="documents.php" class="btn btn-sm btn-outline-primary">
                            Voir tout <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($docs_recents): ?>
                        <div class="document-grid">
                            <?php foreach ($docs_recents as $doc): ?>
                                <a href="<?= APP_URL ?>/<?= $doc['chemin'] ?>" target="_blank" class="document-item text-decoration-none">
                                    <div class="document-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="document-info">
                                        <div class="document-name"><?= e(truncate($doc['nom_fichier'], 20)) ?></div>
                                        <div class="document-meta">
                                            <?= format_filesize($doc['taille']) ?>
                                            <?php if ($doc['est_valide']): ?>
                                                <span class="badge bg-success ms-1">Validé</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-upload"></i>
                            <p>Aucun document uploadé</p>
                            <a href="document-uploader.php">Uploader un document</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="col-lg-6">
            <div class="glass-card">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="section-title mb-0">
                            <i class="fas fa-bell"></i>Notifications
                            <?php if (count($notifs) > 0): ?>
                                <span class="badge bg-danger ms-2"><?= count($notifs) ?></span>
                            <?php endif; ?>
                        </h5>
                        <a href="notifications.php" class="btn btn-sm btn-outline-primary">
                            Voir tout <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($notifs): ?>
                        <div class="notification-list">
                            <?php foreach ($notifs as $notif): ?>
                                <div class="notification-item unread">
                                    <div class="notification-icon">
                                        <i class="fas fa-<?= $notif['type_notification'] === 'success' ? 'check-circle' : 'info-circle' ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?= e($notif['titre'] ?? 'Notification') ?></div>
                                        <div class="notification-message"><?= e(truncate($notif['message'], 60)) ?></div>
                                        <div class="notification-time">
                                            <?= time_elapsed_string($notif['date_creation']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>Aucune notification</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Actions rapides -->
    <div class="quick-actions">
        <a href="tache-voir.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-tasks"></i></div>
            <div class="action-title">Mes tâches</div>
        </a>
        <a href="rendez-vous-demander.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-calendar-plus"></i></div>
            <div class="action-title">Demander RDV</div>
        </a>
        <a href="document-uploader.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-upload"></i></div>
            <div class="action-title">Upload document</div>
        </a>
        <a href="../messagerie/nouvelle.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-envelope"></i></div>
            <div class="action-title">Nouveau message</div>
        </a>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>