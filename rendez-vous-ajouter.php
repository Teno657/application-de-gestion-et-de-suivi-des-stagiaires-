<?php
/**
 * Ajouter/Modifier un rendez-vous (Encadreur)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_any_role(['encadreur_pro', 'encadreur_acro']);

$page_title = "Planifier un rendez-vous - School-Connection";
$user_id = $_SESSION['user_id'];
$error = '';

$edit_id = (int)($_GET['edit'] ?? 0);
$stagiaire_id = (int)($_GET['stagiaire'] ?? 0);

// =============================================
// RÉCUPÉRER LES STAGIAIRES DE L'ENCADREUR
// =============================================
$stagiaires = $pdo->prepare("
    SELECT u.id_utilisateur, u.nom, u.prenom, s.filiere, s.theme_stage
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    WHERE s.id_encadreur = ?
    ORDER BY u.nom, u.prenom
");
$stagiaires->execute([$user_id]);
$liste_stagiaires = $stagiaires->fetchAll();

// Debug - Afficher le nombre de stagiaires trouvés (à supprimer après test)
if (empty($liste_stagiaires)) {
    error_log("Aucun stagiaire trouvé pour l'encadreur ID: " . $user_id);
}

$rdv = [
    'id_stagiaire' => $stagiaire_id,
    'titre' => '',
    'description' => '',
    'date_rdv' => date('Y-m-d\TH:i', strtotime('+1 day 10:00')),
    'duree' => 30,
    'lieu' => '',
    'lien_visio' => '',
    'statut' => 'propose'
];

if ($edit_id > 0) {
    // Mode édition
    $stmt = $pdo->prepare("
        SELECT * FROM rendez_vous 
        WHERE id_rdv = ? AND id_encadreur = ?
    ");
    $stmt->execute([$edit_id, $user_id]);
    $rdv = $stmt->fetch();
    
    if (!$rdv) {
        $_SESSION['flash']['danger'] = "Rendez-vous non trouvé";
        redirect('rendez-vous.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $data = [
            'id_stagiaire' => (int)($_POST['id_stagiaire'] ?? 0),
            'titre' => cleanInput($_POST['titre'] ?? ''),
            'description' => cleanInput($_POST['description'] ?? ''),
            'date_rdv' => $_POST['date_rdv'] ?? '',
            'duree' => (int)($_POST['duree'] ?? 30),
            'lieu' => cleanInput($_POST['lieu'] ?? ''),
            'lien_visio' => cleanInput($_POST['lien_visio'] ?? ''),
            'statut' => $_POST['statut'] ?? 'propose'
        ];
        
        // Validation
        $errors = [];
        
        if ($data['id_stagiaire'] <= 0) $errors[] = 'Veuillez sélectionner un stagiaire';
        if (empty($data['titre'])) $errors[] = 'Le titre est requis';
        if (empty($data['date_rdv'])) $errors[] = 'La date et heure sont requises';
        
        if (strtotime($data['date_rdv']) < time() && $edit_id == 0) {
            $errors[] = 'La date du rendez-vous ne peut pas être dans le passé';
        }
        
        if (empty($data['lieu']) && empty($data['lien_visio'])) {
            $errors[] = 'Veuillez préciser un lieu ou un lien de visioconférence';
        }
        
        // Vérifier que le stagiaire est bien encadré par cet encadreur
        if ($data['id_stagiaire'] > 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM stagiaires 
                WHERE id_stagiaire = ? AND id_encadreur = ?
            ");
            $stmt->execute([$data['id_stagiaire'], $user_id]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = 'Ce stagiaire ne fait pas partie de vos encadrés';
            }
        }
        
        if (empty($errors)) {
            try {
                if ($edit_id > 0) {
                    // Mise à jour
                    $sql = "
                        UPDATE rendez_vous 
                        SET id_stagiaire = ?, titre = ?, description = ?, date_rdv = ?, 
                            duree = ?, lieu = ?, lien_visio = ?, statut = ?
                        WHERE id_rdv = ? AND id_encadreur = ?
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $data['id_stagiaire'],
                        $data['titre'],
                        $data['description'],
                        $data['date_rdv'],
                        $data['duree'],
                        $data['lieu'],
                        $data['lien_visio'],
                        $data['statut'],
                        $edit_id,
                        $user_id
                    ]);
                    
                    // Créer une notification pour le stagiaire
                    create_notification(
                        $data['id_stagiaire'],
                        'Rendez-vous modifié',
                        "Le rendez-vous '{$data['titre']}' a été modifié",
                        'info',
                        "dashboard/stagiaire/rendez-vous.php"
                    );
                    
                    log_action($user_id, 'UPDATE_RDV', "Modification rendez-vous ID: $edit_id", 'modification', 'rendez_vous', $edit_id);
                    $_SESSION['flash']['success'] = "Rendez-vous modifié avec succès";
                    redirect("rendez-vous.php");
                    
                } else {
                    // Insertion
                    $sql = "
                        INSERT INTO rendez_vous (id_encadreur, id_stagiaire, titre, description, date_rdv, duree, lieu, lien_visio, statut)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $user_id,
                        $data['id_stagiaire'],
                        $data['titre'],
                        $data['description'],
                        $data['date_rdv'],
                        $data['duree'],
                        $data['lieu'],
                        $data['lien_visio'],
                        $data['statut']
                    ]);
                    
                    $rdv_id = $pdo->lastInsertId();
                    
                    // Créer une notification pour le stagiaire
                    create_notification(
                        $data['id_stagiaire'],
                        'Nouveau rendez-vous',
                        "Un rendez-vous a été planifié: {$data['titre']} le " . format_datetime($data['date_rdv']),
                        'info',
                        "dashboard/stagiaire/rendez-vous.php"
                    );
                    
                    log_action($user_id, 'CREATE_RDV', "Création rendez-vous pour stagiaire ID: {$data['id_stagiaire']}", 'creation', 'rendez_vous', $rdv_id);
                    $_SESSION['flash']['success'] = "Rendez-vous planifié avec succès";
                    redirect("rendez-vous.php");
                }
                
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
    
    .info-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    
    .info-box i {
        color: #ffc107;
        margin-right: 10px;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3"><?= $edit_id ? 'Modifier' : 'Planifier' ?> un rendez-vous</h1>
                    <p class="text-muted">Organisez une rencontre avec votre stagiaire</p>
                </div>
                <a href="rendez-vous.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (empty($liste_stagiaires) && !$edit_id): ?>
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <strong>Aucun stagiaire assigné</strong><br>
                            Vous n'avez pas encore de stagiaire assigné. Pour pouvoir planifier un rendez-vous, 
                            un stagiaire doit d'abord vous être assigné par l'administrateur.
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <!-- Stagiaire -->
                        <div class="mb-4">
                            <label for="id_stagiaire" class="form-label">Stagiaire</label>
                            <select class="form-select" id="id_stagiaire" name="id_stagiaire" <?= $edit_id ? 'disabled' : '' ?> required>
                                <option value="">-- Sélectionnez un stagiaire --</option>
                                <?php if (empty($liste_stagiaires)): ?>
                                    <option value="" disabled>Aucun stagiaire assigné</option>
                                <?php else: ?>
                                    <?php foreach ($liste_stagiaires as $s): ?>
                                        <option value="<?= $s['id_utilisateur'] ?>" <?= ($rdv['id_stagiaire'] ?? 0) == $s['id_utilisateur'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?>
                                            <?php if (!empty($s['filiere'])): ?>
                                                (<?= htmlspecialchars($s['filiere']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if ($edit_id): ?>
                                <input type="hidden" name="id_stagiaire" value="<?= $rdv['id_stagiaire'] ?>">
                            <?php endif; ?>
                        </div>
                        
                        <!-- Titre -->
                        <div class="mb-4">
                            <label for="titre" class="form-label">Titre du rendez-vous</label>
                            <input type="text" class="form-control" id="titre" name="titre" 
                                   value="<?= htmlspecialchars($rdv['titre'] ?? '') ?>" required>
                        </div>
                        
                        <!-- Date et durée -->
                        <div class="row">
                            <div class="col-md-8 mb-4">
                                <label for="date_rdv" class="form-label">Date et heure</label>
                                <input type="datetime-local" class="form-control" id="date_rdv" name="date_rdv" 
                                       value="<?= date('Y-m-d\TH:i', strtotime($rdv['date_rdv'] ?? '+1 day 10:00')) ?>" required>
                            </div>
                            <div class="col-md-4 mb-4">
                                <label for="duree" class="form-label">Durée (minutes)</label>
                                <select class="form-select" id="duree" name="duree">
                                    <option value="15" <?= ($rdv['duree'] ?? 0) == 15 ? 'selected' : '' ?>>15 min</option>
                                    <option value="30" <?= ($rdv['duree'] ?? 0) == 30 ? 'selected' : '' ?>>30 min</option>
                                    <option value="45" <?= ($rdv['duree'] ?? 0) == 45 ? 'selected' : '' ?>>45 min</option>
                                    <option value="60" <?= ($rdv['duree'] ?? 0) == 60 ? 'selected' : '' ?>>1 heure</option>
                                    <option value="90" <?= ($rdv['duree'] ?? 0) == 90 ? 'selected' : '' ?>>1h30</option>
                                    <option value="120" <?= ($rdv['duree'] ?? 0) == 120 ? 'selected' : '' ?>>2 heures</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Lieu ou lien visio -->
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="lieu" class="form-label">Lieu (physique)</label>
                                <input type="text" class="form-control" id="lieu" name="lieu" 
                                       value="<?= htmlspecialchars($rdv['lieu'] ?? '') ?>" placeholder="Salle, adresse...">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="lien_visio" class="form-label">Lien de visioconférence</label>
                                <input type="url" class="form-control" id="lien_visio" name="lien_visio" 
                                       value="<?= htmlspecialchars($rdv['lien_visio'] ?? '') ?>" placeholder="https://meet.google.com/...">
                            </div>
                        </div>
                        
                        <!-- Statut (pour modification) -->
                        <?php if ($edit_id): ?>
                        <div class="mb-4">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="propose" <?= ($rdv['statut'] ?? '') === 'propose' ? 'selected' : '' ?>>📝 Proposé</option>
                                <option value="confirme" <?= ($rdv['statut'] ?? '') === 'confirme' ? 'selected' : '' ?>>✅ Confirmé</option>
                                <option value="annule" <?= ($rdv['statut'] ?? '') === 'annule' ? 'selected' : '' ?>>❌ Annulé</option>
                                <option value="termine" <?= ($rdv['statut'] ?? '') === 'termine' ? 'selected' : '' ?>>✔️ Terminé</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label">Description (optionnel)</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars($rdv['description'] ?? '') ?></textarea>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-between">
                            <a href="rendez-vous.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary" <?= empty($liste_stagiaires) && !$edit_id ? 'disabled' : '' ?>>
                                <i class="fas fa-save"></i> <?= $edit_id ? 'Mettre à jour' : 'Planifier' ?> le rendez-vous
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Valider que la date n'est pas dans le passé
const dateInput = document.getElementById('date_rdv');
if (dateInput) {
    dateInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const now = new Date();
        if (selectedDate < now && <?= $edit_id ? 'false' : 'true' ?>) {
            alert('La date du rendez-vous ne peut pas être dans le passé');
            this.value = '';
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>