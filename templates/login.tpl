<div class="s-brand" id="loginLogo">
    <a href="https://webmail.planitprima.com" class="s-logo tooltip tooltip-bottom" data-tooltip="Planit Prima Mail Services">
        <img src="img/logo.png" alt="Planit Prima Mail Services"/>
    </a>
</div>
<div class="column" id="errMsg" style="visibility:hidden;">
    <div class="text-center"></div>
    <div class="toast  centered text-center toast-error" id="loginError"><span> Fehler: Der eingegebene Benutzername oder das Passwort sind falsch! </span>
    </div>
</div>
<div style="margin-bottom: 20%" id="login" class="empty col-7 centered">
    <div class="empty-icon">
        <i class="icon icon-people"></i>
    </div>
    <p class="empty-title h5">Dymattic Mail Admin</p>
    <div id="loginBox" class="panel col-7 centered">
        <form method="POST" action="login.php?action=login" class="panel-header login">
            <div class="form-group">
                <label class="form-label" for="email">E-Mail:</label>
                <input class="form-input" name="email" id="email" placeholder="E-Mail Adresse" type="email"
                       value="<?php if (isset($_COOKIE['remember_me'])) {echo $_COOKIE['remember_me'];
                       } ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Passwort:</label>
                <input class="form-input" name="password" id="password" placeholder="Passwort" type="password" required>
            </div>
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="remember" value=1
                    <?php if(isset($_COOKIE['remember_me'])) {
                        echo 'checked="checked"';
                    }
else {
    echo '';
}
                        ?>>
                    <i class="form-icon"></i> Benutzernamen Merken
                </label>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Einloggen</button>
            </div>
        </form>
    </div>
</div>
