<?php
/**
 * Page de connexion - Version avec "Se souvenir de moi"
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Rediriger si déjà connecté
if (is_logged_in()) {
    redirect('dashboard/index.php');
}

$error = '';
$saved_email = '';

// Récupérer l'email du cookie
if (isset($_COOKIE['remember_email'])) {
    $saved_email = $_COOKIE['remember_email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email)) {
        $error = 'Veuillez entrer votre email';
    } else {
        try {
            $user = null;
            $is_admin = false;
            $password_valid = false;
            $stored_hash = null;
            
            // 1. Chercher dans administrateur
            $stmt = $pdo->prepare("SELECT * FROM administrateur WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                $stored_hash = $admin['mot_de_passe'];
                
                // Cas 1: L'utilisateur a tapé un mot de passe
                if (!empty($password)) {
                    if (password_verify($password, $stored_hash)) {
                        $password_valid = true;
                        $user = $admin;
                        $is_admin = true;
                    }
                }
                // Cas 2: L'utilisateur n'a pas tapé de mot de passe mais a un cookie
                elseif (isset($_COOKIE['remember_password_hash'])) {
                    $cookie_hash = $_COOKIE['remember_password_hash'];
                    if (password_verify($stored_hash, $cookie_hash)) {
                        $password_valid = true;
                        $user = $admin;
                        $is_admin = true;
                    }
                }
            }
            
            // 2. Chercher dans utilisateurs si pas trouvé dans admin
            if (!$user) {
                $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
                $stmt->execute([$email]);
                $utilisateur = $stmt->fetch();
                
                if ($utilisateur) {
                    $stored_hash = $utilisateur['mot_de_passe'];
                    
                    // Cas 1: L'utilisateur a tapé un mot de passe
                    if (!empty($password)) {
                        if (password_verify($password, $stored_hash)) {
                            $password_valid = true;
                            $user = $utilisateur;
                        }
                    }
                    // Cas 2: L'utilisateur n'a pas tapé de mot de passe mais a un cookie
                    elseif (isset($_COOKIE['remember_password_hash'])) {
                        $cookie_hash = $_COOKIE['remember_password_hash'];
                        if (password_verify($stored_hash, $cookie_hash)) {
                            $password_valid = true;
                            $user = $utilisateur;
                        }
                    }
                    
                    // Vérifications supplémentaires pour utilisateur normal
                    if ($password_valid && $user) {
                        if (!$user['est_actif']) {
                            $error = 'Votre compte est en attente de validation.';
                            $password_valid = false;
                        } elseif ($user['est_bloque']) {
                            $error = 'Votre compte est bloqué. Raison : ' . $user['raison_blocage'];
                            $password_valid = false;
                        }
                    }
                }
            }
            
            // 3. Si mot de passe invalide
            if (!$password_valid) {
                $error = 'Email ou mot de passe incorrect';
                // Supprimer les cookies si le mot de passe tapé est faux
                if (!empty($password)) {
                    setcookie('remember_email', '', time() - 3600, '/');
                    setcookie('remember_password_hash', '', time() - 3600, '/');
                    $saved_email = '';
                }
            }
            // 4. Connexion réussie
            else {
                // Mettre à jour la date de dernière connexion
                if ($is_admin) {
                    $stmt = $pdo->prepare("UPDATE administrateur SET derniere_connexion = NOW() WHERE id_administrateur = ?");
                    $stmt->execute([$user['id_administrateur']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id_utilisateur = ?");
                    $stmt->execute([$user['id_utilisateur']]);
                }
                
                // Gestion des cookies "Se souvenir de moi"
                if ($remember) {
                    // Créer un cookie avec l'email
                    setcookie('remember_email', $email, time() + (24 * 3600), '/');
                    
                    // Créer un cookie avec le hash du hash du mot de passe
                    // On hash le hash stocké pour ne pas stocker le vrai mot de passe
                    if (!empty($stored_hash)) {
                        $cookie_hash = password_hash($stored_hash, PASSWORD_DEFAULT);
                        setcookie('remember_password_hash', $cookie_hash, time() + (24 * 3600), '/');
                    }
                } else {
                    // Supprimer les cookies
                    setcookie('remember_email', '', time() - 3600, '/');
                    setcookie('remember_password_hash', '', time() - 3600, '/');
                }
                
                // Créer la session
                $_SESSION['user_id'] = $is_admin ? $user['id_administrateur'] : $user['id_utilisateur'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_prenom'] = $user['prenom'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_photo'] = $user['photo'] ?? 'default-avatar.png';
                
                if ($is_admin) {
                    $_SESSION['user_role'] = 'administrateur';
                    $_SESSION['est_admin'] = true;
                    redirect('dashboard/admin/dashboard.php');
                } else {
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['est_admin'] = false;
                    
                    switch ($user['role']) {
                        case 'stagiaire':
                            redirect('dashboard/stagiaire/dashboard.php');
                            break;
                        case 'encadreur_pro':
                        case 'encadreur_acro':
                            redirect('dashboard/encadreur/dashboard.php');
                            break;
                        case 'secretaire':
                            redirect('dashboard/secretaire/dashboard.php');
                            break;
                        default:
                            redirect('dashboard/index.php');
                    }
                }
                exit;
            }
        } catch (Exception $e) {
            $error = 'Une erreur est survenue. Veuillez réessayer.';
            error_log($e->getMessage());
        }
    }
}

$page_title = "Connexion - School-Connection";
include 'includes/header-simple.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100 py-5">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="text-center mb-5">
                <div class="d-inline-block bg-white bg-opacity-10 rounded-circle p-4 mb-4">
                    <i class="fas fa-graduation-cap fa-4x text-white"></i>
                </div>
                <h1 class="display-5 fw-bold text-white mb-2">School-Connection</h1>
                <p class="text-white-50">Connectez-vous à votre espace</p>
            </div>
            
            <div class="card border-0 shadow-lg" style="border-radius: 30px;">
                <div class="card-body p-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control form-control-lg" 
                                   name="email" value="<?= htmlspecialchars($saved_email) ?>" 
                                   placeholder="exemple@email.com" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Mot de passe <span class="text-muted fw-normal">(optionnel si déjà mémorisé)</span>
                            </label>
                            <input type="password" class="form-control form-control-lg" 
                                   id="password" name="password" 
                                   placeholder="Entrez votre mot de passe">
                            <div class="form-text mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Si vous avez coché "Se souvenir de moi", vous pouvez laisser ce champ vide.
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                <label class="form-check-label" for="remember">
                                    Se souvenir de moi (1 jour)
                                </label>
                            </div>
                            <a href="forgot-password.php" class="text-decoration-none">Mot de passe oublié ?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-4 py-3" 
                                style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; border-radius: 15px;">
                            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                        </button>
                    </form>
                    
                    <div class="text-center">
                        <p class="mb-3">Pas encore de compte ?</p>
                        <div class="d-grid gap-2">
                            <a href="register.php?type=stagiaire" class="btn btn-outline-primary btn-lg py-2">
                                <i class="fas fa-user-graduate me-2"></i>S'inscrire comme stagiaire
                            </a>
                            <a href="register.php?type=encadreur" class="btn btn-outline-success btn-lg py-2">
                                <i class="fas fa-chalkboard-teacher me-2"></i>S'inscrire comme encadreur
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-link text-white">
                    <i class="fas fa-arrow-left me-2"></i>Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }
    .form-control:focus {
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
        border-color: #667eea;
    }
    .btn-outline-primary:hover, .btn-outline-success:hover {
        transform: translateY(-2px);
    }
    .alert {
        border-radius: 15px;
    }
</style>

<script>
// Afficher/masquer le mot de passe
const passwordInput = document.getElementById('password');
const toggleBtn = document.createElement('button');
toggleBtn.type = 'button';
toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
toggleBtn.className = 'btn btn-link position-absolute end-0 top-50 translate-middle-y text-secondary';
toggleBtn.style.right = '15px';
toggleBtn.style.zIndex = '10';
toggleBtn.style.background = 'transparent';
toggleBtn.style.border = 'none';

passwordInput.parentNode.style.position = 'relative';
passwordInput.parentNode.appendChild(toggleBtn);

toggleBtn.addEventListener('click', function() {
    const icon = this.querySelector('i');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});
</script>

<?php include 'includes/footer-simple.php'; ?>