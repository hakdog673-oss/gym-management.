<?php
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set("Asia/Manila");

$conn = new mysqli("localhost", "root", "", "im");

if ($conn->connect_error) {
    die("
    <div style='color:white; background:#ef4444; padding:30px; text-align:center; font-family:sans-serif; border-radius:12px; max-width:500px; margin:80px auto; box-shadow:0 10px 25px rgba(0,0,0,0.5);'>
        <h2>🚨 MySQL is OFF!</h2>
        <p>Paki-bukas muna ang iyong XAMPP Control Panel at i-start ang Apache at MySQL.</p>
    </div>
    ");
}

$current_file = basename($_SERVER['PHP_SELF']);

// PROCESS 1: ADD NEW PRODUCT
if (isset($_POST['save_product'])) {
    $p_name = strtoupper(trim($_POST['product_name']));
    $p_price = (float)$_POST['price'];
    $p_stock = (int)$_POST['stock'];

    if (!empty($p_name) && $p_price >= 0 && $p_stock >= 0) {
        $check_stmt = $conn->prepare("SELECT id FROM products WHERE product_name = ?");
        $check_stmt->bind_param("s", $p_name);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($check_res->num_rows > 0) {
            echo "<script>alert('❌ Ang produktong yan ay mayroon na sa iyong listahan!');</script>";
        } else {
            $ins_stmt = $conn->prepare("INSERT INTO products (product_name, price, stock) VALUES (?, ?, ?)");
            $ins_stmt->bind_param("sdi", $p_name, $p_price, $p_stock);
            $ins_stmt->execute();
            header("Location: $current_file?success=1");
            exit();
        }
    }
}

// PROCESS 2: QUICK STOCK UPDATE
if (isset($_POST['update_stock'])) {
    $pid = (int)$_POST['product_id'];
    $new_stock = (int)$_POST['quick_stock'];

    if ($pid > 0 && $new_stock >= 0) {
        $up_stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $up_stmt->bind_param("ii", $new_stock, $pid);
        $up_stmt->execute();
        header("Location: $current_file?updated=1");
        exit();
    }
}

// PROCESS 3: DELETE PRODUCT
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    if ($del_id > 0) {
        $del_stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $del_stmt->bind_param("i", $del_id);
        $del_stmt->execute();
        header("Location: $current_file?deleted=1");
        exit();
    }
}

