<?php
session_start();
require_once '../db.php';  // use db.php with runQuery()

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";

// Dashboard stats
$totalProducts = runQuery("SELECT COUNT(*) AS cnt FROM products")[0]['cnt'];

$totalCompletedOrders = runQuery("SELECT COUNT(*) AS cnt FROM orders WHERE status='completed'")[0]['cnt'];

$totalPendingOrders = runQuery("SELECT COUNT(*) AS cnt FROM orders WHERE status='pending'")[0]['cnt'];

$products = runQuery("SELECT id, name, stock FROM products ORDER BY id ASC");

// Total products sold (sum of quantities in completed orders)
$totalProductsSold = runQuery("
    SELECT COALESCE(SUM(oi.qty),0) AS cnt
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status='completed'
")[0]['cnt'];

// Total stock available (sum of stock column in products)
$totalStock = runQuery("SELECT COALESCE(SUM(stock),0) AS cnt FROM products")[0]['cnt'];


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
                     ["dissi", [$price, $stock, $desc, $photo, $product_id]]);
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

// Handle status updates inline
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $status   = trim($_POST['status'] ?? '');

    if ($order_id > 0 && !empty($status)) {
        runQuery("UPDATE orders SET status=? WHERE id=?", ["si", [$status, $order_id]]);
        $message = "✅ Order #{$order_id} status updated to '{$status}'!";
    }
}

