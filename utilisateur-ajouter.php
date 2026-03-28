<?php
/**
 * Ajouter un utilisateur (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Ajouter un utilisateur - School-Connection";
$error = '';
$success = '';

// Récupérer les filières pour le formulaire
$filieres = $pdo->query("SELECT * FROM filieres WHERE actif = 1 ORDER BY nom_filiere")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $role = $_POST['role'] ?? '';
        $nom = cleanInput($_POST['nom'] ?? '');
        $prenom = cleanInput($_POST['prenom'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $telephone = cleanInput($_POST['telephone'] ?? '');
        $adresse = cleanInput($_POST['adresse'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($nom)) $errors[] = 'Le nom est requis';
        if (empty($prenom)) $errors[] = 'Le prénom est requis';
        if (empty($email)) $errors[] = 'L\'email est requis';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        if (empty($password)) $errors[] = 'Le mot de passe est requis';
        elseif (strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Cet email est déjà utilisé';
        }
        
        // Validation selon le rôle
        switch ($role) {
            case 'stagiaire':
                $filiere = cleanInput($_POST['filiere'] ?? '');
                $niveau_etude = $_POST['niveau_etude'] ?? '';
                $etablissement = cleanInput($_POST['etablissement'] ?? '');
                $theme_stage = cleanInput($_POST['theme_stage'] ?? '');
                $date_debut = $_POST['date_debut'] ?? '';
                $date_fin = $_POST['date_fin'] ?? '';
                
                if (empty($filiere)) $errors[] = 'La filière est requise';
                if (empty($niveau_etude)) $errors[] = 'Le niveau d\'étude est requis';
                if (empty($theme_stage)) $errors[] = 'Le thème du stage est requis';
                if (empty($date_debut)) $errors[] = 'La date de début est requise';
                if (empty($date_fin)) $errors[] = 'La date de fin est requise';
                break;
                
            case 'encadreur_pro':
            case 'encadreur_acro':
                $profession = cleanInput($_POST['profession'] ?? '');
                $specialite = cleanInput($_POST['specialite'] ?? '');
                $entreprise = cleanInput($_POST['entreprise'] ?? '');
                $bio = cleanInput($_POST['bio'] ?? '');
                
                if (empty($profession)) $errors[] = 'La profession est requise';
                if (empty($specialite)) $errors[] = 'La spécialité est requise';
                if (empty($entreprise)) $errors[] = 'L\'entreprise est requise';
                break;
                
            case 'secretaire':
                $service = cleanInput($_POST['service'] ?? '');
                $matricule = cleanInput($_POST['matricule'] ?? '');
                break;
        }
        
        // Upload de la photo
        $photo_path = 'default-avatar.png';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = upload_file($_FILES['photo'], UPLOADS_PATH . 'photos/', ALLOWED_IMAGES, 2 * 1024 * 1024);
            if ($result['success']) {
                $photo_path = 'uploads/photos/' . $result['filename'];
            } else {
                $errors[] = 'Erreur photo : ' . $result['message'];
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Hash du mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer l'utilisateur
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, telephone, adresse, photo, role, ip_inscription)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nom, $prenom, $email, $hashed_password, $telephone, $adresse, $photo_path, $role,
                    get_client_ip()
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Insérer les informations spécifiques
                switch ($role) {
                    case 'stagiaire':
                        $stmt = $pdo->prepare("
                            INSERT INTO stagiaires (id_stagiaire, filiere, niveau_etude, etablissement, theme_stage, date_debut, date_fin, statut_inscription)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'actif')
                        ");
                        $stmt->execute([$user_id, $filiere, $niveau_etude, $etablissement, $theme_stage, $date_debut, $date_fin]);
                        break;
                        
                    case 'encadreur_pro':
                    case 'encadreur_acro':
                        $stmt = $pdo->prepare("
                            INSERT INTO encadreurs (id_encadreur, profession, specialite, entreprise, bio, disponible)
                            VALUES (?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$user_id, $profession, $specialite, $entreprise, $bio]);
                        break;
                        
                    case 'secretaire':
                        $stmt = $pdo->prepare("
                            INSERT INTO secretaires (id_secretaire, service, matricule)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$user_id, $service, $matricule]);
                        break;
                }
                
                // Journaliser l'action
                log_action($_SESSION['user_id'], 'CREATE_USER', "Création de l'utilisateur $email", 'creation', 'utilisateurs', $user_id);
                
                $pdo->commit();
                
                $_SESSION['flash']['success'] = "L'utilisateur a été créé avec succès";
                redirect("utilisateur-voir.php?id=$user_id");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur lors de la création : " . $e->getMessage();
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
            <h1 class="h3">Ajouter un utilisateur</h1>
            <p class="text-muted">Créez un nouvel utilisateur sur la plateforme</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <!-- Type de compte -->
                        <div class="mb-4">
                            <label class="form-label">Type de compte</label>
                            <div class="row g-2">
                                <div class="col">
                                    <input type="radio" class="btn-check" name="role" id="role_stagiaire" value="stagiaire" checked>
                                    <label class="btn btn-outline-primary w-100" for="role_stagiaire">
                                        <i class="fas fa-user-graduate"></i> Stagiaire
                                    </label>
                                </div>
                                <div class="col">
                                    <input type="radio" class="btn-check" name="role" id="role_encadreur_pro" value="encadreur_pro">
                                    <label class="btn btn-outline-primary w-100" for="role_encadreur_pro">
                                        <i class="fas fa-chalkboard-teacher"></i> Encadreur Pro
                                    </label>
                                </div>
                                <div class="col">
                                    <input type="radio" class="btn-check" name="role" id="role_encadreur_acro" value="encadreur_acro">
                                    <label class="btn btn-outline-primary w-100" for="role_encadreur_acro">
                                        <i class="fas fa-university"></i> Encadreur Académique
                                    </label>
                                </div>
                                <div class="col">
                                    <input type="radio" class="btn-check" name="role" id="role_secretaire" value="secretaire">
                                    <label class="btn btn-outline-primary w-100" for="role_secretaire">
                                        <i class="fas fa-user-tie"></i> Secrétaire
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informations personnelles -->
                        <h5 class="mb-3">Informations personnelles</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="nom" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                            <div class="col-md-6">
                                <label for="prenom" class="form-label">Prénom</label>
                                <input type="text" class="form-control" id="prenom" name="prenom" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="text-muted">Minimum 8 caractères</small>
                            </div>
                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone">
                            </div>
                            <div class="col-md-6">
                                <label for="photo" class="form-label">Photo de profil</label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            </div>
                            <div class="col-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <!-- Formulaire Stagiaire -->
                        <div id="form_stagiaire" class="role-form">
                            <h5 class="mb-3">Informations de stage</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="filiere" class="form-label">Filière</label>
                                    <select class="form-select" id="filiere" name="filiere">
                                        <option value="">Sélectionnez</option>
                                        <?php foreach ($filieres as $f): ?>
                                            <option value="<?= e($f['nom_filiere']) ?>"><?= e($f['nom_filiere']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="niveau_etude" class="form-label">Niveau d'étude</label>
                                    <select class="form-select" id="niveau_etude" name="niveau_etude">
                                        <option value="">Sélectionnez</option>
                                        <option value="Licence1">Licence 1</option>
                                        <option value="Licence2">Licence 2</option>
                                        <option value="Licence3">Licence 3</option>
                                        <option value="Master1">Master 1</option>
                                        <option value="Master2">Master 2</option>
                                        <option value="Doctorat">Doctorat</option>
                                        <option value="BTS">BTS</option>
                                        <option value="DUT">DUT</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="etablissement" class="form-label">Établissement</label>
                                    <input type="text" class="form-control" id="etablissement" name="etablissement">
                                </div>
                                <div class="col-12">
                                    <label for="theme_stage" class="form-label">Thème du stage</label>
                                    <textarea class="form-control" id="theme_stage" name="theme_stage" rows="3"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_debut" class="form-label">Date de début</label>
                                    <input type="date" class="form-control" id="date_debut" name="date_debut">
                                </div>
                                <div class="col-md-6">
                                    <label for="date_fin" class="form-label">Date de fin</label>
                                    <input type="date" class="form-control" id="date_fin" name="date_fin">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Formulaire Encadreur -->
                        <div id="form_encadreur" class="role-form" style="display:none;">
                            <h5 class="mb-3">Informations professionnelles</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="profession" class="form-label">Profession</label>
                                    <input type="text" class="form-control" id="profession" name="profession">
                                </div>
                                <div class="col-md-6">
                                    <label for="specialite" class="form-label">Spécialité</label>
                                    <input type="text" class="form-control" id="specialite" name="specialite">
                                </div>
                                <div class="col-md-6">
                                    <label for="entreprise" class="form-label">Entreprise</label>
                                    <input type="text" class="form-control" id="entreprise" name="entreprise">
                                </div>
                                <div class="col-12">
                                    <label for="bio" class="form-label">Biographie</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Formulaire Secrétaire -->
                        <div id="form_secretaire" class="role-form" style="display:none;">
                            <h5 class="mb-3">Informations secrétariat</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="service" class="form-label">Service</label>
                                    <input type="text" class="form-control" id="service" name="service">
                                </div>
                                <div class="col-md-6">
                                    <label for="matricule" class="form-label">Matricule</label>
                                    <input type="text" class="form-control" id="matricule" name="matricule">
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-between">
                            <a href="utilisateurs.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Créer l'utilisateur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion de l'affichage des formulaires selon le rôle
document.querySelectorAll('input[name="role"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.role-form').forEach(form => {
            form.style.display = 'none';
        });
        
        if (this.value === 'stagiaire') {
            document.getElementById('form_stagiaire').style.display = 'block';
        } else if (this.value === 'encadreur_pro' || this.value === 'encadreur_acro') {
            document.getElementById('form_encadreur').style.display = 'block';
        } else if (this.value === 'secretaire') {
            document.getElementById('form_secretaire').style.display = 'block';
        }
    });
});

// Calcul automatique de la date de fin (3 mois après début)
document.getElementById('date_debut')?.addEventListener('change', function() {
    const dateFin = document.getElementById('date_fin');
    if (this.value && !dateFin.value) {
        const date = new Date(this.value);
        date.setMonth(date.getMonth() + 3);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        dateFin.value = `${year}-${month}-${day}`;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>