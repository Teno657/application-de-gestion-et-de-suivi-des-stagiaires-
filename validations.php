<?php
/**
 * Gestion des validations d'inscriptions (Secrétaire)
 * - Envoie des emails au stagiaire, à l'encadreur et à l'admin
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Validations d'inscriptions - School-Connection";

// =============================================
// TRAITEMENT DE LA VALIDATION (ACTIVATION)
// =============================================
if (isset($_GET['valider'])) {
    $id = (int)$_GET['valider'];
    
    try {
        $pdo->beginTransaction();
        
        // ✅ REQUÊTE CORRIGÉE (suppression de AND u.est_actif = 0)
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   s.filiere, s.theme_stage, s.niveau_etude,
                   e.profession, e.specialite, e.entreprise,
                   r.id_encadreur,
                   eu.nom as encadreur_nom, eu.prenom as encadreur_prenom, eu.email as encadreur_email
            FROM utilisateurs u
            LEFT JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire
            LEFT JOIN encadreurs e ON u.id_utilisateur = e.id_encadreur
            LEFT JOIN relations_encadrement r ON u.id_utilisateur = r.id_stagiaire AND (r.statut = 'en_attente' OR r.statut = 'active')
            LEFT JOIN utilisateurs eu ON r.id_encadreur = eu.id_utilisateur
            WHERE u.id_utilisateur = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        // ✅ VÉRIFICATION AVEC LOGS
        error_log("=== VALIDATION UTILISATEUR ID: $id ===");
        error_log("Email: " . ($user['email'] ?? 'NON TROUVÉ'));
        error_log("Rôle: " . ($user['role'] ?? 'NON TROUVÉ'));
        error_log("est_actif: " . ($user['est_actif'] ?? 'NON TROUVÉ'));
        
        if (!$user) {
            throw new Exception("Utilisateur non trouvé");
        }
        
        if ($user['est_actif'] == 1) {
            throw new Exception("Utilisateur déjà activé");
        }
        
        // ✅ ACTIVER LE COMPTE (est_actif = 1)
        $stmt = $pdo->prepare("
            UPDATE utilisateurs 
            SET est_actif = 1
            WHERE id_utilisateur = ?
        ");
        $stmt->execute([$id]);
        
        // ✅ TRAITEMENT SPÉCIFIQUE SELON LE RÔLE
        if ($user['role'] === 'stagiaire') {
            // Mettre à jour le statut du stagiaire
            $stmt = $pdo->prepare("
                UPDATE stagiaires 
                SET statut_inscription = 'actif'
                WHERE id_stagiaire = ?
            ");
            $stmt->execute([$id]);
            
            // Activer la relation d'encadrement si elle existe
            $stmt = $pdo->prepare("
                UPDATE relations_encadrement 
                SET statut = 'active'
                WHERE id_stagiaire = ? AND statut = 'en_attente'
            ");
            $stmt->execute([$id]);
            
            // Mettre à jour le compteur de l'encadreur
            $stmt = $pdo->prepare("
                UPDATE encadreurs e
                JOIN relations_encadrement r ON e.id_encadreur = r.id_encadreur
                SET e.stagiaires_actuels = e.stagiaires_actuels + 1
                WHERE r.id_stagiaire = ? AND r.statut = 'active'
            ");
            $stmt->execute([$id]);
            
            // =============================================
            // ✅ EMAIL AU STAGIAIRE
            // =============================================
            $subject_stagiaire = "✅ Votre inscription à School-Connection a été validée !";
            $message_stagiaire = "
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { padding: 20px; background: white; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                        .footer { text-align: center; padding: 15px; font-size: 12px; color: #777; border-top: 1px solid #eee; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>School-Connection</h1>
                            <p>Votre compte a été activé !</p>
                        </div>
                        <div class='content'>
                            <p>Bonjour <strong>{$user['prenom']} {$user['nom']}</strong>,</p>
                            <p>Votre inscription a été <strong style='color: #4cc9f0;'>validée</strong>.</p>
                            <p>Connectez-vous : <a href='" . APP_URL . "/login.php' class='button'>Se connecter</a></p>
                            <p>Email : {$user['email']}</p>
                            <br>
                            <p>Cordialement,<br>L'équipe School-Connection</p>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " School-Connection - Tous droits réservés</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $email_sent = send_email($user['email'], $subject_stagiaire, $message_stagiaire);
            error_log("Email de validation envoyé à {$user['email']} : " . ($email_sent ? 'SUCCÈS' : 'ÉCHEC'));
            
            // =============================================
            // ✅ EMAIL À L'ENCADREUR (si assigné)
            // =============================================
            if (!empty($user['encadreur_email'])) {
                $subject_encadreur = "📚 Nouveau stagiaire assigné !";
                $message_encadreur = "
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; }
                            .content { padding: 20px; background: white; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>School-Connection</h1>
                                <p>Nouveau stagiaire assigné</p>
                            </div>
                            <div class='content'>
                                <p>Bonjour <strong>{$user['encadreur_prenom']} {$user['encadreur_nom']}</strong>,</p>
                                <p>Un nouveau stagiaire vous a été assigné :</p>
                                <div style='background: #e8f4fd; padding: 15px; border-radius: 10px; margin: 15px 0;'>
                                    <p><strong>Nom :</strong> {$user['prenom']} {$user['nom']}</p>
                                    <p><strong>Email :</strong> {$user['email']}</p>
                                    <p><strong>Filière :</strong> {$user['filiere']}</p>
                                    <p><strong>Thème :</strong> {$user['theme_stage']}</p>
                                </div>
                                <p>Connectez-vous pour voir les détails.</p>
                                <br>
                                <p>Cordialement,<br>L'équipe School-Connection</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                send_email($user['encadreur_email'], $subject_encadreur, $message_encadreur);
                error_log("Email envoyé à l'encadreur: {$user['encadreur_email']}");
            }
            
        } else {
            // =============================================
            // C'EST UN ENCADREUR
            // =============================================
            
            // ✅ EMAIL À L'ENCADREUR
            $subject_encadreur = "✅ Votre inscription à School-Connection a été validée !";
            $message_encadreur = "
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background: white; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>School-Connection</h1>
                            <p>Votre compte a été activé !</p>
                        </div>
                        <div class='content'>
                            <p>Bonjour <strong>{$user['prenom']} {$user['nom']}</strong>,</p>
                            <p>Votre inscription a été <strong style='color: #4cc9f0;'>validée</strong>.</p>
                            <p>Connectez-vous : <a href='" . APP_URL . "/login.php'>Se connecter</a></p>
                            <p>Email : {$user['email']}</p>
                            <br>
                            <p>Cordialement,<br>L'équipe School-Connection</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            send_email($user['email'], $subject_encadreur, $message_encadreur);
            error_log("Email de validation envoyé à l'encadreur: {$user['email']}");
            
            // ✅ EMAIL À L'ADMIN
            $admin = $pdo->query("SELECT email FROM administrateur LIMIT 1")->fetch();
            if ($admin) {
                $subject_admin = "👨‍🏫 Nouvel encadreur validé";
                $message_admin = "
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; }
                            .content { padding: 20px; background: white; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>School-Connection</h1>
                                <p>Nouvel encadreur validé</p>
                            </div>
                            <div class='content'>
                                <p>Bonjour Administrateur,</p>
                                <p>Un nouvel encadreur a été validé :</p>
                                <div style='background: #e8f4fd; padding: 15px; border-radius: 10px; margin: 15px 0;'>
                                    <p><strong>Nom :</strong> {$user['prenom']} {$user['nom']}</p>
                                    <p><strong>Email :</strong> {$user['email']}</p>
                                    <p><strong>Profession :</strong> {$user['profession']}</p>
                                    <p><strong>Spécialité :</strong> {$user['specialite']}</p>
                                    <p><strong>Entreprise :</strong> {$user['entreprise']}</p>
                                </div>
                                <br>
                                <p>Cordialement,<br>L'équipe School-Connection</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                send_email($admin['email'], $subject_admin, $message_admin);
                error_log("Email envoyé à l'admin: {$admin['email']}");
            }
        }
        
        // Créer une notification pour l'utilisateur
        create_notification(
            $id,
            'Inscription validée',
            'Votre inscription a été validée. Vous pouvez maintenant vous connecter.',
            'success',
            '/login.php'
        );
        
        // Journaliser l'action
        log_action($_SESSION['user_id'], 'VALIDATE_INSCRIPTION', "Validation inscription utilisateur ID: $id", 'modification', 'utilisateurs', $id);
        
        $pdo->commit();
        
        $_SESSION['flash']['success'] = "Inscription validée avec succès. Des emails ont été envoyés.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash']['danger'] = "Erreur lors de la validation : " . $e->getMessage();
        error_log("Erreur validation: " . $e->getMessage());
    }
    
    redirect('validations.php');
}

// =============================================
// TRAITEMENT DU REJET
// =============================================
if (isset($_GET['rejeter'])) {
    $id = (int)$_GET['rejeter'];
    $raison = $_GET['raison'] ?? '';
    
    // ✅ Si la raison est vide, on met un message par défaut
    if (empty($raison) || $raison == '') {
        $raison = "Votre dossier ne correspond pas aux critères requis. Pour plus d'informations, veuillez contacter le secrétariat.";
    }
    
    try {
        $pdo->beginTransaction();
        
        // Récupérer les informations de l'utilisateur
        $stmt = $pdo->prepare("
            SELECT * FROM utilisateurs WHERE id_utilisateur = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("Utilisateur non trouvé");
        }
        
        if ($user['est_actif'] == 1) {
            throw new Exception("Cet utilisateur est déjà actif, vous ne pouvez pas le rejeter.");
        }
        
        // =============================================
        // ✅ EMAIL DE REJET AVEC LA RAISON
        // =============================================
        $subject = "❌ Votre inscription à School-Connection";
        $message = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                .header { background: #f72585; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 20px; background: white; border-radius: 0 0 10px 10px; }
                .reason { background: #fff0f0; padding: 15px; border-left: 4px solid #f72585; margin: 15px 0; border-radius: 5px; }
                .footer { text-align: center; padding: 15px; font-size: 12px; color: #777; border-top: 1px solid #eee; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>School-Connection</h1>
                    <p>Votre demande d'inscription</p>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>{$user['prenom']} {$user['nom']}</strong>,</p>
                    <p>Nous avons examiné votre demande d'inscription sur School-Connection.</p>
                    <p>Malheureusement, votre inscription n'a pas été retenue pour la raison suivante :</p>
                    <div class='reason'>
                        <strong>📌 Raison du rejet :</strong><br>
                        " . nl2br(htmlspecialchars($raison)) . "
                    </div>
                    <p>Si vous avez des questions, vous pouvez contacter notre secrétariat.</p>
                    <br>
                    <p>Cordialement,<br>L'équipe School-Connection</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " School-Connection - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $email_sent = send_email($user['email'], $subject, $message);
        error_log("Email de rejet envoyé à {$user['email']} - Succès: " . ($email_sent ? 'OUI' : 'NON'));
        error_log("Raison: " . $raison);
        
        // ✅ SUPPRIMER LE COMPTE (car rejeté)
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([$id]);
        
        // Journaliser l'action
        log_action($_SESSION['user_id'], 'REJECT_INSCRIPTION', "Rejet et suppression inscription utilisateur ID: $id - Raison: $raison", 'suppression', 'utilisateurs', $id);
        
        $pdo->commit();
        
        $_SESSION['flash']['warning'] = "Inscription rejetée et compte supprimé. Un email a été envoyé à l'utilisateur.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash']['danger'] = "Erreur lors du rejet : " . $e->getMessage();
        error_log("Erreur rejet: " . $e->getMessage());
    }
    
    redirect('validations.php');
}

// =============================================
// AFFICHAGE DES INSCRIPTIONS EN ATTENTE
// =============================================

// Filtres
$search = $_GET['search'] ?? '';

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Requête de base - utilisateurs avec est_actif = 0
$where = ["u.est_actif = 0"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Comptage total
$countSql = "SELECT COUNT(*) FROM utilisateurs u WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupération des inscriptions en attente
$sql = "
    SELECT u.*, 
           s.filiere, s.niveau_etude, s.etablissement, s.theme_stage, s.date_debut, s.date_fin,
           e.profession, e.specialite, e.entreprise,
           (SELECT COUNT(*) FROM documents WHERE id_utilisateur = u.id_utilisateur) as nb_documents
    FROM utilisateurs u
    LEFT JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire
    LEFT JOIN encadreurs e ON u.id_utilisateur = e.id_encadreur
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.date_creation DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inscriptions = $stmt->fetchAll();

include '../../includes/header.php';
?>

<!-- ✅ CORRECTION : Espacement pour éviter que le contenu soit caché sous la navbar -->
<style>
    .container-fluid:first-of-type {
        margin-top: 20px;
        padding-top: 10px;
    }
    
    @media (max-width: 768px) {
        .container-fluid:first-of-type {
            margin-top: 10px;
        }
    }
</style>

<div class="container-fluid">
    <!-- Le reste du contenu -->

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Validations d'inscriptions</h1>
                    <p class="text-muted">Gérez les demandes d'inscription en attente</p>
                </div>
                <span class="badge bg-warning fs-6"><?= $total ?> en attente</span>
            </div>
        </div>
    </div>
    
    <!-- Messages flash -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= key($_SESSION['flash']) === 'success' ? 'success' : (key($_SESSION['flash']) === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show">
            <?= current($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    
    <!-- Filtres -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <label for="search" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?= e($search) ?>" placeholder="Nom, email...">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des inscriptions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if ($inscriptions): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Type</th>
                                        <th>Infos</th>
                                        <th>Documents</th>
                                        <th>Date d'inscription</th>
                                        <th>Actions</th>
                                    </thead>
                                <tbody>
                                    <?php foreach ($inscriptions as $inscription): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= getPhotoUrl($inscription['photo']) ?>" 
                                                     alt="" class="rounded-circle me-2" width="40" height="40">
                                                <div>
                                                    <strong><?= e($inscription['prenom'] . ' ' . $inscription['nom']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= e($inscription['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($inscription['role'] === 'stagiaire'): ?>
                                                <span class="badge bg-primary">Stagiaire</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Encadreur</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($inscription['role'] === 'stagiaire'): ?>
                                                <strong><?= e($inscription['filiere'] ?? '—') ?></strong>
                                                <br>
                                                <small class="text-muted"><?= e($inscription['niveau_etude'] ?? '') ?></small>
                                                <br>
                                                <small><?= e(truncate($inscription['theme_stage'] ?? '', 50)) ?></small>
                                            <?php else: ?>
                                                <strong><?= e($inscription['profession'] ?? '—') ?></strong>
                                                <br>
                                                <small class="text-muted"><?= e($inscription['specialite'] ?? '') ?></small>
                                                <br>
                                                <small><?= e($inscription['entreprise'] ?? '') ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (($inscription['nb_documents'] ?? 0) > 0): ?>
                                                <span class="badge bg-success"><?= $inscription['nb_documents'] ?> doc(s)</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">0 doc</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= format_datetime($inscription['date_creation']) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="validation-voir.php?id=<?= $inscription['id_utilisateur'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?valider=<?= $inscription['id_utilisateur'] ?>" 
                                                   class="btn btn-sm btn-outline-success" 
                                                   onclick="return confirm('Valider cette inscription ? Des emails seront envoyés.')"
                                                   title="Valider">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger btn-reject" 
                                                        data-id="<?= $inscription['id_utilisateur'] ?>"
                                                        data-name="<?= e($inscription['prenom'] . ' ' . $inscription['nom']) ?>"
                                                        title="Rejeter">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">Aucune inscription en attente</p>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                                    Précédent
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                                    Suivant
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de rejet -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rejeter l'inscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Vous êtes sur le point de rejeter l'inscription de <strong id="rejectName"></strong>.</p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Cette action supprimera définitivement le compte.</p>
                <div class="mb-3">
                    <label for="raison" class="form-label">Raison du rejet</label>
                    <textarea class="form-control" id="raison" rows="3" placeholder="Expliquez la raison du rejet..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <a href="#" id="rejectLink" class="btn btn-danger">Confirmer le rejet</a>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-reject').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        
        document.getElementById('rejectName').textContent = name;
        const raison = document.getElementById('raison').value;
        document.getElementById('rejectLink').href = '?rejeter=' + id + '&raison=' + encodeURIComponent(raison);
        
        new bootstrap.Modal(document.getElementById('rejectModal')).show();
    });
});

// Mettre à jour le lien avec la raison
document.getElementById('raison').addEventListener('input', function() {
    const id = new URLSearchParams(window.location.search).get('rejeter');
    const btn = document.querySelector('.btn-reject');
    if (btn) {
        const btnId = btn.dataset.id;
        document.getElementById('rejectLink').href = '?rejeter=' + btnId + '&raison=' + encodeURIComponent(this.value);
    } else if (id) {
        document.getElementById('rejectLink').href = '?rejeter=' + id + '&raison=' + encodeURIComponent(this.value);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>