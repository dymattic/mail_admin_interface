<?php
/**
 * Created by PhpStorm.
 * User: dpastian
 * Date: 12/29/17
 * Time: 2:41 PM
 */
session_start();
require_once 'mail-users.php';
require_once 'main.php';
$users = new mailuser();

refreshSessionData();
if (isset($_SESSION['username']) && isset($_SESSION['auth']) && $_SESSION['auth'] == true) {
?>



<html lang="Whatever">
<!--<link rel="stylesheet" type="text/css" href="styles/styles.css"/>-->
<link rel="stylesheet" type="text/css" href="styles/spectre.css"/>
<link rel="stylesheet" type="text/css" href="styles/spectre-icons.css"/>
<link rel="stylesheet" type="text/css" href="styles/spectre-exp.css"/>
<link rel="stylesheet" type="text/css" href="styles/font-awesome.css"/>
<link rel="stylesheet" type="text/css" href="styles/styles.css"/>

<head>
    <title>Dymattic Mail Admin Interface</title>
</head>
<header class="navbar">
    <section class="navbar-section">
        <a href="index.php?logout" class="btn btn-primary">Logout</a>

    </section>
    <section class="navbar-section">
        <a id="home" href="index.php" class="btn btn-primary">Mail Admin Interface</a>

    </section>
    <?php if(!$_SESSION['normie']){ ?>
    <section class="navbar-section">
        <a class="btn btn-link" target="_blank" href="https://rspamd.planitprima.com/">Rspamd-Interface</a>
        <div class="input-group input-inline">
        </div>
    </section>
	<?php } ?>
</header>
<body>
<?php
$active1 = '"tab-item"';
$active2 = '"tab-item"';
$active3 = '"tab-item"';
$active4 = '"tab-item"';
switch($_GET['m']){

    case 'su':
        $active1 = '"tab-item active"';
        break;
    case 'create':
        $active2 = '"tab-item active"';
        break;
    case 'aliases':
        $active3 = '"tab-item active"';
        break;
    /*

    case 'rspamd':
        $active4 = '"tab-item active"';
        break;
     * */
    default:
        $active1 = '"tab-item active"';
        break;
}

?>

<div class="content_wrapper col-8">

    <div class="column col-12">
        <div class="panel">
            <div class="panel-header text-center">
                <figure class="avatar avatar-lg">
                    <img src="<?php echo $_SESSION['avatar']; ?>" alt="Avatar">
                </figure>
                <div class="panel-title h5 mt-10"><a href="edit_profile.php"><?php echo $_SESSION['username']; ?></a></div>
                <div class="panel-subtitle"><?php echo $_SESSION['user_title']; ?></div>
            </div>
            <nav class="panel-nav">
                <ul class="tab tab-block">
                    <?php if(!$_SESSION['normie']){?>
                    <li class=<?php echo $active1; ?>>
                        <a href="?p=email-account&m=su">Nach Adressen Suchen</a>
                    </li>
                    <li class=<?php echo $active2; ?>>
                        <a href="?p=email-account&m=create">Neue Adresse Hinzuf&uuml;gen</a>
                    </li>
                    <li class=<?php echo $active3; ?>>
                        <a href="?p=email-account&m=aliases">Aliase Verwalten</a>
                    </li>
                    <!--
                     <li class=<?php //echo $active4; ?>>
                        <a href="?p=email-account&m=rspamd">Whitelist Verwalten</a>
                    </li>
                     -->
	                <?php }?>
                </ul>
            </nav>
            <div class="panel-body">
                <div class="tile tile-centered">
                    <div class="tile-content">
                        <div class="columns col-8 centered">
                            <div class="column col-12 flex-centered">
                                <div class="container col-12">
                                    <?php
                                    echo $users->run(); ?>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>
    </div>


</div>
</body>

<?php } else {
    redirect('login.php');
}
?>