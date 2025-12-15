<?php
session_start();
require_once '../db.php';  // use db.php with runQuery()
//include '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);

    if ($action === 'add') {
        $name  = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $desc  = trim($_POST['description']);

        // Handle photo upload
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $targetDir = "../uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $photo = time() . "_" . basename($_FILES['photo']['name']);
            $targetFile = $targetDir . $photo;
            move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile);
        }

        runQuery("INSERT INTO products (name, price, stock, description, photo) VALUES (?, ?, ?, ?, ?)", 
                 ["sdiss", [$name, $price, $stock, $desc, $photo]]);
        $message = "✅ Product '{$name}' added successfully!";
    }
    elseif ($action === 'update' && $product_id > 0) {
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);
        $desc  = trim($_POST['description']);

        if (!empty($_FILES['photo']['name'])) {
            $targetDir = "../uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $photo = time() . "_" . basename($_FILES['photo']['name']);
            $targetFile = $targetDir . $photo;
            move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile);

            runQuery("UPDATE products SET price=?, stock=?, description=?, photo=? WHERE id=?", 
                     ["disii", [$price, $stock, $desc, $photo, $product_id]]);
        } else {
            runQuery("UPDATE products SET price=?, stock=?, description=? WHERE id=?", 
                     ["disi", [$price, $stock, $desc, $product_id]]);
        }
        $message = "✏️ Product #{$product_id} updated successfully!";
    }
    elseif ($action === 'delete' && $product_id > 0) {
        runQuery("DELETE FROM products WHERE id=?", ["i", [$product_id]]);
        $message = "🗑️ Product #{$product_id} deleted successfully!";
    }
}

// Fetch products
$products = runQuery("SELECT id, name, price, stock, description, photo FROM products ORDER BY id ASC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiniGrind - Admin Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <!-- Boxicons CDN Link -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

 
</head>
<body>
    <div class="main-container">
        <?php include 'components/admin_header.php'; ?>

        <section class="dashboard">
            <div class="heading">
                <img src="assets/imgs/separator.png">
            </div>

            <div class="box-container">

            </div>
        </section>
    </div>

    <div class="container py-4">
    <h2 class="mb-4">Product Management Dashboard</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <!-- Add product form -->
    <div class="card mb-4">
        <div class="card-header">Add New Product</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="action" value="add">
                <div class="col-md-3">
                    <input type="text" name="name" class="form-control" placeholder="Product Name" required>
                </div>
                <div class="col-md-2">
                    <input type="number" step="0.01" name="price" class="form-control" placeholder="Price" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="stock" class="form-control" placeholder="Stock" required>
                </div>
                <div class="col-md-3">
                    <input type="file" name="photo" class="form-control" accept="image/*">
                </div>
                <div class="col-md-12">
                    <textarea name="description" class="form-control" placeholder="Product Description"></textarea>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-success">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Product list -->
    <h3>Existing Products</h3>
    <table class="table table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Description</th>
                <th>Price (₱)</th>
                <th>Stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td>
                        <?php if ($p['photo']): ?>
                            <img src="../uploads/<?= htmlspecialchars($p['photo']) ?>" alt="Product Photo" style="width:80px;height:auto;">
                        <?php else: ?>
                            <span class="text-muted">No photo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['description']) ?></td>
                    <td>₱<?= number_format($p['price'], 2) ?></td>
                    <td><?= intval($p['stock']) ?></td>
                    <td>
                        <!-- Update form -->
                        <form method="post" enctype="multipart/form-data" class="d-inline">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="number" step="0.01" name="price" value="<?= $p['price'] ?>" style="width:80px">
                            <input type="number" name="stock" value="<?= $p['stock'] ?>" style="width:60px">
                            <input type="file" name="photo" accept="image/*" style="width:150px">
                            <textarea name="description" style="width:200px"><?= htmlspecialchars($p['description']) ?></textarea>
                            <button type="submit" class="btn btn-primary btn-sm">Update</button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" class="text-center">No products found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>

    <?php
    include 'components/alert.php';
    ?>   

    <!-- custom js file link  -->
    <script src="assets/script.js"></script>
</body>
</html>