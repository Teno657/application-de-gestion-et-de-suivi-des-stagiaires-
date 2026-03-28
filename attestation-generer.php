<?php
/**
 * Générer une attestation de stage (Secrétaire) - Version PDF
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Générer une attestation - School-Connection";
$error = '';
$success = '';

$stagiaire_id = (int)($_GET['stagiaire'] ?? 0);

// Si un stagiaire est spécifié, pré-remplir le formulaire
$stagiaire = null;
if ($stagiaire_id > 0) {
    $stmt = $pdo->prepare("
        SELECT u.*, s.*, e.id_encadreur, eu.nom as encadreur_nom, eu.prenom as encadreur_prenom,
               e.profession, e.entreprise, e.poste
        FROM utilisateurs u
        JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire
        LEFT JOIN relations_encadrement r ON s.id_stagiaire = r.id_stagiaire AND r.statut = 'active'
        LEFT JOIN encadreurs e ON r.id_encadreur = e.id_encadreur
        LEFT JOIN utilisateurs eu ON e.id_encadreur = eu.id_utilisateur
        WHERE u.id_utilisateur = ?
    ");
    $stmt->execute([$stagiaire_id]);
    $stagiaire = $stmt->fetch();
    
    if (!$stagiaire) {
        $error = "Stagiaire non trouvé";
    }
}

// Liste des stagiaires
$stagiaires = $pdo->query("
    SELECT u.id_utilisateur, u.nom, u.prenom, s.filiere, s.date_debut, s.date_fin,
           (SELECT COUNT(*) FROM attestations WHERE id_stagiaire = s.id_stagiaire) as a_deja_attestation
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    ORDER BY s.date_fin DESC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $id_stagiaire = (int)($_POST['id_stagiaire'] ?? 0);
        $date_emission = $_POST['date_emission'] ?? date('Y-m-d');
        
        if (!$id_stagiaire) {
            $error = "Veuillez sélectionner un stagiaire";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Récupérer les informations du stagiaire
                $stmt = $pdo->prepare("
                    SELECT u.*, s.*, 
                           eu.nom as encadreur_nom, eu.prenom as encadreur_prenom,
                           e.profession, e.entreprise, e.poste
                    FROM utilisateurs u
                    JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire
                    LEFT JOIN relations_encadrement r ON s.id_stagiaire = r.id_stagiaire AND r.statut = 'active'
                    LEFT JOIN encadreurs e ON r.id_encadreur = e.id_encadreur
                    LEFT JOIN utilisateurs eu ON e.id_encadreur = eu.id_utilisateur
                    WHERE u.id_utilisateur = ?
                ");
                $stmt->execute([$id_stagiaire]);
                $stagiaire = $stmt->fetch();
                
                if (!$stagiaire) {
                    throw new Exception("Stagiaire non trouvé");
                }
                
                // Déterminer le genre
                $prenom = $stagiaire['prenom'];
                $filles = ['Marie', 'Anne', 'Julie', 'Sophie', 'Camille', 'Claire', 'Laura', 'Emma', 'Alice', 'Chloé', 'Léa', 'Sarah', 'Manon', 'Pauline', 'Lucie', 'Jeanne', 'Margaux', 'Elise', 'Inès', 'Nina'];
                $est_femme = in_array($prenom, $filles) || (substr($prenom, -1) == 'e' && !in_array($prenom, ['Alexandre', 'Charles', 'Jules', 'Georges']));
                
                $genre_etudiant = $est_femme ? 'étudiante' : 'étudiant';
                $genre_ne = $est_femme ? 'Née' : 'Né';
                $genre_le = $est_femme ? 'la' : 'le';
                
                // Calculer la durée du stage
                $d1 = new DateTime($stagiaire['date_debut']);
                $d2 = new DateTime($stagiaire['date_fin']);
                $diff = $d1->diff($d2);
                $jours = $diff->days;
                $mois = floor($jours / 30);
                $jours_restants = $jours % 30;
                
                if ($mois > 0) {
                    $duree = $mois . ' mois' . ($mois > 1 ? 's' : '') . ($jours_restants > 0 ? ' et ' . $jours_restants . ' jour' . ($jours_restants > 1 ? 's' : '') : '');
                } else {
                    $duree = $jours . ' jour' . ($jours > 1 ? 's' : '');
                }
                
                // Générer un numéro unique
                $annee = date('Y');
                $mois_courant = date('m');
                $base_numero = 'ATT-' . $annee . $mois_courant . '-' . str_pad($id_stagiaire, 4, '0', STR_PAD_LEFT);
                
                $numero = $base_numero;
                $suffix = 1;
                while (true) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attestations WHERE numero_attestation = ?");
                    $stmt->execute([$numero]);
                    if ($stmt->fetchColumn() == 0) break;
                    $numero = $base_numero . '-' . $suffix;
                    $suffix++;
                }
                
                // Créer le dossier
                $attestation_dir = ROOT_PATH . 'assets/uploads/attestations/';
                if (!is_dir($attestation_dir)) {
                    mkdir($attestation_dir, 0755, true);
                }
                
                // Générer le contenu HTML
                $contenu_html = genererAttestationHTML($stagiaire, $date_emission, $numero, $duree, $genre_etudiant, $genre_ne, $genre_le);
                
                $filename = 'attestation_' . $numero . '_' . $stagiaire['nom'] . '_' . $stagiaire['prenom'] . '.pdf';
                $filepath = $attestation_dir . $filename;
                
                // Utiliser le bon chemin DOMPDF
                $dompdf_path = ROOT_PATH . 'vendor/dompdf/autoload.inc.php';
                
                if (file_exists($dompdf_path)) {
                    require_once $dompdf_path;
                    
                    $dompdf = new Dompdf\Dompdf();
                    $options = new Dompdf\Options();
                    $options->set('defaultFont', 'Helvetica');
                    $options->set('isRemoteEnabled', true);
                    $dompdf->setOptions($options);
                    $dompdf->loadHtml($contenu_html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    file_put_contents($filepath, $dompdf->output());
                } else {
                    // Fallback en HTML
                    $filename = str_replace('.pdf', '.html', $filename);
                    $filepath = $attestation_dir . $filename;
                    file_put_contents($filepath, $contenu_html);
                }
                
                // Enregistrer dans la base
                $stmt = $pdo->prepare("
                    INSERT INTO attestations (id_stagiaire, numero_attestation, date_emission, chemin_fichier, contenu_text, signe_par)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id_stagiaire,
                    $numero,
                    $date_emission,
                    'assets/uploads/attestations/' . $filename,
                    $contenu_html,
                    $_SESSION['user_id']
                ]);
                
                $attestation_id = $pdo->lastInsertId();
                
                // Mettre à jour le statut du stagiaire
                if ($stagiaire['statut_inscription'] !== 'termine') {
                    $pdo->prepare("UPDATE stagiaires SET statut_inscription = 'termine' WHERE id_stagiaire = ?")
                        ->execute([$id_stagiaire]);
                }
                
                create_notification(
                    $id_stagiaire,
                    'Attestation disponible',
                    'Votre attestation de stage est maintenant disponible en téléchargement.',
                    'success',
                    '/dashboard/stagiaire/attestation.php'
                );
                
                $pdo->commit();
                
                $_SESSION['flash']['success'] = "Attestation générée avec succès (N° $numero)";
                redirect("attestation-voir.php?id=$attestation_id");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
}

include '../../includes/header.php';
?>

<style>
    .form-control[readonly] { background-color: #e9ecef; cursor: not-allowed; }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Générer une attestation</h1>
                    <p class="text-muted">Créez une attestation de fin de stage</p>
                </div>
                <a href="attestations.php" class="btn btn-outline-secondary">
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
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-4">
                            <label for="id_stagiaire" class="form-label">Stagiaire</label>
                            <select class="form-select" id="id_stagiaire" name="id_stagiaire" required>
                                <option value="">-- Sélectionnez un stagiaire --</option>
                                <?php foreach ($stagiaires as $s): ?>
                                    <option value="<?= $s['id_utilisateur'] ?>" <?= $stagiaire_id == $s['id_utilisateur'] ? 'selected' : '' ?>>
                                        <?= e($s['prenom'] . ' ' . $s['nom']) ?> - <?= e($s['filiere']) ?> 
                                        (fin: <?= format_date($s['date_fin']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="date_emission" class="form-label">Date d'émission</label>
                            <input type="date" class="form-control" id="date_emission" name="date_emission" 
                                   value="<?= date('Y-m-d') ?>" readonly disabled>
                            <small class="text-muted text-success">
                                <i class="fas fa-calendar-check me-1"></i> Date automatique (aujourd'hui)
                            </small>
                            <input type="hidden" name="date_emission" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-between">
                            <a href="attestations.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-pdf"></i> Générer l'attestation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
function genererAttestationHTML($stagiaire, $date_emission, $numero, $duree, $genre_etudiant, $genre_ne, $genre_le) {
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Attestation de fin de stage - School-Connection</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Times New Roman", Times, serif;
            background: white;
            padding: 40px;
        }
        
        .attestation {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border: 1px solid #e0e0e0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header {
            background: #0a2b3e;
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .logo span {
            color: #ffd700;
        }
        
        .title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.3);
            display: inline-block;
        }
        
        .body {
            padding: 30px 40px;
        }
        
        .numero {
            text-align: right;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 20px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 8px;
        }
        
        .content {
            line-height: 1.6;
            font-size: 0.95rem;
        }
        
        .stagiaire-name {
            font-size: 1.4rem;
            font-weight: bold;
            text-align: center;
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #ffd700;
        }
        
        .period {
            text-align: center;
            background: #f0f7ff;
            padding: 12px;
            margin: 15px 0;
            border-radius: 8px;
        }
        
        .theme {
            background: #fff8e7;
            padding: 12px;
            margin: 15px 0;
            border-left: 4px solid #ffd700;
        }
        
        .signature {
            margin-top: 30px;
            text-align: right;
        }
        
        .signature-line {
            width: 250px;
            border-top: 1px solid #000;
            margin-top: 20px;
            margin-bottom: 5px;
            margin-left: auto;
        }
        
        .footer {
            background: #f5f5f5;
            padding: 12px;
            text-align: center;
            font-size: 0.65rem;
            color: #666;
            border-top: 1px solid #e0e0e0;
            margin-top: 20px;
        }
        
        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="attestation">
        <div class="header">
            <div class="logo">School-<span>Connection</span></div>
            <div class="title">ATTESTATION DE FIN DE STAGE</div>
        </div>
        
        <div class="body">
            <div class="numero">
                N° <strong>' . htmlspecialchars($numero) . '</strong>
            </div>
            
            <div class="content">
                <p>Nous soussignés, <strong>School-Connection</strong>, certifions que :</p>
                
                <div class="stagiaire-name">
                    ' . htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']) . '
                </div>
                
                <div class="info-grid">
                    <div><strong>' . $genre_ne . '(e) le :</strong> ' . format_date($stagiaire['date_naissance']) . ' à Yaoundé</div>
                    <div><strong>' . ucfirst($genre_etudiant) . '(e) en :</strong> ' . htmlspecialchars($stagiaire['filiere']) . '</div>
                    <div><strong>Niveau :</strong> ' . htmlspecialchars($stagiaire['niveau_etude']) . '</div>
                    <div><strong>Établissement :</strong> ' . htmlspecialchars($stagiaire['etablissement'] ?? 'Non renseigné') . '</div>
                </div>
                
                <div class="period">
                    <strong>Période de stage</strong><br>
                    du ' . format_date($stagiaire['date_debut']) . ' au ' . format_date($stagiaire['date_fin']) . '<br>
                    <em>(Durée totale : ' . $duree . ')</em>
                </div>
                
                <div class="theme">
                    <strong>📚 Thème du stage :</strong><br>
                    ' . htmlspecialchars($stagiaire['theme_stage']) . '
                </div>
                
                <p>Pendant cette période, ' . $genre_le . ' ' . $genre_etudiant . ' a fait preuve de sérieux, de rigueur et d\'implication dans les tâches confiées. Sa motivation et sa capacité d\'adaptation ont été appréciées par l\'équipe encadrante.</p>
                
                <div class="signature">
                    <div class="signature-line"></div>
                    <div>
                        <strong>' . ($stagiaire['encadreur_prenom'] ?? '________________') . ' ' . ($stagiaire['encadreur_nom'] ?? '________________') . '</strong><br>
                        <span style="font-size: 0.8rem;">' . ($stagiaire['profession'] ?? 'Responsable de stage') . '</span>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    Fait à Yaoundé, le ' . format_date($date_emission) . '
                </div>
            </div>
        </div>
        
        <div class="footer">
            School-Connection - La connexion entre l\'école et l\'entreprise<br>
            www.school-connection.com | contact@school-connection.com | (+237) 6 50 13 31 45
        </div>
    </div>
</body>
</html>';
    
    return $html;
}
?>

<?php include '../../includes/footer.php'; ?>