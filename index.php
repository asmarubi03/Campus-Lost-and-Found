
<?php
session_start();
require_once "config/db.php";

$result = $conn->query("SELECT COUNT(*) AS total FROM CATEGORY");
$category_count = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM ITEM");
$item_count = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM ITEM WHERE status = 'Lost'");
$lost_count = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM ITEM WHERE status = 'Found'");
$found_count = $result->fetch_assoc()["total"];

$sql = "SELECT
            i.item_id,
            i.item_name,
            i.description,
            i.color,
            c.category_name,
            l.location_name,
            fr.found_date
        FROM FOUND_REPORT fr
        JOIN ITEM i ON fr.item_id = i.item_id
        JOIN CATEGORY c ON i.category_id = c.category_id
        JOIN LOCATION l ON fr.location_id = l.location_id
        WHERE i.status = 'Found'
        ORDER BY fr.report_date DESC
        LIMIT 5";

$recent_items = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Lost & Found</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>Campus Lost & Found Management System</h1>

    <nav>
        <a href="index.php">Home</a>
        <a href="report.php">Report Item</a>
        <a href="search.php">Search Items</a>

        <?php if (isset($_SESSION["user_id"])): ?>
            <?php if (($_SESSION["user_type"] ?? "") === "Admin"): ?>
                <a href="admin.php">Admin Dashboard</a>
            <?php endif; ?>

            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="register.php">Register</a>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>

<main class="container">

    <div class="card">
        <h2>Welcome to Campus Lost & Found</h2>
        <p>
            Lost something on campus? Found an item?
            Report it here and help return lost belongings
            to their rightful owners.
        </p>
        <a href="report.php">Report an Item</a>
        <a href="search.php">Search Items</a>
    </div>

    <h2>Project Overview</h2>

    <div class="card">
        <h3>Total Categories</h3>
        <p><?php echo (int)$category_count; ?></p>
    </div>

    <div class="card">
        <h3>Total Items</h3>
        <p><?php echo (int)$item_count; ?></p>
    </div>

    <div class="card">
        <h3>Lost Items</h3>
        <p><?php echo (int)$lost_count; ?></p>
    </div>

    <div class="card">
        <h3>Found Items Available</h3>
        <p><?php echo (int)$found_count; ?></p>
    </div>

    <h2>Recently Found Items</h2>
    <p>Here are the latest items found on campus.</p>

    <?php if ($recent_items && $recent_items->num_rows > 0): ?>

        <?php while ($item = $recent_items->fetch_assoc()): ?>

            <div class="card">
                <h3>
                    <?php echo htmlspecialchars($item["item_name"]); ?>
                </h3>

                <p>
                    <strong>Category:</strong>
                    <?php echo htmlspecialchars($item["category_name"]); ?>
                </p>

                <p>
                    <strong>Color:</strong>
                    <?php echo htmlspecialchars($item["color"] ?? "Not specified"); ?>
                </p>

                <p>
                    <strong>Found Location:</strong>
                    <?php echo htmlspecialchars($item["location_name"]); ?>
                </p>

                <p>
                    <strong>Found Date:</strong>
                    <?php echo htmlspecialchars($item["found_date"]); ?>
                </p>

                <p>
                    <strong>Description:</strong>
                    <?php echo htmlspecialchars($item["description"] ?? "No description available"); ?>
                </p>

                <p><strong>Status:</strong> Found</p>

                <a href="search.php">View Item</a>
            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="card">
            <p>No recently found items are currently available.</p>
            <a href="report.php">Report a Found Item</a>
        </div>

    <?php endif; ?>

</main>

<footer>
    <p>Campus Lost & Found Management System</p>
    <p>Metropolitan University, Sylhet</p>
</footer>

</body>
</html>
