<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    $user=mysqli_fetch_assoc($result);

    if (mysqli_num_rows($result) == 1) {
        // create a nnew sessin ID after successful login

        session_regenerate_id(true);

        $_SESSION['user_id']=(int) $user['id'];
        $_SESSION['name']=$user['name'];
        $_SESSION['username']=$user['username'];
        $_SESSSION['role']=$user['role'];
        $_SESSION['logged_in']=true;

        if ($user['role'] == 'admin') {
            header("Location: admin_panel.php");
            exit();
        } else {
            header("Location: user_panel.php");
            exit();
        }

    } else {
        header("Location: login_failed.php");
        exit();
    }

} else {
    header("Location: login.php");
    exit();
}
?>