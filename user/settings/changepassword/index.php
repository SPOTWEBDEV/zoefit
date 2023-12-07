<?php

include('../../../configs/database.php');
include('../../../configs/clients/authorization.php');

// old password
// Start session to store error/success messages
// session_start();

// Dummy old password (replace this with your actual old password stored somewhere securely)
$oldPasswordStored = "$password";

// Check if form is submitted
if (isset($_POST['submit'])) {
    // Retrieve form data
    $oldPassword = $_POST['old_password'];
    $confirmOldPassword = $_POST['confirm_old_password'];
    $newPassword = $_POST['new_password'];

    // Check if the old password matches the confirmed old password
    if ($oldPassword === $confirmOldPassword && $oldPassword === $oldPasswordStored) {

        $fetch = mysqli_query($conn, "UPDATE `clients` SET `password`='$newPassword' WHERE `id`='$id'");
        // Update the password (replace this with your password update logic)
        // For example, update the password in a database

        // Example: updating the password (replace this with your actual update query)
        // $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        // Update query to change password for the user

        // For demonstration, just setting a success message
        $_SESSION['success_message'] = "Password changed successfully!";
    } else {
        // Set an error message if old passwords don't match or the old password is incorrect
        $_SESSION['error_message'] = "Old passwords do not match or the old password is incorrect.";
    }

    // Redirect to prevent form resubmission on page refresh
    header("Location: ./index.php");
    exit();
}
// new password
// confirm password


?>

<a href="../../../configs/database.php"></a>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZOEFEED - RESET PASSWORD</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>

    <?php
    // Display error/success messages if set
    if (isset($_SESSION['error_message'])) {
        echo "<p style='color: red;'>{$_SESSION['error_message']}</p>";
        unset($_SESSION['error_message']);
    }

    if (isset($_SESSION['success_message'])) {
        echo "<p style='color: green;'>{$_SESSION['success_message']}</p>";
        unset($_SESSION['success_message']);
    }
    ?>

    <form method="POST" >
        <label for="old_password">Old Password:</label>
        <input type="password" name="old_password"><br><br>

        <label for="confirm_old_password">Confirm Old Password:</label>
        <input type="password" name="confirm_old_password"><br><br>

        <label for="new_password">New Password:</label>
        <input type="password" name="new_password" ><br><br>

        <button type="submit" name="submit">submit</button>
    </form>
</body>

</html>