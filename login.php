<?php

include "db.php";




if (isset($_SESSION["login_message"])) {
    
    unset($_SESSION["login_message"]);
}


if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] == "teacher") {
        echo "<script>window.location.href='teacher.php';</script>";
        exit;
    } else {
        echo "<script>window.location.href='student.php';</script>";
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, trim($_POST["email"]));
    $password = $_POST["password"];

    if ($email == "" || $password == "") {
        echo "<p style='color:red;'>Email and password are required.</p>";
    } else {
        $result = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);

            if ($password == $user["password"]) {
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["role"] = $user["role"];

                if ($user["role"] == "teacher") {
                    echo "<script>window.location.href='teacher.php';</script>";
                    exit;
                } else {
                    echo "<script>window.location.href='student.php';</script>";
                    exit;
                }

            } else {
echo "<script>
        alert('Invalid email or password .');
        
    </script>";            }
        } else {
echo "<script>
        alert('Invalid email or password');
        
    </script>";        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="form">
        <h1>Assignment Tracker</h1>
        <p>Login to continue.</p>

       

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <p class="small-text">if you are new -> <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