// FETCH ALL PRODUCTS
$products_res = $conn->query("SELECT * FROM products ORDER BY product_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product Inventory Management</title>
<style>
:root {
    --primary: #facc15;
    --primary-hover: #eab308;
    --bg: #090d16;
    --card: #111827;
    --input-bg: #1f2937;
    --border: #374151;
    --text-muted: #9ca3af;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    background: var(--bg); color: #f3f4f6;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px 15px;
}
.container {
    max-width: 1300px; margin: auto; background: var(--card);
    padding: 35px; border-radius: 20px; border: 1px solid var(--border);
    box-shadow: 0 20px 40px rgba(0,0,0,0.7);
}
.brand-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 35px; padding-bottom: 25px; border-bottom: 2px dashed var(--border);
}
.brand-text h1 { color: var(--primary); font-size: 28px; text-transform: uppercase; font-weight: 900; }
.navigation-buttons { display: flex; gap: 10px; }
.nav-btn {
    background: transparent; color: white; border: 1px solid var(--border);
    padding: 10px 18px; border-radius: 8px; font-weight: bold; text-decoration: none;
    font-size: 13px; transition: 0.2s; text-transform: uppercase;
}
.nav-btn:hover { background: #1f2937; color: var(--primary); }
.nav-primary { border-color: var(--primary); color: var(--primary); }
.nav-primary:hover { background: var(--primary); color: black; }

.grid-layout { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
@media (max-width: 900px) { .grid-layout { grid-template-columns: 1fr; } }

.sub-card { background: #0f172a; border: 1px solid var(--border); border-radius: 16px; padding: 25px; height: max-content; }
.card-title { font-size: 18px; font-weight: 800; color: white; margin-bottom: 20px; border-left: 4px solid var(--primary); padding-left: 10px; }

.form-group { margin-bottom: 15px; }
.form-group label { display: block; font-size: 12px; color: var(--text-muted); font-weight: bold; margin-bottom: 6px; text-transform: uppercase; }
input { width: 100%; padding: 12px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; color: white; font-size: 14px; }
.btn-save { width: 100%; background: var(--primary); color: black; font-weight: bold; border: none; padding: 14px; border-radius: 8px; cursor: pointer; text-transform: uppercase; font-size: 14px; transition: 0.2s; margin-top: 10px; }
.btn-save:hover { background: var(--primary-hover); }

.table-wrapper { overflow-x: auto; background: #0f172a; border-radius: 12px; border: 1px solid var(--border); }
table { width: 100%; border-collapse: collapse; }
th { background: #1f2937; color: var(--primary); padding: 14px; text-align: left; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid var(--border); }
td { padding: 14px; border-bottom: 1px solid var(--border); font-size: 14px; color: #f3f4f6; }
.quick-stock-form { display: flex; gap: 6px; align-items: center; max-width: 150px; }
.input-qty { padding: 6px; text-align: center; font-weight: bold; width: 70px; }
.btn-set { background: #374151; color: var(--primary); border: 1px solid var(--border); padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; }
.btn-set:hover { background: var(--primary); color: black; }
.btn-delete { color: #f87171; text-decoration: none; font-size: 13px; font-weight: bold; }
.btn-delete:hover { text-decoration: underline; color: #ef4444; }
</style>
</head>
<body>

<div class="container">
    <div class="brand-header">
        <div class="brand-text">
            <h1>📦 PRODUCT INVENTORY</h1>
        </div>
        <div class="navigation-buttons">
            <a href="IM.php" class="nav-btn nav-primary">← Dashboard</a>
            <a href="history.php" class="nav-btn">📊 Sales History</a>
            <a href="settings.php" class="nav-btn">⚙️ Settings</a>
        </div>
    </div>

    <div class="grid-layout">
        <div class="sub-card">
            <div class="card-title">ADD NEW PRODUCT</div>
            <form method="POST">
                <div class="form-group">
                    <label>Product Name / Brand</label>
                    <input type="text" name="product_name" required placeholder="e.g., STING, WATER, WHEY">
                </div>
                <div class="form-group">
                    <label>Selling Price (₱)</label>
                    <input type="number" step="0.01" name="price" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Initial Stock Qty</label>
                    <input type="number" name="stock" required placeholder="0">
                </div>
                <button type="submit" name="save_product" class="btn-save">💾 Save Product</button>
            </form>
        </div>

        <div class="sub-card">
            <div class="card-title">CURRENT INVENTORY ITEMS</div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Current Stock</th>
                            <th>Quick Update</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products_res && $products_res->num_rows > 0): ?>
                            <?php while ($row = $products_res->fetch_assoc()): ?>
                            <tr>
                                <td><b><?php echo htmlspecialchars($row['product_name']); ?></b></td>
                                <td style="color:#34d399; font-weight:bold;">₱<?php echo number_format($row['price'], 2); ?></td>
                                <td>
                                    <?php if ($row['stock'] <= 5): ?>
                                        <span style="background: rgba(220,38,38,0.2); color:#f87171; padding:4px 8px; border-radius:6px; font-weight:bold; font-size:12px;">Low Stock: <?php echo $row['stock']; ?></span>
                                    <?php else: ?>
                                        <span style="color:#9ca3af; font-weight:bold;"><?php echo $row['stock']; ?> pcs</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="quick-stock-form">
                                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                                        <input type="number" name="quick_stock" class="input-qty" value="<?php echo $row['stock']; ?>" min="0">
                                        <button type="submit" name="update_stock" class="btn-set">SET</button>
                                    </form>
                                </td>
                                <td>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Sigurado ka bang buburahin ang produktong <?php echo addslashes($row['product_name']); ?> sa inventory?')">Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; color:var(--text-muted); padding:40px;">
                                    Walang produkto sa system. Magdagdag sa kaliwa!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>