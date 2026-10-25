
<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$item_id = isset($_GET["item_id"]) ? (int) $_GET["item_id"] : 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $item_id = (int) $_POST["item_id"];
    $details = trim($_POST["identification_details"]);
    $user_id = (int) $_SESSION["user_id"];

    $check = $conn->prepare(
        "SELECT item_id FROM ITEM WHERE item_id = ? AND status = 'Found'"
    );
    $check->bind_param("i", $item_id);
    $check->execute();
    $found_item = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$found_item) {
        $message = "This item is not available for claiming.";
    } elseif ($details === "") {
        $message = "Please enter identification details.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO CLAIM (item_id, user_id, identification_details)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iis", $item_id, $user_id, $details);

        if ($stmt->execute()) {
            $message = "Claim submitted successfully. The administrator will review it.";
        } else {
            $message = "Unable to submit claim. Please try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Item - Campus Lost & Found</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>Campus Lost & Found</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="report.php">Report Item</a>
        <a href="search.php">Search Items</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">
    <div class="card">
        <h2>Claim a Found Item</h2>

        <?php if ($message !== ""): ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if ($item_id > 0): ?>
            <form method="POST" action="claim.php">
                <input type="hidden" name="item_id"
                       value="<?php echo $item_id; ?>">

                <label>Item ID</label>
                <input type="text" value="<?php echo $item_id; ?>" disabled>

                <label>Identification Details</label>
                <textarea name="identification_details" required
                          placeholder="Describe details that prove this item belongs to you"></textarea>

                <button type="submit">Submit Claim</button>
            </form>
        <?php else: ?>
            <p>Please open this page from a found item's claim link.</p>
            <p><a href="search.php">Go to Search Items</a></p>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
