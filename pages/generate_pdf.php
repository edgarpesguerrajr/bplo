<?php
require_once '../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Invalid document ID.');
}

// Fetch document data
$document = null;
$stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$document) {
    die('Document not found.');
}

// Fetch driver data
$driverRows = [];
$stmt = $conn->prepare("SELECT driver_first_name, driver_middle_name, driver_last_name, driver_ext_name FROM document_drivers WHERE document_id = ? ORDER BY id ASC");
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $driverRows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
    $stmt->close();
}

// Determine document status
$expirationDate = $document['expiration_date'] ?? null;
$currentDate = date('Y-m-d');
$isExpired = $expirationDate && $expirationDate < $currentDate;
$statusText = $isExpired ? 'EXPIRED' : 'VALID';
$statusClass = $isExpired ? 'danger' : 'success';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Franchise Document - <?php echo htmlspecialchars($document['franchise_no']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            background: #f5f5f5;
        }
        
        .print-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .no-print {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-print:hover {
            background-color: #45a049;
        }
        
        .btn-back {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-back:hover {
            background-color: #0b7dda;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 12px;
            color: #666;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .status-badge.valid {
            background-color: #c3e6cb;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-badge.expired {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section-title {
            background-color: #f0f0f0;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .row.full {
            grid-template-columns: 1fr;
        }
        
        .row.quad {
            grid-template-columns: 1fr 1fr 1fr 1fr;
        }
        
        .label {
            font-weight: bold;
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .value {
            font-size: 13px;
            color: #333;
            padding: 5px;
            border-bottom: 1px solid #ddd;
            min-height: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .table th {
            background-color: #f0f0f0;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            border: 1px solid #ddd;
        }
        
        .table td {
            padding: 10px;
            border: 1px solid #ddd;
            font-size: 12px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .no-print {
                display: none;
            }
            
            .print-container {
                max-width: 100%;
                padding: 0;
                box-shadow: none;
                margin: 0;
            }
            
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="no-print">
            <button class="btn btn-print" onclick="window.print();">
                <span>Print to PDF</span>
            </button>
            <a href="template.php?page=documents" class="btn btn-back">Back to Documents</a>
        </div>
        
        <div class="header">
            <h1>Republic of the Philippines</h1>
            <p>Province of Rizal</p>
            <h1>Municipality of Tanay</h1>
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
        </div>

        <!-- Franchise Section -->
        <div class="section">
            <div class="section-title">Franchise Information</div>
            <div class="row">
                <div>
                    <div class="label">Franchise No.</div>
                    <div class="value"><?php echo htmlspecialchars($document['franchise_no'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Registration No.</div>
                    <div class="value"><?php echo htmlspecialchars($document['registration_no'] ?? ''); ?></div>
                </div>
            </div>
            <div class="row">
                <div>
                    <div class="label">Registration Date</div>
                    <div class="value"><?php echo $document['registration_date'] ? date('F d, Y', strtotime($document['registration_date'])) : ''; ?></div>
                </div>
                <div>
                    <div class="label">Expiration Date</div>
                    <div class="value"><?php echo $document['expiration_date'] ? date('F d, Y', strtotime($document['expiration_date'])) : ''; ?></div>
                </div>
            </div>
        </div>

        <!-- Franchisee Section -->
        <div class="section">
            <div class="section-title">Franchisee Information</div>
            <div class="row quad">
                <div>
                    <div class="label">First Name</div>
                    <div class="value"><?php echo htmlspecialchars($document['franchisee_first_name'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Middle Name</div>
                    <div class="value"><?php echo htmlspecialchars($document['franchisee_middle_name'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Last Name</div>
                    <div class="value"><?php echo htmlspecialchars($document['franchisee_last_name'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Extension</div>
                    <div class="value"><?php echo htmlspecialchars($document['franchisee_ext_name'] ?? ''); ?></div>
                </div>
            </div>
            <div class="row full">
                <div>
                    <div class="label">Address</div>
                    <div class="value"><?php echo htmlspecialchars($document['address'] ?? ''); ?></div>
                </div>
            </div>
            <div class="row">
                <div>
                    <div class="label">Barangay</div>
                    <div class="value"><?php echo htmlspecialchars($document['barangay'] ?? ''); ?></div>
                </div>
            </div>
        </div>

        <!-- Vehicle Section -->
        <div class="section">
            <div class="section-title">Vehicle Information</div>
            <div class="row quad">
                <div>
                    <div class="label">Make</div>
                    <div class="value"><?php echo htmlspecialchars($document['make'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Year Model</div>
                    <div class="value"><?php echo htmlspecialchars($document['year_model'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Motor No.</div>
                    <div class="value"><?php echo htmlspecialchars($document['motor_no'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Plate No.</div>
                    <div class="value"><?php echo htmlspecialchars($document['plate_no'] ?? ''); ?></div>
                </div>
            </div>
        </div>

        <!-- Drivers Section -->
        <?php if (!empty($driverRows)): ?>
        <div class="section">
            <div class="section-title">Authorized Drivers</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Extension</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($driverRows as $driver): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($driver['driver_first_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($driver['driver_middle_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($driver['driver_last_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($driver['driver_ext_name'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Fees Section -->
        <div class="section">
            <div class="section-title">Fees</div>
            <div class="row">
                <div>
                    <div class="label">Franchisee Fee</div>
                    <div class="value">₱<?php echo number_format($document['franchisee_fee'] ?? 0, 2); ?></div>
                </div>
                <div>
                    <div class="label">Sticker Fee</div>
                    <div class="value">₱<?php echo number_format($document['sticker_fee'] ?? 0, 2); ?></div>
                </div>
            </div>
            <div class="row">
                <div>
                    <div class="label">Filing Fee</div>
                    <div class="value">₱<?php echo number_format($document['filing_fee'] ?? 0, 2); ?></div>
                </div>
                <div>
                    <div class="label">Penalty Fee</div>
                    <div class="value">₱<?php echo number_format($document['penalty_fee'] ?? 0, 2); ?></div>
                </div>
            </div>
            <div class="row">
                <div>
                    <div class="label">Transfer Fee</div>
                    <div class="value">₱<?php echo number_format($document['transfer_fee'] ?? 0, 2); ?></div>
                </div>
                <div>
                    <div class="label">Plate Fee</div>
                    <div class="value">₱<?php echo number_format($document['plate_fee'] ?? 0, 2); ?></div>
                </div>
            </div>
            <div class="row">
                <div>
                    <div class="label"><strong>Total Amount</strong></div>
                    <div class="value"><strong>₱<?php echo number_format($document['total_amount'] ?? 0, 2); ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="section">
            <div class="section-title">Additional Information</div>
            <div class="row">
                <div>
                    <div class="label">OR No.</div>
                    <div class="value"><?php echo htmlspecialchars($document['or_no'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">OR Date</div>
                    <div class="value"><?php echo $document['or_date'] ? date('F d, Y', strtotime($document['or_date'])) : ''; ?></div>
                </div>
            </div>
            <div class="row">
                <div>
                    <div class="label">CTC No.</div>
                    <div class="value"><?php echo htmlspecialchars($document['ctc_no'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">CTC Date</div>
                    <div class="value"><?php echo $document['ctc_date'] ? date('F d, Y', strtotime($document['ctc_date'])) : ''; ?></div>
                </div>
            </div>
            <div class="row">
                <div>
                    <div class="label">Sticker No.</div>
                    <div class="value"><?php echo htmlspecialchars($document['sticker_no'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">TODA No.</div>
                    <div class="value"><?php echo htmlspecialchars($document['toda_no'] ?? ''); ?></div>
                </div>
            </div>
            <div class="row full">
                <div>
                    <div class="label">Remarks</div>
                    <div class="value" style="min-height: 40px;"><?php echo htmlspecialchars($document['remarks'] ?? ''); ?></div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Generated on <?php echo date('F d, Y \a\t g:i A'); ?></p>
            <p>Barangay Licensing and Permits Office</p>
        </div>
    </div>
</body>
</html>