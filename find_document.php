<?php
/**
 * Script pour trouver où sont stockés les fichiers
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!is_logged_in()) {
    die('Non connecté');
}

$document_id = (int)($_GET['id'] ?? 0);

if (!$document_id) {
    echo "<h3>Entrez un ID document: <a href='?id=111'>ID 111</a></h3>";
    exit;
}

// Récupérer le document
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id_document = ?");
$stmt->execute([$document_id]);
$doc = $stmt->fetch();

if (!$doc) {
    die("Document ID $document_id non trouvé");
}

echo "<h2>Recherche du fichier pour le document ID: $document_id</h2>";
echo "<p><strong>Nom fichier:</strong> " . $doc['nom_fichier'] . "</p>";
echo "<p><strong>Chemin stocké:</strong> " . $doc['chemin'] . "</p>";
echo "<p><strong>Nom de base:</strong> " . basename($doc['chemin']) . "</p>";
echo "<hr>";

$filename = basename($doc['chemin']);
$base = 'C:/xampp/htdocs/school-connection/';

$paths_to_check = [
    // Chemins possibles
    $base . 'assets/uploads/documents/' . $filename,
    $base . 'assets/uploads/documents/cv/' . $filename,
    $base . 'assets/uploads/documents/lettres/' . $filename,
    $base . 'assets/uploads/documents/rapports/' . $filename,
    $base . 'assets/uploads/' . $filename,
    $base . 'uploads/documents/' . $filename,
    $base . $doc['chemin'],
    $base . str_replace('assets/', '', $doc['chemin']),
];

echo "<h3>Recherche du fichier:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Chemin testé</th><th>Existe ?</th></tr>";

foreach ($paths_to_check as $path) {
    $exists = file_exists($path);
    $color = $exists ? 'green' : 'red';
    echo "<tr>";
    echo "<td>" . htmlspecialchars($path) . "</td>";
    echo "<td style='color: $color; font-weight: bold;'>" . ($exists ? '✅ OUI' : '❌ NON') . "</td>";
    echo "</tr>";
}

echo "</table>";

// Lister tous les fichiers dans le dossier uploads
echo "<h3>Contenu du dossier uploads:</h3>";
$upload_dir = $base . 'assets/uploads/';
if (is_dir($upload_dir)) {
    echo "<pre>";
    system('dir "' . $upload_dir . '" /s /b');
    echo "</pre>";
} else {
    echo "Le dossier n'existe pas: $upload_dir";
}
?>