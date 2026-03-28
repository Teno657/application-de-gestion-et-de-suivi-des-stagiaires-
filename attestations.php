<?php
/**
 * Gestion des attestations (Secrétaire)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Gestion des attestations - School-Connection";

// =============================================
// TRAITEMENT DE LA SUPPRESSION (suppression totale)
// =============================================
if (isset($_GET['delete_file']) && is_numeric($_GET['delete_file'])) {
    $id = (int)$_GET['delete_file'];
    
    try {
        // Récupérer les infos pour supprimer le fichier
        $stmt = $pdo->prepare("SELECT chemin_fichier, numero_attestation FROM attestations WHERE id_attestation = ?");
        $stmt->execute([$id]);
        $att = $stmt->fetch();
        
        if ($att) {
            // Supprimer le fichier physique
            if (!empty($att['chemin_fichier'])) {
                $fichier = ROOT_PATH . $att['chemin_fichier'];
                if (file_exists($fichier)) {
                    unlink($fichier);
                }
            }
            
            // Supprimer de la base de données
            $stmt = $pdo->prepare("DELETE FROM attestations WHERE id_attestation = ?");
            $stmt->execute([$id]);
            
            $_SESSION['flash']['success'] = "L'attestation {$att['numero_attestation']} a été supprimée définitivement.";
        }
    } catch (Exception $e) {
        $_SESSION['flash']['danger'] = "Erreur lors de la suppression";
    }
    
    redirect('attestations.php');
}

// =============================================
// TRAITEMENT DE L'ENVOI PAR EMAIL
// =============================================
if (isset($_GET['send_email']) && is_numeric($_GET['send_email'])) {
    $id = (int)$_GET['send_email'];
    
    try {
        // Récupérer les informations de l'attestation et du stagiaire
        $stmt = $pdo->prepare("
            SELECT a.*, u.nom, u.prenom, u.email, a.chemin_fichier, a.numero_attestation
            FROM attestations a
            JOIN stagiaires s ON a.id_stagiaire = s.id_stagiaire
            JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
            WHERE a.id_attestation = ?
        ");
        $stmt->execute([$id]);
        $att = $stmt->fetch();
        
        if ($att && !empty($att['email'])) {
            // Vérifier si le fichier existe
            $fichier = ROOT_PATH . $att['chemin_fichier'];
            if (file_exists($fichier)) {
                // Lire le contenu du fichier
                $file_content = file_get_contents($fichier);
                $extension = pathinfo($fichier, PATHINFO_EXTENSION);
                
                // Préparer l'email
                $subject = "📄 Votre attestation de fin de stage - School-Connection";
                $message = "
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
                                <p>Votre attestation de fin de stage</p>
                            </div>
                            <div class='content'>
                                <p>Bonjour <strong>{$att['prenom']} {$att['nom']}</strong>,</p>
                                <p>Votre attestation de fin de stage <strong>N° {$att['numero_attestation']}</strong> est maintenant disponible.</p>
                                <p>Vous trouverez ci-joint le document en pièce jointe.</p>
                                <p style='text-align: center;'>
                                    <a href='" . APP_URL . "/{$att['chemin_fichier']}' class='button'>📄 Télécharger l'attestation</a>
                                </p>
                                <p>Vous pouvez également la télécharger depuis votre espace personnel sur la plateforme.</p>
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
                
                // Envoyer l'email avec pièce jointe
                require_once ROOT_PATH . 'vendor/phpmailer/src/PHPMailer.php';
                require_once ROOT_PATH . 'vendor/phpmailer/src/SMTP.php';
                require_once ROOT_PATH . 'vendor/phpmailer/src/Exception.php';
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USERNAME;
                $mail->Password   = MAIL_PASSWORD;
                $mail->SMTPSecure = MAIL_SECURE;
                $mail->Port       = MAIL_PORT;
                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($att['email'], $att['prenom'] . ' ' . $att['nom']);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $message;
                $mail->AltBody = strip_tags($message);
                
                // Ajouter la pièce jointe
                $filename = 'attestation_' . $att['numero_attestation'] . '.' . $extension;
                $mail->addStringAttachment($file_content, $filename);
                
                $mail->send();
                
                $_SESSION['flash']['success'] = "L'attestation a été envoyée par email à {$att['email']}";
            } else {
                $_SESSION['flash']['danger'] = "Le fichier de l'attestation n'existe pas";
            }
        } else {
            $_SESSION['flash']['danger'] = "Impossible d'envoyer l'email";
        }
    } catch (Exception $e) {
        $_SESSION['flash']['danger'] = "Erreur lors de l'envoi : " . $e->getMessage();
        error_log("Erreur envoi attestation: " . $e->getMessage());
    }
    
    redirect('attestations.php');
}

// Filtres
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construction de la requête
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR a.numero_attestation LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($statut === 'generees') {
    $where[] = "a.id_attestation IS NOT NULL";
} elseif ($statut === 'en_attente') {
    $where[] = "a.id_attestation IS NULL AND s.statut_inscription = 'termine'";
} elseif ($statut === 'bientot') {
    $where[] = "a.id_attestation IS NULL AND s.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
}

// Récupération des attestations
$sql = "
    SELECT a.*, 
           u.id_utilisateur, 
           u.nom, u.prenom, u.email, u.photo, 
           s.filiere, s.date_debut, s.date_fin, s.statut_inscription,
           DATEDIFF(s.date_fin, CURDATE()) as jours_restants
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    LEFT JOIN attestations a ON s.id_stagiaire = a.id_stagiaire
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.date_fin DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attestations = $stmt->fetchAll();

// Comptage total
$countSql = "SELECT COUNT(*) FROM stagiaires s JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur LEFT JOIN attestations a ON s.id_stagiaire = a.id_stagiaire WHERE " . implode(' AND ', $where);
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute(array_slice($params, 0, -2));
$total = $stmtCount->fetchColumn();
$totalPages = ceil($total / $perPage);

// Statistiques
// Statistiques
$stats = [
    'generees' => $pdo->query("SELECT COUNT(*) FROM attestations")->fetchColumn(),
    'en_attente' => $pdo->query("
        SELECT COUNT(*) 
        FROM stagiaires s 
        WHERE s.statut_inscription = 'termine' 
        AND NOT EXISTS (SELECT 1 FROM attestations a WHERE a.id_stagiaire = s.id_stagiaire)
    ")->fetchColumn(),
    'bientot' => $pdo->query("
        SELECT COUNT(*) 
        FROM stagiaires 
        WHERE date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
        AND statut_inscription = 'actif'
    ")->fetchColumn()
];

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

<style>
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
    }
    
    .attestation-preview {
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .attestation-preview:hover {
        transform: scale(1.05);
        color: #4361ee;
    }
    
    .btn-delete-file {
        color: #dc3545;
    }
    
    .btn-delete-file:hover {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-send-email {
        color: #17a2b8;
    }
    
    .btn-send-email:hover {
        background-color: #17a2b8;
        color: white;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Gestion des attestations</h1>
                    <p class="text-muted">Générez et gérez les attestations de stage</p>
                </div>
                <a href="attestation-generer.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Nouvelle attestation
                </a>
            </div>
        </div>
    </div>
    
    <!-- Messages flash -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= key($_SESSION['flash']) === 'success' ? 'success' : (key($_SESSION['flash']) === 'warning' ? 'warning' : (key($_SESSION['flash']) === 'info' ? 'info' : 'danger')) ?> alert-dismissible fade show">
            <?= current($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    
    <!-- Statistiques -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card bg-primary text-white">
                <div class="stat-number"><?= $stats['generees'] ?></div>
                <div>Attestations générées</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-warning text-white">
                <div class="stat-number"><?= $stats['en_attente'] ?></div>
                <div>En attente (stages terminés)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-info text-white">
                <div class="stat-number"><?= $stats['bientot'] ?></div>
                <div>Stages se terminent bientôt</div>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?= e($search) ?>" placeholder="Nom du stagiaire, numéro d'attestation...">
                        </div>
                        <div class="col-md-4">
                            <label for="statut" class="form-label">Filtrer</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="">Tous</option>
                                <option value="generees" <?= $statut === 'generees' ? 'selected' : '' ?>>Attestations générées</option>
                                <option value="en_attente" <?= $statut === 'en_attente' ? 'selected' : '' ?>>En attente (stages terminés)</option>
                                <option value="bientot" <?= $statut === 'bientot' ? 'selected' : '' ?>>Stages bientôt terminés</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des attestations -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                 <tr>
                                    <th>Stagiaire</th>
                                    <th>Filière</th>
                                    <th>Période de stage</th>
                                    <th>N° Attestation</th>
                                    <th>Date émission</th>
                                    <th>Téléchargements</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                 </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attestations as $a): ?>
                                <tr>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <img src="<?= getPhotoUrl($a['photo']) ?>" 
                                                 alt="" class="rounded-circle me-2" width="35" height="35">
                                            <div>
                                                <strong><?= e($a['prenom'] . ' ' . $a['nom']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= e($a['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle"><?= e($a['filiere']) ?></td>
                                    <td class="align-middle">
                                        <small><?= format_date($a['date_debut']) ?> - <?= format_date($a['date_fin']) ?></small>
                                    </td>
                                    <td class="align-middle">
                                        <?php if ($a['numero_attestation']): ?>
                                            <span class="badge bg-primary"><?= e($a['numero_attestation']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle"><?= $a['date_emission'] ? format_date($a['date_emission']) : '—' ?></td>
                                    <td class="align-middle text-center"><?= (int)($a['nb_telechargements'] ?? 0) ?></td>
                                    <td class="align-middle">
                                        <?php if ($a['id_attestation']): ?>
                                            <span class="badge bg-success">Générée</span>
                                        <?php elseif ($a['statut_inscription'] === 'termine'): ?>
                                            <span class="badge bg-warning">En attente</span>
                                        <?php elseif ($a['jours_restants'] <= 7 && $a['jours_restants'] > 0): ?>
                                            <span class="badge bg-info">Bientôt fin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">En cours</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <div class="btn-group">
                                            <?php if ($a['id_attestation']): ?>
                                                <a href="attestation-voir.php?id=<?= $a['id_attestation'] ?>" 
                                                   class="btn btn-sm btn-outline-primary attestation-preview" title="Voir et télécharger">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                               <a href="?send_email=<?= $a['id_attestation'] ?>" 
   class="btn btn-sm btn-warning btn-send-email" 
   title="Envoyer par email au stagiaire"
   onclick="return confirm('Envoyer cette attestation par email à <?= e($a['prenom'] . ' ' . $a['nom']) ?> ?')">
    <i class="fas fa-envelope"></i>
</a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger btn-delete-file" 
                                                        data-id="<?= $a['id_attestation'] ?>"
                                                        data-name="<?= e($a['numero_attestation']) ?>"
                                                        title="Supprimer définitivement">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <a href="attestation-generer.php?stagiaire=<?= $a['id_utilisateur'] ?>" 
                                                   class="btn btn-sm btn-outline-success" title="Générer">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($attestations)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="fas fa-file-pdf fa-2x mb-2 d-block"></i>
                                        Aucune attestation trouvée
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>">
                                    Précédent
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>">
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.querySelectorAll('.btn-delete-file').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        
        Swal.fire({
            title: '⚠️ Supprimer définitivement',
            html: `Êtes-vous sûr de vouloir supprimer l'attestation <strong>${name}</strong> ?<br><br>
                   <span class="text-danger">⚠️ Cette action est irréversible.<br>
                   L'attestation sera supprimée de la base de données et du serveur.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer définitivement',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete_file=' + id;
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>