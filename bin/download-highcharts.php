<?php
/**
 * Télécharger automatiquement les modules Highcharts dans le package
 * Exécuté lors de: composer install, composer update
 */

$cdnBases = [
    'https://cdnjs.cloudflare.com/ajax/libs/highcharts/11.2.0',
];

$files = [
    'highcharts.js',
    'highcharts-more.js',
    'modules/heatmap.js',
    'modules/exporting.js',
    'modules/export-data.js',
    'modules/accessibility.js',
];

$destDir = __DIR__ . '/../resources/vendor/highcharts';

// Vérifier si déjà téléchargé
$alreadyDownloaded = true;
foreach ($files as $file) {
    if (!file_exists($destDir . '/' . $file)) {
        $alreadyDownloaded = false;
        break;
    }
}

if ($alreadyDownloaded) {
    echo "[Statisty] Highcharts déjà téléchargés, passage.\n";
    exit(0);
}

// Créer le dossier
@mkdir($destDir, 0755, true);

echo "[Statisty] Téléchargement automatique des fichiers Highcharts 11.2.0...\n";
echo "[Statisty] Destination: $destDir\n";

$downloaded = 0;
$failed = 0;

foreach ($files as $file) {
    $filePath = $destDir . '/' . $file;
    $fileDir = dirname($filePath);
    
    @mkdir($fileDir, 0755, true);
    
    $success = false;
    foreach ($cdnBases as $base) {
        $url = $base . '/' . $file;
        
        // Tenter avec curl d'abord (plus fiable)
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Statisty-Package/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $content !== false && strlen($content) > 0) {
                if (@file_put_contents($filePath, $content) !== false) {
                    echo "[Statisty] ✓ Téléchargé: $file\n";
                    $downloaded++;
                    $success = true;
                    break;
                }
            }
        } else {
            // Fallback avec file_get_contents si curl non disponible
            $content = @file_get_contents($url, false, stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Statisty-Package/1.0',
                    'ignore_errors' => true,
                ],
                'https' => [
                    'timeout' => 10,
                    'user_agent' => 'Statisty-Package/1.0',
                    'ignore_errors' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]));
            
            if ($content !== false && strlen($content) > 0) {
                if (@file_put_contents($filePath, $content) !== false) {
                    echo "[Statisty] ✓ Téléchargé: $file\n";
                    $downloaded++;
                    $success = true;
                    break;
                }
            }
        }
    }
    
    if (!$success) {
        echo "[Statisty] ✗ Impossible de télécharger: $file\n";
        $failed++;
    }
}

echo "[Statisty] Résultat: $downloaded fichiers téléchargés, $failed manquants\n";

if ($failed > 0) {
    echo "[Statisty] Note: Highcharts sera chargé depuis CDN (fallback automatique dans le navigateur).\n";
    exit(0); // Ne pas échouer, CDN est le default
}

exit(0);

