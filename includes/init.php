<?php
session_start();
require_once 'config.php';
require_once '../vendor/autoload.php'; 
use Picqer\Barcode\BarcodeGeneratorPNG;
// Session and Auth Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}


function getCurrentUserName() {
    return isLoggedIn() ? $_SESSION['user_name'] : 'Guest';
}


// Notification Functions
function setNotification($type, $message) {
    $_SESSION['notification'] = ['type' => $type, 'message' => $message];
}

function getNotification() {
    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        unset($_SESSION['notification']);
        return $notification;
    }
    return null;
}

function generateProductBarcode($product_id) {
    // Generate a unique barcode identifier
    $barcode = 'PROD' . str_pad($product_id, 8, '0', STR_PAD_LEFT);

    // Paths
    $barcode_dir = '../assets/barcode/';
    $barcode_file = $barcode_dir . $barcode . '.png';

    if (!is_dir($barcode_dir)) {
        mkdir($barcode_dir, 0777, true);
    }

    // Generate the barcode image (without text)
    $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $barcode_data = $generator->getBarcode($barcode, $generator::TYPE_CODE_128, 3, 100);

    // Create image from barcode binary
    $barcode_image = imagecreatefromstring($barcode_data);
    $barcode_width = imagesx($barcode_image);
    $barcode_height = imagesy($barcode_image);

    // Create new image with extra space below for text
    $text_height = 20;
    $total_height = $barcode_height + $text_height;

    $final_image = imagecreatetruecolor($barcode_width, $total_height);

    // Colors
    $white = imagecolorallocate($final_image, 255, 255, 255);
    $black = imagecolorallocate($final_image, 0, 0, 0);
    imagefill($final_image, 0, 0, $white);

    // Copy barcode onto final image
    imagecopy($final_image, $barcode_image, 0, 0, 0, 0, $barcode_width, $barcode_height);

    // Add the product ID text
    $font = __DIR__ . '/arial.ttf'; // Or use a built-in GD font if no TTF available
    if (file_exists($font)) {
        imagettftext($final_image, 12, 0, 10, $total_height - 5, $black, $font, $barcode);
    } else {
        // fallback to built-in font
        imagestring($final_image, 4, 10, $barcode_height + 2, $barcode, $black);
    }

    // Save final image
    imagepng($final_image, $barcode_file);
    imagedestroy($barcode_image);
    imagedestroy($final_image);

    return [
        'barcode' => $barcode,
        'barcode_path' => 'assets/barcode/' . $barcode . '.png'
    ];
}
