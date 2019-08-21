<?php
if (isset($_POST['reset-pwd'])) {
    require 'init.php';
    $user_email = $_POST['email'];

    $selector= bin2hex(random_bytes(8)) ;
    $token = random_bytes(32);

    $url = "www.test.kyucu.co.ke/user/new-password.php?selector=".$selector."&validator=".bin2hex($token);

    $expires =date("U") + 1800;

    $db = new DATABASE();
    
    //delete all tokens in the database from the specific user
    $sql = "DELETE FROM pwdreset WHERE pwdreset_email = ?";
    if (!$stmt = $db->conn()->prepare($sql)) {
        echo "there was an error ";
        exit();
    } else {
        $stmt->execute([$user_email]);
    }

    //insert the token to the database
    $sql = "INSERT INTO pwdreset (pwdreset_email,pwdreset_selector,pwdreset_token,pwdreset_expires)
     VALUES(?,?,?,?)";
    if (!$stmt = $db->conn()->prepare($sql)) {
        echo "there was an error ";
        exit();
    } else {
        //hash the token before inserting into the databse
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $stmt->execute([$user_email,$selector,$hashedToken,$expires]);
    }

    //sending the email to the user
    require 'mailer.php';
    $message='<p>We received a request to Reset Your Password. If you did not Send this request Please Ignore this Email And Try To Login </p>';
    $message .= '<br> <p>Below is Your Password Reset Link</p>';
    $message .= '<br> <a href="' .$url .'">' . $url. '</a>';


    $subject= "Password Reset ";
    $from= "info@kyucu.co.ke";
    sendmail($user_email, $message, $from, $subject);

    redirect("../reset.php?reset=success");
} 

//set new password
elseif (isset($_POST['new-pwd'])) {
    $selector = $_POST['selector'];
    $validator = $_POST['validator'];
    $user_pwd = $_POST['password'];

    $currentDate = date("U");

    require 'init.php';
    $db = new DATABASE();

    //retrive the selector token from the database
    $sql = "SELECT * FROM pwdreset WHERE pwdreset_selector =? AND pwdreset_expires >= ?";
    if (!$stmt = $db->conn()->prepare($sql)) {
        echo "there was an error ";
        exit();
    } else {
        $stmt->execute([$selector,$currentDate]);
        $row =$stmt->fetch();
        $user_email = $row['pwdreset_email'];
        $tokenbin = hex2bin($validator);
        $tokenCheck = password_verify($tokenbin, $row['pwdreset_token']);


        if ($tokenCheck === false) {
            echo "token do no match ";
            exit();
        } elseif ($tokenCheck === true) {
            //get details of the user who requested to reset the password
            $tokenEmail = $row['pwdreset_email'];

            //update the user password
            $sql = "UPDATE user SET user_pwd = ? WHERE user_email =?";

            if (!$stmt = $db->conn()->prepare($sql)) {
                echo "there was an error in preparing the update password script";
                exit();
            } else {
                //hash the password before inserting into the databse
                $hashedpwd = password_hash($user_pwd, PASSWORD_DEFAULT);
                $stmt->execute([$hashedpwd,$user_email]);

                //delete all tokens in the database from the specific user
                $sql = "DELETE FROM pwdreset WHERE pwdreset_email = ?";
                if (!$stmt = $db->conn()->prepare($sql)) {
                    echo "there was an error ";
                    exit();
                } else {
                    $stmt->execute([$user_email]);
                }

                redirect("../login.php?resetsuccess");
            }
        }
    }
} else {
    require 'init.php';
    redirect("../login.php");
}