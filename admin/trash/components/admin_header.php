<header>
    <div class="logo">
        <img src="../assets/imgs/coffee-logo.png" width="90">
    </div>
    <div class="right">
        <div class="bx bxs-user" id="user-btn"></div>
        <div class="toggle-btn">
            <i class="bx bx-menu"></i>
        </div>
    </div>
    <div class="profile-detail">
        <?php
$profile = runQuery("SELECT * FROM admin WHERE id = ?", ["i", [$admin_id]]);
if (!empty($profile)) {
    $fetch_profile = $profile[0];
}
?>

        <div class="profile">
            <img src="../uploaded_files/<?= $fetch_profile['image']; ?>" class="logo-img" width="150">
            <p><?= $fetch_profile['name']; ?></p>
            <div class="flex-btn">
                <a href="profile.php" class="btn">Profile</a>
                <a href="../components/admin_logout.php" onclick="return confirm('logout from this website');" class="btn">logout</a>
            </div>
        </div>

    </div>
</header>

<div class="sidebar-container">
    <div class="sidebar">
        <h5>Menu</h5>
        <div class="navbar">
            <ul>
                <li><a href="#"><i class="bx bxs-home-smile active" onclick="showView('dashboard')"></i>Dashboard</a></li>
                <li><a href="#"><i class="bx bxs-user-detail" onclick="showView('customer')"></i>Manage Customer</a></li>
                <li><a href="#"><i class="bx bxs-shopping-bags" onclick="showView('products')"></i>Manage Products</a></li>
                <li><a href="#"><i class="bx bxs-food-menu" onclick="showView('orders')"></i>Manage Orders</a></li>
                <li><a href="#" onclick="showView('sales-summary')"><i class="bx bx-bar-chart"></i>Sales Summary</a></li>
            </ul>
        </div>
    </div>
</div>
