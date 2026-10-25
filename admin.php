
<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "Admin") {
    header("Location: login.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["claim_id"], $_POST["action"])) {
    $claim_id = (int) $_POST["claim_id"];
    $action = $_POST["action"];

    if (in_array($action, ["Approved", "Rejected"], true)) {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare(
                "SELECT item_id FROM CLAIM
                 WHERE claim_id = ? AND status = 'Pending'
                 FOR UPDATE"
            );
            $stmt->bind_param("i", $claim_id);
            $stmt->execute();
            $claim = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$claim) {
                throw new Exception("This claim is no longer pending.");
            }

            if ($action === "Approved") {
                $stmt = $conn->prepare(
                    "UPDATE ITEM SET status = 'Claimed'
                     WHERE item_id = ? AND status = 'Found'"
                );
                $stmt->bind_param("i", $claim["item_id"]);
                $stmt->execute();
                $updated = $stmt->affected_rows;
                $stmt->close();

                if ($updated !== 1) {
                    throw new Exception("This item is no longer available.");
                }

                $stmt = $conn->prepare(
                    "UPDATE CLAIM SET status = 'Rejected'
                     WHERE item_id = ? AND status = 'Pending' AND claim_id <> ?"
                );
                $stmt->bind_param("ii", $claim["item_id"], $claim_id);
                $stmt->execute();
                $stmt->close();
            }

            $admin_id = (int) $_SESSION["user_id"];
            $stmt = $conn->prepare(
                "UPDATE CLAIM SET status = ?, verified_by = ?
                 WHERE claim_id = ? AND status = 'Pending'"
            );
            $stmt->bind_param("sii", $action, $admin_id, $claim_id);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new Exception("Unable to update the claim.");
            }

            $stmt->close();
            $conn->commit();
            $message = "Claim " . $action . " successfully.";
        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage();
        }
    }
}

$result = $conn->query("SELECT COUNT(*) AS total FROM ITEM");
$item_count = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM `USER`");
$user_count = $result->fetch_assoc()["total"];

$result = $conn->query("SELECT COUNT(*) AS total FROM CLAIM WHERE status = 'Pending'");
$pending_claims = $result->fetch_assoc()["total"];

$sql = "SELECT
            cl.claim_id,
            cl.item_id,
            cl.claim_date,
            cl.identification_details,
            cl.status,
            u.name AS claimant_name,
            u.email AS claimant_email,
            i.item_name
        FROM CLAIM cl
        JOIN `USER` u ON cl.user_id = u.user_id
        JOIN ITEM i ON cl.item_id = i.item_id
        ORDER BY cl.claim_date DESC";

$claims = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>Campus Lost & Found</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="search.php">Search Items</a>
        <a href="report.php">Report Item</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="container">
    <h2>Admin Dashboard</h2>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!</p>

    <?php if ($message !== ""): ?>
        <div class="card">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>Total Items</h3>
        <p><?php echo $item_count; ?></p>
    </div>

    <div class="card">
        <h3>Registered Users</h3>
        <p><?php echo $user_count; ?></p>
    </div>

    <div class="card">
        <h3>Pending Claims</h3>
        <p><?php echo $pending_claims; ?></p>
    </div>

    <h2>Manage Claims</h2>

    <?php if ($claims && $claims->num_rows > 0): ?>
        <?php while ($claim = $claims->fetch_assoc()): ?>
            <div class="card">
                <h3><?php echo htmlspecialchars($claim["item_name"]); ?></h3>
                <p><strong>Claim ID:</strong> <?php echo (int)$claim["claim_id"]; ?></p>
                <p><strong>Item ID:</strong> <?php echo (int)$claim["item_id"]; ?></p>
                <p><strong>Claimant:</strong> <?php echo htmlspecialchars($claim["claimant_name"]); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($claim["claimant_email"]); ?></p>
                <p><strong>Details:</strong> <?php echo nl2br(htmlspecialchars($claim["identification_details"])); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($claim["status"]); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($claim["claim_date"]); ?></p>

                <?php if ($claim["status"] === "Pending"): ?>
                    <form method="POST" action="admin.php">
                        <input type="hidden" name="claim_id" value="<?php echo (int)$claim["claim_id"]; ?>">
                        <button type="submit" name="action" value="Approved">Approve Claim</button>
                        <button type="submit" name="action" value="Rejected">Reject Claim</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <p>No claims have been submitted yet.</p>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
