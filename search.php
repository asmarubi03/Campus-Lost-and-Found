
<?php
session_start();
require_once "config/db.php";

$item_name = trim($_GET["item_name"] ?? "");
$category_id = $_GET["category_id"] ?? "";
$color = trim($_GET["color"] ?? "");
$location = trim($_GET["location"] ?? "");
$status = $_GET["status"] ?? "";

$sql = "SELECT
            i.item_id,
            i.item_name,
            i.description,
            i.color,
            i.status,
            c.category_name,
            COALESCE(lost_loc.location_name, found_loc.location_name) AS location_name,
            COALESCE(lr.lost_date, fr.found_date) AS item_date
        FROM ITEM i
        JOIN CATEGORY c ON i.category_id = c.category_id
        LEFT JOIN LOST_REPORT lr ON i.item_id = lr.item_id
        LEFT JOIN LOCATION lost_loc ON lr.location_id = lost_loc.location_id
        LEFT JOIN FOUND_REPORT fr ON i.item_id = fr.item_id
        LEFT JOIN LOCATION found_loc ON fr.location_id = found_loc.location_id
        WHERE 1 = 1";

$types = "";
$params = [];

if ($item_name !== "") {
    $sql .= " AND i.item_name LIKE ?";
    $types .= "s";
    $params[] = "%" . $item_name . "%";
}

if ($category_id !== "" && ctype_digit((string)$category_id)) {
    $sql .= " AND i.category_id = ?";
    $types .= "i";
    $params[] = (int)$category_id;
}

if ($color !== "") {
    $sql .= " AND i.color LIKE ?";
    $types .= "s";
    $params[] = "%" . $color . "%";
}

if ($location !== "") {
    $sql .= " AND (lost_loc.location_name LIKE ? OR found_loc.location_name LIKE ?)";
    $types .= "ss";
    $params[] = "%" . $location . "%";
    $params[] = "%" . $location . "%";
}

if (in_array($status, ["Lost", "Found", "Claimed", "Returned"], true)) {
    $sql .= " AND i.status = ?";
    $types .= "s";
    $params[] = $status;
}

$sql .= " ORDER BY item_date DESC";

$stmt = $conn->prepare($sql);

if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$items = $stmt->get_result();

$categories = $conn->query("SELECT category_id, category_name FROM CATEGORY ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Items - Campus Lost & Found</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>Campus Lost & Found</h1>
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
        <h2>Search Items</h2>

        <form method="GET" action="search.php">
            <label>Item Name</label>
            <input type="text" name="item_name"
                   value="<?php echo htmlspecialchars($item_name); ?>">

            <label>Category</label>
            <select name="category_id">
                <option value="">All Categories</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?php echo $cat["category_id"]; ?>"
                        <?php echo ((string)$category_id === (string)$cat["category_id"]) ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($cat["category_name"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Color</label>
            <input type="text" name="color"
                   value="<?php echo htmlspecialchars($color); ?>">

            <label>Location</label>
            <input type="text" name="location"
                   value="<?php echo htmlspecialchars($location); ?>">

            <label>Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach (["Lost", "Found", "Claimed", "Returned"] as $s): ?>
                    <option value="<?php echo $s; ?>"
                        <?php echo $status === $s ? "selected" : ""; ?>>
                        <?php echo $s; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Search Items</button>
        </form>
    </div>

    <h2>Search Results</h2>

    <?php if ($items->num_rows > 0): ?>
        <?php while ($item = $items->fetch_assoc()): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($item["item_name"]); ?></h3>

                <p><strong>Item ID:</strong> <?php echo (int)$item["item_id"]; ?></p>
                <p><strong>Category:</strong> <?php echo htmlspecialchars($item["category_name"]); ?></p>
                <p><strong>Color:</strong> <?php echo htmlspecialchars($item["color"] ?? "Not specified"); ?></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($item["location_name"] ?? "Not specified"); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($item["item_date"] ?? "Not specified"); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($item["status"]); ?></p>
                <p><?php echo nl2br(htmlspecialchars($item["description"] ?? "")); ?></p>

                <?php if ($item["status"] === "Found"): ?>
                    <?php if (isset($_SESSION["user_id"]) && ($_SESSION["user_type"] ?? "") !== "Admin"): ?>
                        <a href="claim.php?item_id=<?php echo (int)$item["item_id"]; ?>">Claim Item</a>
                    <?php elseif (!isset($_SESSION["user_id"])): ?>
                        <a href="login.php">Login to Claim</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <p>No items found. Try changing your search.</p>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