// Fetch all orders
$orders = runQuery("SELECT o.id, u.username, o.total_amount, o.status, o.created_at 
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    ORDER BY o.created_at DESC");

// Fetch order items for each order
function getOrderItems($order_id) {
    return runQuery("SELECT oi.product_id, p.name, oi.qty, oi.price, oi.subtotal
                     FROM order_items oi
                     JOIN products p ON oi.product_id = p.id
                     WHERE oi.order_id = ?", ["i", [$order_id]]);
}

//Querying for
// Yearly summary
$yearly = runQuery("SELECT YEAR(created_at) AS year,
                           SUM(total_amount) AS total_sales,
                           COUNT(*) AS order_count
                    FROM orders
                    WHERE status='completed'
                    GROUP BY year
                    ORDER BY year DESC");

// Monthly summary
$monthly = runQuery("SELECT DATE_FORMAT(created_at, '%Y-%m') AS period,
                            SUM(total_amount) AS total_sales,
                            COUNT(*) AS order_count
                     FROM orders
                     WHERE status='completed'
                     GROUP BY period
                     ORDER BY period DESC");

// Weekly summary
$weekly = runQuery("SELECT YEARWEEK(created_at, 1) AS period,
                           SUM(total_amount) AS total_sales,
                           COUNT(*) AS order_count
                    FROM orders
                    WHERE status='completed'
                    GROUP BY period
                    ORDER BY period DESC");

// Individual product sales summary
$productSales = runQuery("SELECT p.name AS product_name,
           SUM(o_items.qty * o_items.price) AS total_sales
    FROM order_items o_items
    JOIN products p ON o_items.product_id = p.id
    JOIN orders o ON o_items.order_id = o.id
    WHERE o.status='completed'
    GROUP BY p.id, p.name
    ORDER BY total_sales DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manage Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons CDN Link -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: #fff;
            position: fixed;
            top: 60px;
            bottom: 0;
            left: 0;
            padding-top: 60px;
        }
        .sidebar a {
            display: block;
            color: #fff;
            padding: 12px 20px;
            text-decoration: none;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            flex: 1;
            padding-top: 80px;
        }
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: #212529;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar bg-dark text-white">
  <h4 class="text-center py-3"><b>ADMIN PANEL</b></h4>
  <ul class="nav flex-column ps-4"> 
    <li class="nav-item">
      <a class="nav-link text-white" href="#dashboard" onclick="showView('dashboard', this)">
        <i class="bx bxs-home-smile active"></i>  Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="#customer" onclick="showView('customer', this)">
        <i class="bx bxs-user-detail"></i>  Manage Customer
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="#products" onclick="showView('products', this)">
        <i class="bx bxs-shopping-bags"></i>  Manage Products
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="#orders" onclick="showView('orders', this)">
        <i class="bx bxs-food-menu"></i>  Manage Orders
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link text-white" href="#sales-summary" onclick="showView('sales-summary', this)">
        <i class="bx bx-bar-chart"></i>  Sales Summary
      </a>
    </li>
  </ul>
</div>

<!-- Topbar -->
<div class="topbar">
    <div>
        <!-- Left side of topbar (optional logo or title) -->
        <a href="#" class="nav-logo d-flex align-items-center text-decoration-none">
                    <img src="../assets/logo_iconpeanutbutter.png" alt="SiniGrind Logo" class="logo-image me-2" style="width: 60px;">
                    <h2 class="logo-text mb-0" style="color: #fff"><b>SiniGrind</b></h2>
                </a>
    </div>
    <div>
        <!-- Right side: Logged in text + Logout -->
        <span class="me-3">Logged in as the Manager</span>
        <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
    </div>
</div>

<!-- Content -->
<div class="content">

  <!--Dashboard-->
  <div id="dashboard">
    <h2>Dashboard</h2>

    <!--Dashboard Overview-->
  <h2>Dashboard Overview</h2>
  <div class="row">
    <div class="col-md-3">
      <div class="card text-center shadow-sm mb-4">
        <div class="card-body">
          <h5 class="card-title">Total Products</h5>
          <p class="display-6"><?= $totalProducts ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center shadow-sm mb-4">
        <div class="card-body">
          <h5 class="card-title">Completed Orders</h5>
          <p class="display-6"><?= $totalCompletedOrders ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center shadow-sm mb-4">
        <div class="card-body">
          <h5 class="card-title">Pending Orders</h5>
          <p class="display-6"><?= $totalPendingOrders ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center shadow-sm mb-4">
        <div class="card-body">
          <h5 class="card-title">Products Sold</h5>
          <p class="display-6"><?= $totalProductsSold ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center shadow-sm mb-4">
        <div class="card-body">
          <h5 class="card-title">Total Stock</h5>
          <p class="display-6"><?= $totalStock ?></p>
        </div>
      </div>
    </div>
  </div>
  <h3 class="mt-4">Stock by Product</h3>
<div class="row">
  <div class="col-12">
    <table class="table table-bordered table-striped">
      <thead class="table-dark">
        <tr>
          <th>Product</th>
          <th>Stock</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= intval($p['stock']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


  </div>

  <!--Customer-->
  <div id="customer" style="display:none">
    <h2>Customer Management</h2>
  </div>

  <!--Products-->
  <div id="products" style="display:none">
    <h2 id="product-management" class="mb-4">Product Management Dashboard</h2>
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
                    <?php if (!empty($p['photo'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($p['photo']) ?>" 
                             alt="Product Photo" style="width:80px;height:auto;">
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
                    <form method="post" enctype="multipart/form-data" class="update-form d-inline">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">

                        <div class="mb-2">
                            <input type="number" step="0.01" name="price" 
                                   value="<?= $p['price'] ?>" class="form-control form-control-sm" 
                                   placeholder="Price">
                        </div>
                        <div class="mb-2">
                            <input type="number" name="stock" 
                                   value="<?= $p['stock'] ?>" class="form-control form-control-sm" 
                                   placeholder="Stock">
                        </div>
                        <div class="mb-2">
                            <input type="file" name="photo" accept="image/*" 
                                   class="form-control form-control-sm">
                        </div>
                        <div class="mb-2">
                            <textarea name="description" class="form-control form-control-sm" 
                                      placeholder="Description"><?= htmlspecialchars($p['description']) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                    </form>

                    <!-- Delete form -->
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

  <!--Orders-->
  <div id="orders" style="display:none">
    <div class="container py-4">
      <h2 class="mb-4">Orders Management</h2>

      <!-- Show alert if message exists -->
      <?php if (!empty($message)): ?>
          <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <table class="table table-bordered">
          <thead class="table-dark">
              <tr>
                  <th>Order ID</th>
                  <th>User</th>
                  <th>Total Amount</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Items</th>
                  <th>Actions</th>
              </tr>
          </thead>
          <tbody>
          <?php if (!empty($orders)): ?>
              <?php foreach ($orders as $o): ?>
                  <tr>
                      <td><?= $o['id'] ?></td>
                      <td><?= htmlspecialchars($o['username']) ?></td>
                      <td>₱<?= number_format($o['total_amount'], 2) ?></td>
                      <td><?= htmlspecialchars($o['status']) ?></td>
                      <td><?= $o['created_at'] ?></td>
                      <td>
                          <ul class="mb-0">
                          <?php $items = getOrderItems($o['id']); ?>
                          <?php foreach ($items as $item): ?>
                              <li>
                                  <?= htmlspecialchars($item['name']) ?> 
                                  (x<?= intval($item['qty']) ?>) - 
                                  ₱<?= number_format($item['price'], 2) ?> each, 
                                  Subtotal: ₱<?= number_format($item['subtotal'], 2) ?>
                              </li>
                          <?php endforeach; ?>
                          </ul>
                      </td>
                      <td>
                          <!-- Update status form -->
                          <form method="post" class="d-inline">
                              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                              <select name="status" class="form-select form-select-sm d-inline w-auto">
                                  <option value="pending" <?= $o['status']=='pending'?'selected':'' ?>>Pending</option>
                                  <option value="processing" <?= $o['status']=='processing'?'selected':'' ?>>Processing</option>
                                  <option value="shipped" <?= $o['status']=='shipped'?'selected':'' ?>>Shipped</option>
                                  <option value="completed" <?= $o['status']=='completed'?'selected':'' ?>>Completed</option>
                                  <option value="cancelled" <?= $o['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                              </select>
                              <button type="submit" class="btn btn-primary btn-sm">Update</button>
                          </form>
                      </td>
                  </tr>
              <?php endforeach; ?>
          <?php else: ?>
              <tr><td colspan="7" class="text-center">No orders found.</td></tr>
          <?php endif; ?>
          </tbody>
      </table>
  </div>
  </div>

  <!--Sales Summary-->
  <div id="sales-summary" style="display:none">
    <h2>Sales Summary</h2>
    <div class="container py-4">

  <!-- Yearly chart -->
  <h3>Yearly Sales Overview</h3>
  <canvas id="yearlyChart" height="100"></canvas>

  <script>
    const yearlyData = {
      labels: <?= json_encode(array_column($yearly, 'year')) ?>,
      datasets: [{
        label: 'Total Sales (₱)',
        data: <?= json_encode(array_column($yearly, 'total_sales')) ?>,
        backgroundColor: 'rgba(54, 162, 235, 0.7)'
      }]
    };
    new Chart(document.getElementById('yearlyChart'), {
      type: 'bar',
      data: yearlyData
    });
  </script>

    <!-- Sales per Product -->
  <h3 class="mt-5">Sales by Product</h3>
  <canvas id="productSalesChart" height="120"></canvas>

  <script>
  const productSalesData = {
    labels: <?= json_encode(array_column($productSales, 'product_name')) ?>,
    datasets: [{
      label: 'Sales by Product (₱)',
      data: <?= json_encode(array_column($productSales, 'total_sales')) ?>,
      backgroundColor: 'rgba(255, 99, 132, 0.7)'
    }]
  };

  new Chart(document.getElementById('productSalesChart'), {
    type: 'bar',
    data: productSalesData,
    options: {
      indexAxis: 'y', // horizontal bars for readability
      scales: {
        x: { beginAtZero: true }
      }
    }
  });
</script>



  <!-- Monthly table -->
  <h3 class="mt-5">Monthly Sales</h3>
  <table class="table table-bordered">
    <thead class="table-dark">
      <tr><th>Month</th><th>Total Sales (₱)</th><th>Orders</th></tr>
    </thead>
    <tbody>
      <?php foreach ($monthly as $m): ?>
        <tr>
          <td><?= $m['period'] ?></td>
          <td>₱<?= number_format($m['total_sales'],2) ?></td>
          <td><?= $m['order_count'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Weekly table collapsible -->
  <h3 class="mt-5">Weekly Sales</h3>
  <button class="btn btn-secondary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#weeklyTable">
    Toggle Weekly Breakdown
  </button>
  <div class="collapse" id="weeklyTable">
    <table class="table table-bordered">
      <thead class="table-dark">
        <tr><th>Week</th><th>Total Sales (₱)</th><th>Orders</th></tr>
      </thead>
      <tbody>
        <?php foreach ($weekly as $w): ?>
          <tr>
            <td><?= $w['period'] ?></td>
            <td>₱<?= number_format($w['total_sales'],2) ?></td>
            <td><?= $w['order_count'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

  </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
  // Sidebar Navigation
  function showView(view, el){
    // hide all sections
    ['dashboard','customer','products','orders','sales-summary'].forEach(v=>{
      const section = document.getElementById(v);
      if(section) section.style.display = (v===view)?'block':'none';
    });

    // remove active from all links
    document.querySelectorAll('.sidebar a').forEach(a=>a.classList.remove('active'));
    // add active to clicked link
    if(el) el.classList.add('active');
  }

  // set default view on page load
  document.addEventListener('DOMContentLoaded', ()=>{
  const hash = window.location.hash.substring(1); // e.g. "sales-summary"
  const validTabs = ['dashboard','customer','products','orders','sales-summary'];
  let defaultTab = validTabs.includes(hash) ? hash : 'dashboard';
  const defaultLink = document.querySelector(`.sidebar a[href="#${defaultTab}"]`);
  if(defaultLink) showView(defaultTab, defaultLink);
});


$(document).on("submit", ".update-form", function(e) {
  e.preventDefault(); // stop reload

  var form = this;
  var formData = new FormData(form);

  $.ajax({
    url: "api/product_update.php",   // your PHP update endpoint
    type: "POST",
    data: formData,
    processData: false, // required for FormData
    contentType: false, // required for FormData
    success: function(response) {
      var data = JSON.parse(response);
      if (data.success) {
        var row = $(form).closest("tr");
        row.find("td:nth-child(5)").text("₱" + parseFloat(data.price).toFixed(2));
        row.find("td:nth-child(6)").text(data.stock);
        row.find("td:nth-child(4)").text(data.description);
        if (data.photo) {
          row.find("td:nth-child(2)").html(
            `<img src="../uploads/${data.photo}" alt="Product Photo" style="width:80px;height:auto;">`
          );
        }
        alert("Product updated successfully!");
      }
    },
    error: function() {
      alert("Update failed.");
    }
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</body>
</html>