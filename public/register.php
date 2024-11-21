<?php
require '../includes/header.php'; // Include the header, which already starts the session

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize input
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Basic input validation (optional but recommended)
    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        // Check if the username already exists
        $check_sql = "SELECT user_id FROM users WHERE username = ?";
        if ($check_stmt = $conn->prepare($check_sql)) {
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                // Username already exists
                $error = "Username already taken. Please choose another one.";
            } else {
                // Username is available, proceed with registration

                // Hash the password
                $password_hash = password_hash($password, PASSWORD_BCRYPT);

                // Prepare and execute the SQL statement
                $sql = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("ss", $username, $password_hash);

                    if ($stmt->execute()) {
                        // Automatically log the user in after registration
                        $_SESSION['user_id'] = $stmt->insert_id; // Get the newly created user ID
                        $_SESSION['username'] = $username;

                        // Redirect to the index page
                        header("Location: index.php");
                        exit;
                    } else {
                        // Handle execution errors
                        $error = "Error: " . $stmt->error;
                    }
                } else {
                    // Handle preparation errors
                    $error = "Error preparing the registration statement.";
                }
            }

            // Close the check statement
            $check_stmt->close();
        } else {
            // Handle preparation errors for the check statement
            $error = "Error preparing the username check statement.";
        }
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
        <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Sign Up</button>
    </form>
</body>
</html>
