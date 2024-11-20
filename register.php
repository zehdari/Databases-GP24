<?php
require 'includes/header.php'; // Include the header, which already starts the session

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Hash the password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Prepare and execute the SQL statement
    $sql = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password_hash);

    if ($stmt->execute()) {
        // Automatically log the user in after registration
        $_SESSION['user_id'] = $stmt->insert_id; // Get the newly created user ID
        $_SESSION['username'] = $username;

        // Redirect to the index page
        header("Location: index.php");
        exit;
    } else {
        $error = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <form method="POST" action="register.php">
        <h2>Sign Up</h2>
        <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Sign Up</button>
    </form>
</body>
</html>
