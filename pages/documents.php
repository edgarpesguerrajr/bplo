<?php
require_once 'config.php';

$deleteDocId = isset($_GET['delete']) ? (int)$_GET['delete'] : 0;
if ($deleteDocId > 0) {
    $deleteDocStmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    if ($deleteDocStmt) {
        $deleteDocStmt->bind_param('i', $deleteDocId);
        $deleteDocStmt->execute();
        $deleteDocStmt->close();
    }

    $redirectUrl = 'template.php?page=documents&success=deleted';
    if (!headers_sent()) {
        header("Location: {$redirectUrl}");
    } else {
        echo "<script>window.location.replace('{$redirectUrl}');</script>";
    }
    exit;
}

$successFlag = $_GET['success'] ?? '';
$showSuccess = in_array($successFlag, ['added', 'updated', 'deleted'], true);
$successMessage = $successFlag === 'updated'
    ? 'Document updated successfully.'
    : ($successFlag === 'deleted'
        ? 'Document deleted successfully.'
        : 'Document was added successfully.');

$perPage = 20;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$searchTerm = trim($_GET['q'] ?? '');
$hasSearch = $searchTerm !== '';
$likeTerm = '%' . $searchTerm . '%';
$barangayFilter = trim($_GET['barangay'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$offset = ($page - 1) * $perPage;

// Sorting parameters
$validSortColumns = ['franchise_no', 'franchisee_first_name', 'barangay', 'expiration_date'];
$sortBy = isset($_GET['sortBy']) && in_array($_GET['sortBy'], $validSortColumns) ? $_GET['sortBy'] : 'franchise_no';
$sortDir = isset($_GET['sortDir']) && $_GET['sortDir'] === 'ASC' ? 'ASC' : 'DESC';

$totalDocuments = 0;
$whereClauses = [];
$params = [];
$types = '';
if ($hasSearch) {
    $whereClauses[] = "(franchise_no LIKE ? OR franchisee_first_name LIKE ? OR franchisee_last_name LIKE ? OR plate_no LIKE ? OR motor_no LIKE ?)";
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $types .= 'sssss';
}
if ($barangayFilter !== '') {
    $whereClauses[] = 'barangay = ?';
    $params[] = $barangayFilter;
    $types .= 's';
}
if ($statusFilter === 'expired') {
    $whereClauses[] = 'expiration_date < CURDATE()';
} elseif ($statusFilter === 'valid') {
    $whereClauses[] = 'expiration_date >= CURDATE()';
}
$whereSql = $whereClauses ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

$countSql = "SELECT COUNT(*) AS total FROM documents {$whereSql}";
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        $totalDocuments = (int)($row['total'] ?? 0);
        $countResult->free();
    }
    $countStmt->close();
}
$totalPages = max(1, (int)ceil($totalDocuments / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$documents = [];
$perPage = (int)$perPage;
$offset = (int)$offset;
$listSql = "SELECT id, franchise_no, franchisee_first_name, franchisee_last_name, barangay, expiration_date
            FROM documents
            {$whereSql}
            ORDER BY {$sortBy} {$sortDir}
            LIMIT ? OFFSET ?";
$stmt = $conn->prepare($listSql);
if ($stmt) {
    $listParams = $params;
    $listTypes = $types . 'ii';
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $stmt->bind_param($listTypes, ...$listParams);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $documents = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0,-50..200" />
    <title>Documents</title>
    <style>
        .success-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #e8f7ed;
            color: #1b5e20;
            border: 1px solid #c8e6c9;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            font-size: 14px;
        }

        
        .action-btn {
        width: 36px;
        height: 36px;
        border: 1px solid var(--color-border-hr);
        background: var(--color-bg-secondary);
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--color-text-primary);
        transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.1s ease;
        margin-right: 6px;
        }

        .action-btn:last-child {
        margin-right: 0;
        }

        .action-btn:hover {
        background: var(--color-hover-secondary);
        border-color: var(--color-hover-primary);
        }

        .action-btn:active {
        transform: scale(0.98);
        }

        .action-btn .material-symbols-outlined {
        font-size: 20px;
        line-height: 1;
        }
        
        .badge.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .badge.secondary {
            background: #e9ecef;
            color: #495057;
            border: 1px solid #ced4da;
        }

        .ellipsis {
            display: inline-block;
            padding: 8px 6px;
            color: var(--color-text-primary);
            font-weight: 500;
        }

        .pagination-controls .pagination-btn {
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .pagination-controls .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-controls .pagination-btn.active {
            font-weight: 600;
            background-color: var(--color-hover-primary);
            color: white;
            border-color: var(--color-hover-primary);
            pointer-events: none;
        }

        .documents-table thead th {
            padding: 0;
        }

        .sort-header {
            display: block;
            text-decoration: none;
            color: inherit;
            padding: 12px;
            cursor: pointer;
            user-select: none;
            transition: background-color 0.2s ease;
        }

        .sort-header:hover {
            background-color: var(--color-hover-secondary);
        }

        .sort-indicator {
            display: inline-block;
            margin-left: 6px;
            font-size: 12px;
            color: var(--color-text-secondary);
            min-width: 16px;
        }

    </style>
</head>
<body>
    <?php if ($showSuccess): ?>
        <div class="success-popup" id="successPopup"><?php echo $successMessage; ?></div>
        <script>
            setTimeout(() => {
                const popup = document.getElementById('successPopup');
                if (popup) popup.remove();
            }, 3000);
        </script>
    <?php endif; ?>
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Documents</h1>
            <button id="addDocumentButton" class="add-btn" type="button">+ Add Document</button>
        </div>
        <form class="search-filter" action="template.php" method="GET">
            <input type="hidden" name="page" value="documents">
            <select name="status" class="search-bar" style="max-width: 150px;">
                <option value="">All Status</option>
                <option value="valid" <?php echo $statusFilter === 'valid' ? 'selected' : ''; ?>>VALID</option>
                <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>EXPIRED</option>
            </select>
            <select name="barangay" class="search-bar" style="max-width: 180px;">
                <option value="">All Barangays</option>
                <option value="CAYABU" <?php echo $barangayFilter === 'CAYABU' ? 'selected' : ''; ?>>CAYABU</option>
                <option value="CAYUMBAY" <?php echo $barangayFilter === 'CAYUMBAY' ? 'selected' : ''; ?>>CAYUMBAY</option>
                <option value="DARAITAN" <?php echo $barangayFilter === 'DARAITAN' ? 'selected' : ''; ?>>DARAITAN</option>
                <option value="KATIPUNAN BAYANI" <?php echo $barangayFilter === 'KATIPUNAN BAYANI' ? 'selected' : ''; ?>>KATIPUNAN BAYANI</option>
                <option value="KAYBUTO" <?php echo $barangayFilter === 'KAYBUTO' ? 'selected' : ''; ?>>KAYBUTO</option>
                <option value="LAIBAN" <?php echo $barangayFilter === 'LAIBAN' ? 'selected' : ''; ?>>LAIBAN</option>
                <option value="MADILAYDILAY" <?php echo $barangayFilter === 'MADILAYDILAY' ? 'selected' : ''; ?>>MADILAYDILAY</option>
                <option value="MAG AMPON" <?php echo $barangayFilter === 'MAG AMPON' ? 'selected' : ''; ?>>MAG AMPON</option>
                <option value="MAMUYAO" <?php echo $barangayFilter === 'MAMUYAO' ? 'selected' : ''; ?>>MAMUYAO</option>
                <option value="PINAGKAMALIGAN" <?php echo $barangayFilter === 'PINAGKAMALIGAN' ? 'selected' : ''; ?>>PINAGKAMALIGAN</option>
                <option value="PLAZA ALDEA" <?php echo $barangayFilter === 'PLAZA ALDEA' ? 'selected' : ''; ?>>PLAZA ALDEA</option>
                <option value="SAMPALOC" <?php echo $barangayFilter === 'SAMPALOC' ? 'selected' : ''; ?>>SAMPALOC</option>
                <option value="SAN ANDRES" <?php echo $barangayFilter === 'SAN ANDRES' ? 'selected' : ''; ?>>SAN ANDRES</option>
                <option value="SAN ISIDRO" <?php echo $barangayFilter === 'SAN ISIDRO' ? 'selected' : ''; ?>>SAN ISIDRO</option>
                <option value="SANTA INEZ" <?php echo $barangayFilter === 'SANTA INEZ' ? 'selected' : ''; ?>>SANTA INEZ</option>
                <option value="STO NIÑO" <?php echo $barangayFilter === 'STO NIÑO' ? 'selected' : ''; ?>>STO NIÑO</option>
                <option value="TABING ILOG" <?php echo $barangayFilter === 'TABING ILOG' ? 'selected' : ''; ?>>TABING ILOG</option>
                <option value="TANDANG KUTYO" <?php echo $barangayFilter === 'TANDANG KUTYO' ? 'selected' : ''; ?>>TANDANG KUTYO</option>
                <option value="TINUCAN" <?php echo $barangayFilter === 'TINUCAN' ? 'selected' : ''; ?>>TINUCAN</option>
                <option value="WAWA" <?php echo $barangayFilter === 'WAWA' ? 'selected' : ''; ?>>WAWA</option>
            </select>
            <input type="text" name="q" placeholder="Search franchises..." class="search-bar" value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button class="filter-btn" type="submit">
                <span class="material-symbols-rounded">search</span>
                <span>Search</span>
            </button>
        </form>

        <table class="documents-table">
            <thead>
                <tr>
                    <th style="width: 15%;">
                        <a class="sort-header" href="?page=documents&sortBy=franchise_no&sortDir=<?php echo ($sortBy === 'franchise_no' && $sortDir === 'DESC') ? 'ASC' : 'DESC'; ?><?php echo $hasSearch ? '&q=' . urlencode($searchTerm) : ''; ?><?php echo $barangayFilter ? '&barangay=' . urlencode($barangayFilter) : ''; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?>">
                            FRANCHISE NO. <span class="sort-indicator"><?php echo ($sortBy === 'franchise_no') ? ($sortDir === 'DESC' ? '▼' : '▲') : ''; ?></span>
                        </a>
                    </th>
                    <th style="width: 30%;">
                        <a class="sort-header" href="?page=documents&sortBy=franchisee_first_name&sortDir=<?php echo ($sortBy === 'franchisee_first_name' && $sortDir === 'DESC') ? 'ASC' : 'DESC'; ?><?php echo $hasSearch ? '&q=' . urlencode($searchTerm) : ''; ?><?php echo $barangayFilter ? '&barangay=' . urlencode($barangayFilter) : ''; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?>">
                            FRANCHISEE NAME <span class="sort-indicator"><?php echo ($sortBy === 'franchisee_first_name') ? ($sortDir === 'DESC' ? '▼' : '▲') : ''; ?></span>
                        </a>
                    </th>
                    <th style="width: 20%;">
                        <a class="sort-header" href="?page=documents&sortBy=barangay&sortDir=<?php echo ($sortBy === 'barangay' && $sortDir === 'DESC') ? 'ASC' : 'DESC'; ?><?php echo $hasSearch ? '&q=' . urlencode($searchTerm) : ''; ?><?php echo $barangayFilter ? '&barangay=' . urlencode($barangayFilter) : ''; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?>">
                            BARANGAY <span class="sort-indicator"><?php echo ($sortBy === 'barangay') ? ($sortDir === 'DESC' ? '▼' : '▲') : ''; ?></span>
                        </a>
                    </th>
                    <th style="width: 15%;">
                        <a class="sort-header" href="?page=documents&sortBy=expiration_date&sortDir=<?php echo ($sortBy === 'expiration_date' && $sortDir === 'DESC') ? 'ASC' : 'DESC'; ?><?php echo $hasSearch ? '&q=' . urlencode($searchTerm) : ''; ?><?php echo $barangayFilter ? '&barangay=' . urlencode($barangayFilter) : ''; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?>">
                            STATUS <span class="sort-indicator"><?php echo ($sortBy === 'expiration_date') ? ($sortDir === 'DESC' ? '▼' : '▲') : ''; ?></span>
                        </a>
                    </th>
                    <th style="width: 20%;">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($documents) === 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">No data available</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <?php
                            $expirationDate = $doc['expiration_date'] ?? null;
                            $currentDate = date('Y-m-d');
                            $isExpired = $expirationDate && $expirationDate < $currentDate;
                            $statusText = $isExpired ? 'EXPIRED' : 'VALID';
                            $statusClass = $isExpired ? 'danger' : 'success';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['franchise_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(trim(($doc['franchisee_first_name'] ?? '') . ' ' . ($doc['franchisee_last_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars($doc['barangay'] ?? ''); ?></td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td>
                                <a class="action-btn" title="View" href="template.php?page=view_document&id=<?php echo (int)$doc['id']; ?>">
                                    <span class="material-symbols-outlined">visibility</span>
                                </a>
                                <a class="action-btn" title="Edit" href="template.php?page=edit_document&id=<?php echo (int)$doc['id']; ?>">
                                    <span class="material-symbols-outlined">edit_document</span>
                                </a>
                                <a class="action-btn" title="Delete" href="template.php?page=documents&delete=<?php echo (int)$doc['id']; ?>" onclick="return confirm('Delete this franchise record?');">
                                    <span class="material-symbols-outlined">delete</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php
                $startItem = $totalDocuments === 0 ? 0 : $offset + 1;
                $endItem = min($offset + $perPage, $totalDocuments);
                $filterQueryParams = [];
                if ($hasSearch) {
                    $filterQueryParams['q'] = $searchTerm;
                }
                if ($barangayFilter !== '') {
                    $filterQueryParams['barangay'] = $barangayFilter;
                }
                if ($statusFilter !== '') {
                    $filterQueryParams['status'] = $statusFilter;
                }
                // Add sort parameters
                $filterQueryParams['sortBy'] = $sortBy;
                $filterQueryParams['sortDir'] = $sortDir;
                $filterQuery = http_build_query($filterQueryParams);
                $filterSuffix = $filterQuery !== '' ? '&' . $filterQuery : '';
            ?>
            <span>Showing <?php echo $startItem; ?>-<?php echo $endItem; ?> of <?php echo $totalDocuments; ?></span>
            <div id="pagination-controls" class="pagination-controls">
                <!-- Pagination will be rendered by JavaScript -->
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = <?php echo (int)$page; ?>;
            const totalPages = <?php echo (int)$totalPages; ?>;
            const filterSuffix = '<?php echo addslashes($filterSuffix); ?>';

            function renderPagination() {
                let paginationHtml = '';

                // Previous button
                const prevDisabled = currentPage === 1 ? 'disabled' : '';
                const prevHref = currentPage > 1 ? `href="template.php?page=documents&p=${currentPage - 1}${filterSuffix}"` : '';
                const prevTag = currentPage > 1 ? 'a' : 'button';
                paginationHtml += `<${prevTag} class="pagination-btn prev" ${prevHref} ${prevDisabled}>Previous</${prevTag}>`;

                // First two pages
                paginationHtml += `<a class="pagination-btn ${currentPage === 1 ? 'active' : ''}" href="template.php?page=documents&p=1${filterSuffix}">1</a>`;
                if (totalPages > 1) {
                    paginationHtml += `<a class="pagination-btn ${currentPage === 2 ? 'active' : ''}" href="template.php?page=documents&p=2${filterSuffix}">2</a>`;
                }

                // Ellipsis if needed
                if (currentPage > 3 && totalPages > 5) {
                    paginationHtml += `<span class="ellipsis">...</span>`;
                }

                // Current page (if not in first two or last two)
                if (currentPage > 2 && currentPage < totalPages - 1) {
                    paginationHtml += `<a class="pagination-btn active" href="template.php?page=documents&p=${currentPage}${filterSuffix}">${currentPage}</a>`;
                }

                // Ellipsis before last two
                if (currentPage < totalPages - 2 && totalPages > 5) {
                    paginationHtml += `<span class="ellipsis">...</span>`;
                }

                // Last two pages
                if (totalPages > 1 && totalPages > 2) {
                    paginationHtml += `<a class="pagination-btn ${currentPage === totalPages - 1 ? 'active' : ''}" href="template.php?page=documents&p=${totalPages - 1}${filterSuffix}">${totalPages - 1}</a>`;
                }
                if (totalPages > 1) {
                    paginationHtml += `<a class="pagination-btn ${currentPage === totalPages ? 'active' : ''}" href="template.php?page=documents&p=${totalPages}${filterSuffix}">${totalPages}</a>`;
                }

                // Next button
                const nextDisabled = currentPage === totalPages ? 'disabled' : '';
                const nextHref = currentPage < totalPages ? `href="template.php?page=documents&p=${currentPage + 1}${filterSuffix}"` : '';
                const nextTag = currentPage < totalPages ? 'a' : 'button';
                paginationHtml += `<${nextTag} class="pagination-btn next" ${nextHref} ${nextDisabled}>Next</${nextTag}>`;

                document.getElementById('pagination-controls').innerHTML = paginationHtml;
            }

            // Initial render
            renderPagination();
        });
        </script>
    </div>
    <script>
        // Add Document Button
        const addDocButton = document.getElementById('addDocumentButton');
        if (addDocButton) {
            addDocButton.addEventListener('click', () => {
                window.location.href = 'template.php?page=add_document';
            });
        }
    </script>
</body>
</html>