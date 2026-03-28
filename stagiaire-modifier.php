<?php
/**
 * Modifier un stagiaire (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Modifier un stagiaire - School-Connection";
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID stagiaire manquant";
    redirect('stagiaires.php');
}

// Récupérer les informations du stagiaire
$stmt = $pdo->prepare("
    SELECT u.*, s.*,
           r.id_encadreur as encadreur_actuel
    FROM utilisateurs u
    JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire
    LEFT JOIN relations_encadrement r ON s.id_stagiaire = r.id_stagiaire AND r.statut = 'active'
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$id]);
$stagiaire = $stmt->fetch();

if (!$stagiaire) {
    $_SESSION['flash']['danger'] = "Stagiaire non trouvé";
    redirect('stagiaires.php');
}

// Récupérer les filières
$filieres = $pdo->query("SELECT * FROM filieres WHERE actif = 1 ORDER BY nom_filiere")->fetchAll();

// Récupérer les encadreurs disponibles
$encadreurs = $pdo->query("
    SELECT u.id_utilisateur, u.nom, u.prenom, e.profession, e.specialite, e.disponible,
           (e.max_stagiaires - e.stagiaires_actuels) as places_disponibles
    FROM encadreurs e
    JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    WHERE u.est_actif = 1
    ORDER BY u.nom, u.prenom
")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        // Informations de base
        $nom = cleanInput($_POST['nom'] ?? '');
        $prenom = cleanInput($_POST['prenom'] ?? '');
        $telephone = cleanInput($_POST['telephone'] ?? '');
        $adresse = cleanInput($_POST['adresse'] ?? '');
        
        // Informations de stage
        $filiere = cleanInput($_POST['filiere'] ?? '');
        $niveau_etude = $_POST['niveau_etude'] ?? '';
        $etablissement = cleanInput($_POST['etablissement'] ?? '');
        $theme_stage = cleanInput($_POST['theme_stage'] ?? '');
        $date_debut = $_POST['date_debut'] ?? '';
        $date_fin = $_POST['date_fin'] ?? '';
        $statut_inscription = $_POST['statut_inscription'] ?? 'en_attente';
        $id_encadreur = (int)($_POST['id_encadreur'] ?? 0);
        
        // Statut du compte
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;
        $est_bloque = isset($_POST['est_bloque']) ? 1 : 0;
        $raison_blocage = cleanInput($_POST['raison_blocage'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($nom)) $errors[] = 'Le nom est requis';
        if (empty($prenom)) $errors[] = 'Le prénom est requis';
        if (empty($filiere)) $errors[] = 'La filière est requise';
        if (empty($niveau_etude)) $errors[] = 'Le niveau d\'étude est requis';
        if (empty($theme_stage)) $errors[] = 'Le thème du stage est requis';
        if (empty($date_debut)) $errors[] = 'La date de début est requise';
        if (empty($date_fin)) $errors[] = 'La date de fin est requise';
        
        if (strtotime($date_fin) <= strtotime($date_debut)) {
            $errors[] = 'La date de fin doit être postérieure à la date de début';
        }
        
        // Vérifier si l'encadreur a des places disponibles (si différent de l'actuel)
        if ($id_encadreur > 0 && $id_encadreur != $stagiaire['encadreur_actuel']) {
            $stmt = $pdo->prepare("
                SELECT (max_stagiaires - stagiaires_actuels) as places 
                FROM encadreurs 
                WHERE id_encadreur = ?
            ");
            $stmt->execute([$id_encadreur]);
            $places = $stmt->fetchColumn();
            
            if ($places <= 0) {
                $errors[] = 'Cet encadreur n\'a plus de places disponibles';
            }
        }
        
        // Upload de la photo
        $photo_path = $stagiaire['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = upload_file($_FILES['photo'], UPLOADS_PATH . 'photos/', ALLOWED_IMAGES, 2 * 1024 * 1024);
            if ($result['success']) {
                // Supprimer l'ancienne photo si ce n'est pas la photo par défaut
                if (!empty($stagiaire['photo']) && $stagiaire['photo'] !== 'default-avatar.png' && file_exists(ROOT_PATH . $stagiaire['photo'])) {
                    unlink(ROOT_PATH . $stagiaire['photo']);
                }
                $photo_path = 'uploads/photos/' . $result['filename'];
            } else {
                $errors[] = 'Erreur photo : ' . $result['message'];
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Mettre à jour l'utilisateur
                $stmt = $pdo->prepare("
                    UPDATE utilisateurs 
                    SET nom = ?, prenom = ?, telephone = ?, adresse = ?, photo = ?,
                        est_actif = ?, est_bloque = ?, raison_blocage = ?
                    WHERE id_utilisateur = ?
                ");
                $stmt->execute([$nom, $prenom, $telephone, $adresse, $photo_path, 
                               $est_actif, $est_bloque, $raison_blocage, $id]);
                
                // Mettre à jour le stagiaire
                $stmt = $pdo->prepare("
                    UPDATE stagiaires 
                    SET filiere = ?, niveau_etude = ?, etablissement = ?, theme_stage = ?,
                        date_debut = ?, date_fin = ?, statut_inscription = ?
                    WHERE id_stagiaire = ?
                ");
                $stmt->execute([$filiere, $niveau_etude, $etablissement, $theme_stage,
                               $date_debut, $date_fin, $statut_inscription, $id]);
                
                // Gérer le changement d'encadreur
                if ($id_encadreur != $stagiaire['encadreur_actuel']) {
                    
                    // Si un ancien encadreur existait, mettre fin à la relation
                    if ($stagiaire['encadreur_actuel'] > 0) {
                        $pdo->prepare("
                            UPDATE relations_encadrement 
                            SET statut = 'terminee', date_fin = NOW()
                            WHERE id_stagiaire = ? AND statut = 'active'
                        ")->execute([$id]);
                        
                        // Décrémenter le compteur de l'ancien encadreur
                        $pdo->prepare("
                            UPDATE encadreurs 
                            SET stagiaires_actuels = stagiaires_actuels - 1
                            WHERE id_encadreur = ?
                        ")->execute([$stagiaire['encadreur_actuel']]);
                    }
                    
                    // Si un nouvel encadreur est sélectionné, créer la relation
                    if ($id_encadreur > 0) {
                        $pdo->prepare("
                            INSERT INTO relations_encadrement (id_stagiaire, id_encadreur, date_debut, statut)
                            VALUES (?, ?, ?, 'active')
                        ")->execute([$id, $id_encadreur, $date_debut]);
                        
                        // Incrémenter le compteur du nouvel encadreur
                        $pdo->prepare("
                            UPDATE encadreurs 
                            SET stagiaires_actuels = stagiaires_actuels + 1
                            WHERE id_encadreur = ?
                        ")->execute([$id_encadreur]);
                    }
                }
                
                // Journaliser l'action
                log_action($_SESSION['user_id'], 'UPDATE_STAGIAIRE', "Modification du stagiaire ID: $id", 'modification', 'stagiaires', $id);
                
                $pdo->commit();
                
                $_SESSION['flash']['success'] = "Le stagiaire a été modifié avec succès";
                redirect("stagiaire-voir.php?id=$id");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur lors de la modification : " . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Modifier un stagiaire</h1>
                    <p class="text-muted"><?= e($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?></p>
                </div>
                <a href="stagiaire-voir.php?id=<?= $id ?>" class="btn btn-outline-secondary">
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
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <!-- Photo -->
                        <div class="text-center mb-4">
                            <img src="<?= getPhotoUrl($stagiaire['photo']) ?>" 
                                 alt="Photo" class="rounded-circle img-thumbnail mb-2" 
                                 style="width: 120px; height: 120px; object-fit: cover;"
                                 id="photoPreview">
                            <div>
                                <label for="photo" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-camera"></i> Changer la photo
                                </label>
                                <input type="file" id="photo" name="photo" class="d-none" accept="image/*" data-preview="photoPreview">
                            </div>
                        </div>
                        
                        <!-- Informations personnelles -->
                        <h5 class="mb-3">Informations personnelles</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="nom" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?= e($stagiaire['nom']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="prenom" class="form-label">Prénom</label>
                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                       value="<?= e($stagiaire['prenom']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?= e($stagiaire['email']) ?>" readonly disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" 
                                       value="<?= e($stagiaire['telephone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= e($stagiaire['adresse'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Informations académiques -->
                        <h5 class="mb-3">Informations académiques</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="filiere" class="form-label">Filière</label>
                                <select class="form-select" id="filiere" name="filiere" required>
                                    <option value="">Sélectionnez une filière</option>
                                    <?php foreach ($filieres as $f): ?>
                                        <option value="<?= e($f['nom_filiere']) ?>" <?= $stagiaire['filiere'] === $f['nom_filiere'] ? 'selected' : '' ?>>
                                            <?= e($f['nom_filiere']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="niveau_etude" class="form-label">Niveau d'étude</label>
                                <select class="form-select" id="niveau_etude" name="niveau_etude" required>
                                    <option value="">Sélectionnez</option>
                                    <option value="Licence1" <?= $stagiaire['niveau_etude'] === 'Licence1' ? 'selected' : '' ?>>Licence 1</option>
                                    <option value="Licence2" <?= $stagiaire['niveau_etude'] === 'Licence2' ? 'selected' : '' ?>>Licence 2</option>
                                    <option value="Licence3" <?= $stagiaire['niveau_etude'] === 'Licence3' ? 'selected' : '' ?>>Licence 3</option>
                                    <option value="Master1" <?= $stagiaire['niveau_etude'] === 'Master1' ? 'selected' : '' ?>>Master 1</option>
                                    <option value="Master2" <?= $stagiaire['niveau_etude'] === 'Master2' ? 'selected' : '' ?>>Master 2</option>
                                    <option value="Doctorat" <?= $stagiaire['niveau_etude'] === 'Doctorat' ? 'selected' : '' ?>>Doctorat</option>
                                    <option value="BTS" <?= $stagiaire['niveau_etude'] === 'BTS' ? 'selected' : '' ?>>BTS</option>
                                    <option value="DUT" <?= $stagiaire['niveau_etude'] === 'DUT' ? 'selected' : '' ?>>DUT</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="etablissement" class="form-label">Établissement</label>
                                <input type="text" class="form-control" id="etablissement" name="etablissement" 
                                       value="<?= e($stagiaire['etablissement'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label for="theme_stage" class="form-label">Thème du stage</label>
                                <textarea class="form-control" id="theme_stage" name="theme_stage" rows="3" required><?= e($stagiaire['theme_stage']) ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Période de stage -->
                        <h5 class="mb-3">Période de stage</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="date_debut" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                       value="<?= $stagiaire['date_debut'] ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="date_fin" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                       value="<?= $stagiaire['date_fin'] ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="statut_inscription" class="form-label">Statut</label>
                                <select class="form-select" id="statut_inscription" name="statut_inscription">
                                    <option value="en_attente" <?= $stagiaire['statut_inscription'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="actif" <?= $stagiaire['statut_inscription'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                    <option value="termine" <?= $stagiaire['statut_inscription'] === 'termine' ? 'selected' : '' ?>>Terminé</option>
                                    <option value="abandon" <?= $stagiaire['statut_inscription'] === 'abandon' ? 'selected' : '' ?>>Abandon</option>
                                    <option value="suspendu" <?= $stagiaire['statut_inscription'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Assignation encadreur -->
                        <h5 class="mb-3">Assigner un encadreur</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <select class="form-select" id="id_encadreur" name="id_encadreur">
                                    <option value="0">-- Aucun encadreur --</option>
                                    <?php foreach ($encadreurs as $e): ?>
                                        <option value="<?= $e['id_utilisateur'] ?>" 
                                            <?= ($stagiaire['encadreur_actuel'] ?? 0) == $e['id_utilisateur'] ? 'selected' : '' ?>
                                            <?= $e['places_disponibles'] <= 0 && ($stagiaire['encadreur_actuel'] ?? 0) != $e['id_utilisateur'] ? 'disabled' : '' ?>>
                                            <?= e($e['prenom'] . ' ' . $e['nom']) ?> - <?= e($e['profession']) ?>
                                            <?php if ($e['places_disponibles'] <= 0 && ($stagiaire['encadreur_actuel'] ?? 0) != $e['id_utilisateur']): ?>
                                                (Complet)
                                            <?php elseif ($e['places_disponibles'] > 0): ?>
                                                (<?= $e['places_disponibles'] ?> place(s) dispo)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    <?php if ($stagiaire['encadreur_actuel'] > 0): ?>
                                        Encadreur actuel: <strong><?= e($stagiaire['encadreur_nom'] ?? '') ?></strong>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Statut du compte -->
                        <h5 class="mb-3">Statut du compte</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" 
                                           <?= $stagiaire['est_actif'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_actif">
                                        Compte actif
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_bloque" name="est_bloque" 
                                           <?= $stagiaire['est_bloque'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_bloque">
                                        Bloquer le compte
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4" id="blocage_div" style="<?= !$stagiaire['est_bloque'] ? 'display:none;' : '' ?>">
                            <div class="col-12">
                                <label for="raison_blocage" class="form-label">Raison du blocage</label>
                                <textarea class="form-control" id="raison_blocage" name="raison_blocage" rows="2"><?= e($stagiaire['raison_blocage'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-between">
                            <a href="stagiaire-voir.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Aperçu de la photo
document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photoPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Afficher/masquer raison du blocage
document.getElementById('est_bloque').addEventListener('change', function() {
    document.getElementById('blocage_div').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php include '../../includes/footer.php'; ?>