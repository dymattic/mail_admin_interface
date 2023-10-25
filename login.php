<?php
/**
 * Created by PhpStorm.
 * User: dpastian
 * Date: 1/3/18
 * Time: 11:54 AM
 */
session_start();
unset($_SESSION);
session_destroy();
session_write_close();
session_start();
require_once 'main.php';
switch ($_GET['action']) {
        case 'login':
            if (isset($_POST['email']) && isset ($_POST['password'])) {
                if (!login($_POST['email'], $_POST['password'])) {
                    echo 'Authentifizierung fehlgeschlagen.';
                }
                else{
                    redirect('index.php');
                }
            }
            break;
        default:
            break;
    }

?>


<html>

<link rel="stylesheet" type="text/css" href="styles/spectre.css"/>
<link rel="stylesheet" type="text/css" href="styles/spectre-icons.css"/>
<link rel="stylesheet" type="text/css" href="styles/spectre-exp.css"/>
<link rel="stylesheet" type="text/css" href="styles/font-awesome.css"/>
<link rel="stylesheet" type="text/css" href="styles/styles.css"/>
<head>
</head>
<body>

<div class=wrapper>
    <?php
	    include './templates/login.tpl';
           ?>
</div>
</body>
