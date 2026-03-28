<?php
/**
 * Validation d'un document par l'encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_any_role(['encadreur_pro', 'encadreur_acro']);

$encadreur_id = $_SESSION['user_id'];

// Vérifier le token CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Token de sécurité invalide'];
    redirect('documents.php');
}

$document_id = (int)($_POST['id_document'] ?? 0);
$commentaire = cleanInput($_POST['commentaire'] ?? '');

if (!$document_id) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID document manquant'];
    redirect('documents.php');
}

try {
    // Récupérer les informations du document et du stagiaire
    $stmt = $pdo->prepare("
        SELECT d.*, 
               u.id_utilisateur as stagiaire_id, 
               u.nom as stagiaire_nom, 
               u.prenom as stagiaire_prenom,
               u.email as stagiaire_email
        FROM documents d
        JOIN utilisateurs u ON d.id_utilisateur = u.id_utilisateur
        JOIN stagiaires s ON d.id_utilisateur = s.id_stagiaire
        WHERE d.id_document = ? AND s.id_encadreur = ?
    ");
    $stmt->execute([$document_id, $encadreur_id]);
    $document = $stmt->fetch();

    if (!$document) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Document non trouvé ou accès non autorisé'];
        redirect('documents.php');
    }

    if ($document['est_valide']) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Ce document a déjà été validé'];
        redirect('documents.php');
    }

    // Mettre à jour le statut du document
    $stmt = $pdo->prepare("
        UPDATE documents 
        SET est_valide = 1, 
            valide_par = ?,
            date_validation = NOW(),
            commentaire_validation = ?
        WHERE id_document = ?
    ");
    $stmt->execute([$encadreur_id, $commentaire, $document_id]);

    // Envoyer une notification au stagiaire
    $notification_message = "Votre document \"" . $document['nom_fichier'] . "\" a été validé par votre encadreur.";
    if (!empty($commentaire)) {
        $notification_message .= " Commentaire: " . $commentaire;
    }

    // Vérifier si la table notifications existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (id_utilisateur, titre, message, type_notification, lien, est_lue, date_creation)
            VALUES (?, 'Document validé', ?, 'document', 'dashboard/stagiaire/documents.php', 0, NOW())
        ");
        $stmt->execute([$document['stagiaire_id'], $notification_message]);
    }

    // Journaliser l'action
    log_action(
        $encadreur_id,
        'validation_document',
        "Document validé: " . $document['nom_fichier'] . " pour le stagiaire " . $document['stagiaire_prenom'] . " " . $document['stagiaire_nom'],
        'validation',
        'documents',
        $document_id
    );

    $_SESSION['flash'] = [
        'type' => 'success', 
        'message' => 'Document validé avec succès ! Le stagiaire a été notifié.'
    ];

} catch (PDOException $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Erreur: ' . $e->getMessage()];
}

redirect('documents.php');
?>