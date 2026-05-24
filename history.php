<?php
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set("Asia/Manila");

$conn = new mysqli("localhost", "root", "", "im");

if ($conn->connect_error) {
    die("<div style='color:white; background:#ef4444; padding:30px; text-align:center; font-family:sans-serif;'>🚨 MySQL is OFF! Paki-start ang Apache at MySQL sa XAMPP Control Panel.</div>");
}

$search = isset($_GET['search_member']) ? trim($_GET['search_member']) : "";
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : "";
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : "";
$category = isset($_GET['category']) ? $_GET['category'] : "all";

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "name LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "DATE(gym_date) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
} elseif (!empty($start_date)) {
    $where_clauses[] = "DATE(gym_date) = ?";
    $params[] = $start_date;
    $types .= "s";
}

if ($category == "earnings_today") {
    $where_clauses[] = "DATE(gym_date) = ?";
    $params[] = date('Y-m-d');
    $types .= "s";
} elseif ($category == "memberships") {
    $where_clauses[] = "(att_addons LIKE '%Membership%' OR att_addons LIKE '%Half-Month%')";
} elseif ($category == "walkins") {
    $where_clauses[] = "att_addons NOT LIKE '%Membership%' AND att_addons NOT LIKE '%Half-Month%' AND att_addons NOT LIKE '%(x%' AND att_addons != 'Check-in Only'";
} elseif ($category == "sales") {
    $where_clauses[] = "att_addons LIKE '%(x%'";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Gumagamit ng att_id para sa secure binding base sa database structure mo
$query_string = "SELECT * FROM attendance $where_sql ORDER BY att_id DESC";
$stmt = $conn->prepare($query_string);

if (!empty($params) && $stmt) {
    $stmt->bind_param($types, ...$params);
}

if($stmt) {
    $stmt->execute();
    $logs_result = $stmt->get_result();
}

$total_revenue = 0;
if (isset($logs_result) && $logs_result->num_rows > 0) {
    while ($rev_row = $logs_result->fetch_assoc()) {
        $total_revenue += (float)$rev_row['amount'];
    }
    $logs_result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales & Attendance History</title>
<style>
:root{
    --primary: #facc15;
    --primary-hover: #eab308;
    --bg: #090d16;
    --card: #111827;
    --input-bg: #1f2937;
    --border: #374151;
    --text-muted: #9ca3af;
}
*{ margin: 0; padding: 0; box-sizing: border-box; }
body{
    background: var(--bg); color: #f3f4f6;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px 15px;
}
.container{
    max-width: 1400px; margin: auto; background: var(--card);
    padding: 35px; border-radius: 20px; border: 1px solid var(--border);
    box-shadow: 0 20px 40px rgba(0,0,0,0.7);
}
.brand-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 35px; padding-bottom: 25px; border-bottom: 2px dashed var(--border);
    flex-wrap: wrap; gap: 20px;
}
.brand-text h1 { color: var(--primary); font-size: 28px; text-transform: uppercase; letter-spacing: 1px; font-weight: 900; }
.navigation-buttons { display: flex; gap: 10px; }
.nav-btn {
    background: transparent; color: white; border: 1px solid var(--border);
    padding: 10px 18px; border-radius: 8px; font-weight: bold; text-decoration: none;
    font-size: 13px; transition: 0.2s; text-transform: uppercase;
}
.nav-btn:hover { background: #1f2937; color: var(--primary); }
.nav-primary { border-color: var(--primary); color: var(--primary); }
.nav-primary:hover { background: var(--primary); color: black; }

.filter-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
input { padding: 12px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; color: white; font-size: 14px; }
.btn-filter { background: var(--primary); color: black; font-weight: bold; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; transition: 0.2s; }
.btn-filter:hover { background: var(--primary-hover); }

.tab-container { display: flex; gap: 6px; margin-bottom: 20px; flex-wrap: wrap; }
.tab-link { background: #1f2937; color: white; border: 1px solid var(--border); padding: 9px 14px; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: bold; transition: 0.2s; }
.tab-link.active { background: var(--primary); color: #000; border-color: var(--primary); }

.table-wrapper { overflow-x: auto; background: #0f172a; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 20px; }
table{ width: 100%; border-collapse: collapse; }
th{ background: #1f2937; color: var(--primary); padding: 14px; text-align: left; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid var(--border); }
td{ padding: 14px; border-bottom: 1px solid var(--border); font-size: 14px; color: #f3f4f6; }
tr:hover td { background: #1f293750; }

.revenue-box { background: rgba(6, 95, 70, 0.2); border: 1px solid #065f46; border-radius: 12px; padding: 18px; text-align: center; margin-top: 10px; }
.revenue-box h2 { color: #34d399; font-size: 22px; font-weight: 900; }
</style>
</head>
<body>

<div class="container">
    <div class="brand-header">
        <div class="brand-text">
            <h1>📊 SALES & ATTENDANCE HISTORY</h1>
        </div>
        <div class="navigation-buttons">
            <a href="IM.php" class="nav-btn nav-primary">← Dashboard</a>
            <a href="inventory.php" class="nav-btn">📦 Product Inventory</a>
            <a href="settings.php" class="nav-btn">⚙️ Settings</a>
        </div>
    </div>

    <div style="background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 25px;">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <input type="text" name="search_member" style="flex-grow: 1; min-width: 250px;" placeholder="Search by member name..." value="<?php echo htmlspecialchars($search); ?>">
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            <button type="submit" class="btn-filter">Filter Records</button>
            <a href="?category=all" style="color: var(--text-muted); font-size: 13px; margin-left: 8px; text-decoration: none;">Reset</a>
        </form>

        <div class="tab-container">
            <?php $keep_filters = "&search_member=" . urlencode($search) . "&start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date); ?>
            <a href="?category=all<?php echo $keep_filters; ?>" class="tab-link <?php echo $category == 'all' ? 'active' : ''; ?>">All Items</a>
            <a href="?category=earnings_today<?php echo $keep_filters; ?>" class="tab-link <?php echo $category == 'earnings_today' ? 'active' : ''; ?>">Today's Earnings</a>
            <a href="?category=memberships<?php echo $keep_filters; ?>" class="tab-link <?php echo $category == 'memberships' ? 'active' : ''; ?>">Memberships</a>
            <a href="?category=walkins<?php echo $keep_filters; ?>" class="tab-link <?php echo $category == 'walkins' ? 'active' : ''; ?>">Walk-ins</a>
            <a href="?category=sales<?php echo $keep_filters; ?>" class="tab-link <?php echo $category == 'sales' ? 'active' : ''; ?>">Product Sales</a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">Date & Time</th>
                        <th style="width: 30%;">Member Name</th>
                        <th style="width: 30%;">Item/Log Details</th>
                        <th style="width: 15%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($logs_result) && $logs_result->num_rows > 0): ?>
                        <?php while ($log = $logs_result->fetch_assoc()): ?>
                        <tr>
                            <td style="color: var(--text-muted);"><?php echo date("M d, Y h:i A", strtotime($log['gym_date'])); ?></td>
                            <td><b><?php echo htmlspecialchars($log['name']); ?></b></td>
                            <td><span style="color: var(--primary); font-weight: bold;"><?php echo htmlspecialchars($log['att_addons']); ?></span></td>
                            <td style="font-weight: bold; color: #34d399;">₱<?php echo number_format($log['amount'], 2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 40px; font-weight: bold;">NO RECORDS FOUND</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="revenue-box">
            <h2>Filtered Revenue Accumulation: ₱<?php echo number_format($total_revenue, 2); ?></h2>
        </div>
    </div>
</div>

</body>
</html>