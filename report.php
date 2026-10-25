
<?php
require_once "config/db.php";

$categories = $conn->query(
    "SELECT category_id, category_name FROM CATEGORY ORDER BY category_name"
);

$locations = $conn->query(
    "SELECT location_id, location_name FROM LOCATION ORDER BY location_name"
);

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $item_name = trim($_POST["item_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $color = trim($_POST["color"] ?? "");
    $category_id = (int)($_POST["category_id"] ?? 0);
    $location_id = (int)($_POST["location_id"] ?? 0);
    $report_type = $_POST["report_type"] ?? "";
    $item_date = $_POST["item_date"] ?? "";

    if (
        $item_name === "" ||
        $category_id < 1 ||
        $location_id < 1 ||
        !in_array($report_type, ["Lost", "Found"], true) ||
        $item_date === ""
    ) {
        $message = "Please complete all required fields.";
    } else {
        $conn->begin_transaction();

        try {
            $status = $report_type;

            $stmt = $conn->prepare(
                "INSERT INTO ITEM (item_name, description, color, category_id, status)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "sssis",
                $item_name,
                $description,
                $color,
                $category_id,
                $status
            );
            $stmt->execute();
            $item_id = $conn->insert_id;
            $stmt->close();

            $test_email = "project.reporter@example.com";

            $stmt = $conn->prepare(
                "SELECT user_id FROM `USER` WHERE email = ?"
            );
            $stmt->bind_param("s", $test_email);
            $stmt->execute();
            $user_result = $stmt->get_result();
            $user = $user_result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $test_name = "Project Test Reporter";
                $test_password = password_hash(
                    bin2hex(random_bytes(16)),
                    PASSWORD_DEFAULT
                );
                $test_type = "Student";

                $stmt = $conn->prepare(
                    "INSERT INTO `USER` (name, email, password, user_type)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "ssss",
                    $test_name,
                    $test_email,
                    $test_password,
                    $test_type
                );
                $stmt->execute();
                $user_id = $conn->insert_id;
                $stmt->close();
            } else {
                $user_id = (int)$user["user_id"];
            }

            if ($report_type === "Lost") {
                $stmt = $conn->prepare(
                    "INSERT INTO LOST_REPORT
                     (item_id, user_id, location_id, lost_date, additional_details)
                     VALUES (?, ?, ?, ?, ?)"
                );
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO FOUND_REPORT
                     (item_id, user_id, location_id, found_date, additional_details)
                     VALUES (?, ?, ?, ?, ?)"
                );
            }

            $stmt->bind_param(
                "iiiss",
                $item_id,
                $user_id,
                $location_id,
                $item_date,
                $description
            );
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $message = "Your " . strtolower($report_type) .
                       " item report was submitted successfully.";
        } catch (Throwable $e) {
            $conn->rollback();
            $message = "The report could not be saved. Please check your entries and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report an Item | Campus Lost & Found</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>Campus Lost & Found</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="report.php">Report Item</a>
        <a href="search.php">Search Items</a>
        <a href="login.php">Admin Login</a>
    </nav>
</header>

<div class="container">
    <div class="card">
        <h2>Report a Lost or Found Item</h2>
        <p>Fields marked * are required.</p>

        <?php if ($message !== ""): ?>
            <p><strong><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></strong></p>
        <?php endif; ?>

        <form method="POST" action="report.php">
            <label for="report_type">Report Type *</label>
            <select name="report_type" id="report_type" required>
                <option value="">Choose Lost or Found</option>
                <option value="Lost">I lost an item</option>
                <option value="Found">I found an item</option>
            </select>

            <label for="item_name">Item Name *</label>
            <input type="text" id="item_name" name="item_name"
                   required maxlength="100" placeholder="Example: Black wallet">

            <label for="category_id">Category *</label>
            <select name="category_id" id="category_id" required>
                <option value="">Choose a category</option>
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <option value="<?php echo (int)$category["category_id"]; ?>">
                        <?php echo htmlspecialchars($category["category_name"], ENT_QUOTES, "UTF-8"); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="color">Color</label>
            <input type="text" id="color" name="color"
                   maxlength="50" placeholder="Example: Black">

            <label for="location_id">Location *</label>
            <select name="location_id" id="location_id" required>
                <option value="">Choose a campus location</option>
                <?php while ($location = $locations->fetch_assoc()): ?>
                    <option value="<?php echo (int)$location["location_id"]; ?>">
                        <?php echo htmlspecialchars($location["location_name"], ENT_QUOTES, "UTF-8"); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="item_date">Date Lost or Found *</label>
            <input type="date" id="item_date" name="item_date" required>

            <label for="description">Description / Additional Details</label>
            <textarea id="description" name="description" rows="4"
                      placeholder="Describe the item to help identify it."></textarea>

            <button type="submit" class="button">Submit Report</button>
        </form>
    </div>
</div>

<footer>
    <p>Campus Lost & Found Management System | University Campus</p>
</footer>

</body>
</html>
