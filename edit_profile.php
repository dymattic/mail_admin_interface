<?php
/**
 * Created by PhpStorm.
 * User: dpastian
 * Date: 12/29/17
 * Time: 2:41 PM
 */
session_start ();
require_once 'main.php';


if (isset( $_SESSION[ 'username' ] ) && isset( $_SESSION[ 'auth' ] ) && $_SESSION[ 'auth' ] == true) {
?>
<html>
<!--<link rel="stylesheet" type="text/css" href="styles/styles.css"/>-->
<link rel="stylesheet" type="text/css" href="styles/spectre.css"/>
<link rel="stylesheet" type="text/css" href="styles/spectre-icons.css"/>
<link rel="stylesheet" type="text/css" href="styles/spectre-exp.css"/>
<link rel="stylesheet" type="text/css" href="styles/font-awesome.css"/>
<link rel="stylesheet" type="text/css" href="styles/styles.css"/>

<head>
</head>
<header class="navbar">
    <section class="navbar-section">
        <a href="index.php?logout" class="btn btn-primary">Logout</a>

    </section>
    <section class="navbar-section">
        <a id="home" href="index.php" class="btn btn-primary">Mail Admin Interface</a>

    </section>
    <section class="navbar-section">
        <a class="btn btn-link" target="_blank" href="https://rspamd.planitprima.com/">Rspamd-Interface</a>
        <div class="input-group input-inline">
        </div>
    </section>
</header>
<body>
<div class="content_wrapper col-8">

    <div class="column col-12">
        <form action="index.php?p=email-account&m=su&exact=true&searchuser=<?php echo $_SESSION['username'];
        ?>&edit=edited"
              method="post">

        <div class="panel">
            <div class="panel-header text-center">
                <figure class="avatar avatar-lg">
                    <img src="<?php echo $_SESSION['avatar']; ?>" alt="Avatar">
                </figure>
                <div class="panel-title h5 mt-10"><?php echo $_SESSION[ 'username' ]; ?></div>
                <div class="panel-subtitle"><?php echo $_SESSION['user_title'];?></div>
            </div>
            <div class="panel-body">
                <div class="tile tile-centered">
                    <div class="tile-content">
                        <div class="columns col-12">
                            <div class="column col-12 flex-centered">
                                <div class="container col-12">
                                        <div class="columns col-12">
                                            <div class="column col-4">
                                                <label for="inp_nick">Your Nickname:</label>
                                            </div>
                                            <div class="column col-8">
                                                <input id="inp_nick" class="form-input col-5" type="text"
                                                       name="nickname" <?php if(isset($_SESSION['nickname'])) echo 'value="'.$_SESSION['nickname'].'"' ;?>
                                                       placeholder="Nickname"/>
                                            </div>
                                        </div>
                                        <div class="columns col-12">
                                            <div class="column col-4">
                                                <label for="inp_name">Your Name:</label>
                                            </div>

                                            <div class="column col-8">
                                                <input id="inp_name" class="form-input col-5" name="name" type="text"
                                                    <?php if (isset($_SESSION['name'])) echo 'value="' . $_SESSION['name'] . '"' ;?>
                                                       placeholder="Peter">
                                                </div>

                                        </div>
                                        <div class="columns col-12">
                                            <div class="column col-4">
                                                <label for="inp_surname">Your Surname:</label>

                                            </div>
                                            <div class="column col-8">
                                                <input id="inp_surname" class="form-input col-5" name="family_name"
                                                       type="text" <?php if (isset($_SESSION['family_name'])) echo 'value="' . $_SESSION['family_name'] . '"'; ?>
                                                       placeholder="Shvonsson">

                                            </div>
                                        </div>
                                        </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
            <div class="panel-footer centered col-4">
                <button class="btn btn-primary btn-block">Save</button>
            </div>
        </form>

    </div>
    </div>


</div>
</body>

<?php
}
else{
    redirect ( 'login.php' );
}
?>
