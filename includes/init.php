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

//Fucntion to creaete Barcode
function generateProductBarcode($product_id) {
    // Generate a unique barcode identifier (e.g., a random string)
    $barcode = 'PROD' . str_pad($product_id, 8, '0', STR_PAD_LEFT); // e.g., PROD00000001

    // Define the path to save the barcode image
    $barcode_dir = '../assets/barcode/';
    $barcode_file = $barcode_dir . $barcode . '.png';

    // Create the directory if it doesn't exist
    if (!is_dir($barcode_dir)) {
        mkdir($barcode_dir, 0777, true); // Create directory with full permissions, recursively
    }

    // Generate the barcode (using Code 128 format)
    $generator = new BarcodeGeneratorPNG();
    $barcode_data = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);

    // Save the barcode image to the file
    file_put_contents($barcode_file, $barcode_data);

    return [
        'barcode' => $barcode,
        'barcode_path' => 'assets/barcode/' . $barcode . '.png'
    ];
}