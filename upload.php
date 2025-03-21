<?php
// Hata raporlamayı etkinleştir
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CORS başlıkları ekle
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// JSON yanıtı döndürmek için başlık
header('Content-Type: application/json');

// Yükleme klasörü
$uploadDir = 'uploads/';

// Yükleme klasörü yoksa oluştur
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// POST isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [];
    
    // Dosya yüklendi mi kontrol et
    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
        $files = $_FILES['files'];
        $fileCount = count($files['name']);
        
        // Başarılı ve başarısız dosya sayısı
        $successCount = 0;
        $errorCount = 0;
        
        // Her dosya için işlem yap
        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $files['name'][$i];
            $fileTmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileError = $files['error'][$i];
            
            // Dosya yükleme hatası kontrolü
            if ($fileError === 0) {
                // Benzersiz dosya adı oluştur
                $newFileName = uniqid() . '_' . $fileName;
                $destination = $uploadDir . $newFileName;
                
                // Dosyayı taşı
                if (move_uploaded_file($fileTmpName, $destination)) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $response['errors'][] = [
                        'file' => $fileName,
                        'message' => 'Dosya yüklenirken bir hata oluştu'
                    ];
                }
            } else {
                $errorCount++;
                $response['errors'][] = [
                    'file' => $fileName,
                    'message' => 'Dosya yükleme hatası: ' . $fileError
                ];
            }
        }
        
        // Sonucu döndür
        $response['status'] = ($errorCount === 0) ? 'success' : 'partial';
        $response['message'] = "$fileCount dosyadan $successCount tanesi başarıyla yüklendi.";
        $response['success_count'] = $successCount;
        $response['error_count'] = $errorCount;
        
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Hiçbir dosya yüklenmedi'
        ];
    }
    
    // JSON yanıtını döndür
    echo json_encode($response);
} else {
    // POST değilse hata mesajı döndür
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu'
    ]);
}
?>
