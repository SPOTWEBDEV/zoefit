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

     <?php include('../../../includes/navbar.php') ?>

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

    <section class="bg-gray-50 dark:bg-gray-900">
  <div class="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0">
      <a href="#" class="flex items-center mb-6 text-2xl font-semibold text-gray-900 dark:text-white">
          <img class="w-8 h-8 mr-2" src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/logo.svg" alt="logo">
          Flowbite    
      </a>
      <div class="w-full p-6 bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md dark:bg-gray-800 dark:border-gray-700 sm:p-8">
          <h2 class="mb-1 text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
              Change Password
          </h2>
          <form class="mt-4 space-y-4 lg:mt-5 md:space-y-5" action="#">
              <div>
                  <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Your email</label>
                  <input type="email" name="email" id="email" class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="name@company.com" required="">
              </div>
              <div>
                  <label for="password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">New Password</label>
                  <input type="password" name="password" id="password" placeholder="••••••••" class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required="">
              </div>
              <div>
                  <label for="confirm-password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Confirm password</label>
                  <input type="confirm-password" name="confirm-password" id="confirm-password" placeholder="••••••••" class="bg-gray-50 border border-gray-300 text-gray-900 sm:text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required="">
              </div>
              <div class="flex items-start">
                  <div class="flex items-center h-5">
                    <input id="newsletter" aria-describedby="newsletter" type="checkbox" class="w-4 h-4 border border-gray-300 rounded bg-gray-50 focus:ring-3 focus:ring-primary-300 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-primary-600 dark:ring-offset-gray-800" required="">
                  </div>
                  <div class="ml-3 text-sm">
                    <label for="newsletter" class="font-light text-gray-500 dark:text-gray-300">I accept the <a class="font-medium text-primary-600 hover:underline dark:text-primary-500" href="#">Terms and Conditions</a></label>
                  </div>
              </div>
              <button type="submit" class="w-full text-white bg-primary-600 hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800">Reset passwod</button>
          </form>
      </div>
  </div>
</section>

    <?php include('../../../includes/footer.php') ?>
</body>

</html>