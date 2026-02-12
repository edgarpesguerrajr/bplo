<?php

require __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . '/../config.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Get document ID from URL parameter
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Invalid document ID.');
}

// Fetch document data from database
$stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
if (!$stmt) {
    die('Database error.');
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$document) {
    die('Document not found.');
}

// Fetch driver data
$driverStmt = $conn->prepare("SELECT driver_first_name, driver_middle_name, driver_last_name, driver_ext_name FROM document_drivers WHERE document_id = ? ORDER BY id ASC LIMIT 1");
$driverData = null;
if ($driverStmt) {
    $driverStmt->bind_param('i', $id);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    if ($driverResult) {
        $driverData = $driverResult->fetch_assoc();
        $driverResult->free();
    }
    $driverStmt->close();
}

// Fallback to old driver fields if no driver data found
if (!$driverData) {
    $driverData = [
        'driver_first_name' => $document['driver_first_name'] ?? '',
        'driver_middle_name' => $document['driver_middle_name'] ?? '',
        'driver_last_name' => $document['driver_last_name'] ?? '',
        'driver_ext_name' => $document['driver_ext_name'] ?? ''
    ];
}

// Build full names
$operatorFullName = trim(
    ($document['franchisee_first_name'] ?? '') . ' ' . 
    ($document['franchisee_middle_name'] ?? '') . ' ' . 
    ($document['franchisee_last_name'] ?? '') . ' ' . 
    ($document['franchisee_ext_name'] ?? '')
);

$driverFullName = trim(
    ($driverData['driver_first_name'] ?? '') . ' ' . 
    ($driverData['driver_middle_name'] ?? '') . ' ' . 
    ($driverData['driver_last_name'] ?? '') . ' ' . 
    ($driverData['driver_ext_name'] ?? '')
);

// Build full address
$fullAddress = trim(($document['address'] ?? '') . ', ' . ($document['barangay'] ?? '') . ', Tanay, Rizal');

// Format dates
$registrationDate = $document['registration_date'] ? date('n/j/Y', strtotime($document['registration_date'])) : '';
$expirationDate = $document['expiration_date'] ? date('n/j/Y', strtotime($document['expiration_date'])) : '';
$orDate = $document['or_date'] ? date('n/j/Y', strtotime($document['or_date'])) : '';
$orNo = $document['or_no'] ?? '';
$ctcDate = $document['ctc_date'] ? date('n/j/Y', strtotime($document['ctc_date'])) : '';

// Generate QR code based on registration number
$registrationNo = $document['registration_no'] ?? '';
$qrData = $registrationNo !== '' ? $registrationNo : ('MTOP-' . $id);
$qrCode = new QrCode(
    data: $qrData,
    size: 150
);
$writer = new PngWriter();
$result = $writer->write($qrCode);
$qrImageData = base64_encode($result->getString());
$qrCodePath = 'data:image/png;base64,' . $qrImageData;

// Format amount
$totalAmount = $document['total_amount'] ? 'Php' . number_format($document['total_amount'], 2) : '';

// Determine application type
$applicationType = (strpos(strtolower($document['remarks'] ?? ''), 'renewal') !== false) ? 'RENEWAL' : 'NEW';
$applicationTypeNew = $applicationType === 'NEW' ? 'XXXX' : '';
$applicationTypeRenewal = $applicationType === 'RENEWAL' ? 'XXXX' : '';

// Build MTOP plate display
$mtopPlateDisplay = trim(($document['franchise_no'] ?? '') . ' ' . ($document['toda_no'] ?? ''));

/**
 * Set the Dompdf options
 */
$options = new Options;
$options->setChroot(dirname(__DIR__));
$options->setIsRemoteEnabled(true);

$dompdf = new Dompdf($options);

/**
 * Set the paper size and orientation
 */
$dompdf->setPaper("Legal", "portrait");

/**
 * Load the HTML and replace placeholders with values from the database
 */
$html = file_get_contents("form.html");

// Define all replacements
$placeholders = [
    '{{ application_type_new }}',
    '{{ application_type_renewal }}',
    '{{ registration_no }}',
    '{{ operator_name }}',
    '{{ address }}',
    '{{ make }}',
    '{{ year_model }}',
    '{{ motor_no }}',
    '{{ chassis_no }}',
    '{{ plate_no }}',
    '{{ mtop_plate }}',
    '{{ registration_date }}',
    '{{ expiration_date }}',
    '{{ total_amount }}',
    '{{ or_no_date }}',
    '{{ ctc_no }}',
    '{{ ctc_date }}',
    '{{ or_no }}',
    '{{ or_date }}',
    '{{ qr_code_path }}'
];

$values = [
    $applicationTypeNew,
    $applicationTypeRenewal,
    $document['registration_no'] ?? '',
    strtoupper($operatorFullName),
    $fullAddress,
    $document['make'] ?? '',
    $document['year_model'] ?? '',
    $document['motor_no'] ?? '',
    $document['motor_no'] ?? '', // Using motor_no as chassis_no placeholder
    $document['plate_no'] ?? '',
    $mtopPlateDisplay,
    $registrationDate,
    $expirationDate,
    $totalAmount,
    $orNo . ' /' . $orDate,
    $document['ctc_no'] ?? '',
    $ctcDate,
    $orNo,
    $orDate,
    $qrCodePath
];

$html = str_replace($placeholders, $values, $html);

$dompdf->loadHtml($html);

/**
 * Create the PDF and set attributes
 */
$dompdf->render();

$dompdf->addInfo("Title", "MTOP Application - " . $operatorFullName);

/**
 * Send the PDF to the browser
 */
$filename = "MTOP_" . ($document['registration_no'] ?? $id) . ".pdf";
$dompdf->stream($filename, ["Attachment" => 0]);