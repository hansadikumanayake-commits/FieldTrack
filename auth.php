<?php 

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
function requireLogin(){
    if(!isset($_SESSION['user_id'],$_SESSION['role'])){
        header("Location:login.php");
        exit();
    }
}
function requireRole(array $allowedRoles){
    requireLogin();
    if(!in_array($_SESSION['role'],$allowedRoles,true)){
        http_response_code(403);
        exit("Access denied.You donot have the permission");
    }
}


?>