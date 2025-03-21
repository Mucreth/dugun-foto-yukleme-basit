<?php
// CORS ayarları
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition");

// OPTIONS isteği için yanıt
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Hata raporlama
ini_set('display_errors', 1);
error_reporting(E_ALL);

// JSON yanıtı döndürmek için
header('Content-Type: application/json');

// Yükleme klasörü
$uploadDir = 'uploads/temp/';
$finalDir = 'uploads/';

// Klasörleri oluştur
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!file_exists($finalDir)) {
    mkdir($finalDir, 0755, true);
}

// POST isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Chunk bilgilerini al
    $contentRange = isset($_SERVER['HTTP_CONTENT_RANGE']) ? $_SERVER['HTTP_CONTENT_RANGE'] : '';
    $fileName = isset($_POST['fileName']) ? $_POST['fileName'] : '';
    $fileId = isset($_POST['fileId']) ? $_POST['fileId'] : uniqid();
    
    if (empty($contentRange) || empty($fileName)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gerekli bilgiler eksik'
        ]);
        exit;
    }
    
    // Content-Range başlığını ayrıştır (bytes 0-1048575/2097152 formatı)
    preg_match('/bytes\s+(\d+)-(\d+)\/(\d+)/', $contentRange, $matches);
    
    if (count($matches) !== 4) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Geçersiz Content-Range formatı'
        ]);
        exit;
    }
    
    $rangeStart = intval($matches[1]);
    $rangeEnd = intval($matches[2]);
    $fileSize = intval($matches[3]);
    
    // Gelen veriyi al
    $data = file_get_contents('php://input');
    
    if ($data === false) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Veri alınamadı'
        ]);
        exit;
    }
    
    // Güvenli bir dosya adı oluştur
    $safeFileId = preg_replace('/[^a-zA-Z0-9_]/', '', $fileId);
    $tempFilePath = $uploadDir . $safeFileId . '.part';
    
    // Dosyayı oluştur veya aç
    $file = fopen($tempFilePath, 'c');
    
    if ($file === false) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Dosya oluşturulamadı'
        ]);
        exit;
    }
    
    // Dosyayı kilitle
    if (!flock($file, LOCK_EX)) {
        fclose($file);
        echo json_encode([
            'status' => 'error',
            'message' => 'Dosya kilitlenemedi'
        ]);
        exit;
    }
    
    // İmleci doğru konuma getir
    fseek($file, $rangeStart);
    
    // Veriyi yaz
    if (fwrite($file, $data) === false) {
        flock($file, LOCK_UN);
        fclose($file);
        echo json_encode([
            'status' => 'error',
            'message' => 'Veri yazılamadı'
        ]);
        exit;
    }
    
    // Dosyayı serbest bırak ve kapat
    flock($file, LOCK_UN);
    fclose($file);
    
    // Son chunk ise dosyayı tamamla
    if ($rangeEnd + 1 === $fileSize) {
        // Benzersiz dosya adı oluştur
        $finalFileName = uniqid() . '_' . $fileName;
        $finalFilePath = $finalDir . $finalFileName;
        
        // Geçici dosyayı taşı
        if (!rename($tempFilePath, $finalFilePath)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Dosya taşınamadı'
            ]);
            exit;
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Dosya yükleme tamamlandı',
            'fileName' => $finalFileName,
            'fileSize' => $fileSize
        ]);
    } else {
        // Devam ediyor
        echo json_encode([
            'status' => 'progress',
            'message' => 'Chunk alındı',
            'received' => $rangeEnd + 1,
            'size' => $fileSize,
            'percent' => round(($rangeEnd + 1) / $fileSize * 100, 2)
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu'
    ]);
}
?>
