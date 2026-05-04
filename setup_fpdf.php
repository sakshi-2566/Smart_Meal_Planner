<?php
// setup_fpdf.php
$url = 'https://raw.githubusercontent.com/Setasign/FPDF/master/fpdf.php';
$dest = __DIR__ . '/api/libs/fpdf.php';

if (!is_dir(__DIR__ . '/api/libs')) {
    mkdir(__DIR__ . '/api/libs', 0777, true);
}

// Try using file_get_contents
$content = @file_get_contents($url);

if ($content === false) {
    // Try cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $content = curl_exec($ch);
    curl_close($ch);
}

if ($content) {
    if (file_put_contents($dest, $content)) {
        echo "FPDF downloaded successfully to $dest";
    } else {
        echo "Failed to write to $dest";
    }
} else {
    echo "Failed to download FPDF from $url";
}
?>
