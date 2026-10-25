
<?php
require_once "config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $message = "Enter valid details. Password must be at least 6 characters.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM `USER` WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "This email is already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_type = "Student";

            $stmt = $conn->prepare(
                "INSERT INTO `USER` (name, email, phone, password, user_type)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "sssss",
                $name,
                $email,
                $phone,
                $hashed_password,
                $user_type
            );

            if ($stmt->execute()) {
                $message = "Registration successful! You can now log in.";
            } else {
                $message = "Registration failed. Please try again.";
            }
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
    <title>Register | Campus Lost & Found</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header>
    <h1>Campus Lost & Found</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="report.php">Report Item</a>
        <a href="search.php">Search Items</a>
        <a href="login.php">Login</a>
    </nav>
</header>

<div class="container">
    <div class="card">
        <h2>Create an Account</h2>

        <?php if ($message !== ""): ?>
            <p><strong><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></strong></p>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <label for="name">Full Name *</label>
            <input type="text" id="name" name="name" required maxlength="100">

            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" required>

            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" maxlength="20">

            <label for="password">Password (minimum 6 characters) *</label>
            <input type="password" id="password" name="password"
                   minlength="6" required>

            <button type="submit" class="button">Register</button>
        </form>

        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

<footer>
    <p>Campus Lost & Found Management System | University Campus</p>
</footer>

</body>
</html>

