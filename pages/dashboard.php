<?php
require_once 'config.php';

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest';

$totalDocuments = 0;
$barangays = [
    'CAYABU', 'CUYAMBAY', 'DARAITAN', 'KATIPUNAN-BAYANI', 'KAYBUTO', 'LAIBAN',
    'MADILAYDILAY', 'MAG-AMPON', 'MAMUYAO', 'PINAGKAMALIGAN', 'PLAZA ALDEA',
    'SAMPALOC', 'SAN ANDRES', 'SAN ISIDRO', 'SANTA INEZ', 'SANTO NIÑO',
    'TABING ILOG', 'TANDANG KUTYO', 'TINUCAN', 'WAWA'
];
$barangayCounts = array_fill_keys($barangays, 0);

$totalResult = $conn->query("SELECT COUNT(*) AS total FROM documents");
if ($totalResult) {
    $row = $totalResult->fetch_assoc();
    $totalDocuments = (int)($row['total'] ?? 0);
    $totalResult->free();
}

$barangayResult = $conn->query("SELECT barangay, COUNT(*) AS total FROM documents GROUP BY barangay");
if ($barangayResult) {
    while ($row = $barangayResult->fetch_assoc()) {
        $barangayKey = $row['barangay'] ?? '';
        if ($barangayKey !== '' && array_key_exists($barangayKey, $barangayCounts)) {
            $barangayCounts[$barangayKey] = (int)($row['total'] ?? 0);
        }
    }
    $barangayResult->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
    .dashboard-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px;
    }

    .welcome-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    margin-bottom: 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.12), rgba(14, 165, 233, 0.12));
    border: 1px solid var(--color-border-hr);
    }

    .welcome-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--color-text-primary);
    margin: 0;
    }

    .welcome-subtitle {
    margin: 6px 0 0;
    color: var(--color-text-secondary);
    font-size: 0.95rem;
    }

    .dashboard-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-height: 120px;
    gap: 8px;
    border-radius: 14px;
    border: 1px solid var(--color-border-hr);
    background: var(--color-bg-secondary);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.12);
    }

    .dashboard-card.full-row {
    grid-column: 1 / -1;
    }

    .dashboard-card .stat-label {
    font-size: 0.95rem;
    color: var(--color-text-placeholder);
    font-weight: 600;
    letter-spacing: 0.02em;
    }

    .dashboard-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--color-text-primary);
    }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Dashboard</h1>
        </div>
        <div class="welcome-card">
            <div>
                <h2 class="welcome-title">Hi <?php echo htmlspecialchars($user_name); ?>.</h2>
                <p class="welcome-subtitle">Welcome back to the BPLO Monitoring System.</p>
            </div>
        </div>
        <div class="dashboard-grid">
            <div class="card dashboard-card full-row">
                <div class="stat-label">Total Franchise</div>
                <div class="stat-value"><?php echo $totalDocuments; ?></div>
            </div>
            <?php foreach ($barangayCounts as $barangay => $count): ?>
                <div class="card dashboard-card">
                    <div class="stat-label"><?php echo htmlspecialchars($barangay); ?></div>
                    <div class="stat-value"><?php echo $count; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>