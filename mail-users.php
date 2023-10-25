<?php
/**
 * Created by PhpStorm.
 * User: dpastian
 * Date: 12/29/17
 * Time: 3:15 PM
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

//Load Composer's autoloader
require 'vendor/autoload.php';

require_once "helper_functions.php";
class mailuser
{

    private $db;
    private $pgTitle;
    private $passwordAlphabet = 'abcdefghjklmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ#+!?$%&234567';
    private $passwordLength = 20;

    function __construct()
    {
        $this->pgTitle = 'Mail Users';
        $this->db['c'] = 'mysql:host=mariadb;dbname=vmail';
        $this->db['u'] = 'mailadmin';
        $this->db['p'] = 'iL12j10dqBLC2mGEDx6z';
    }


    function getPgTitle()
    {
        return $this->pgTitle;
    }

    function run()
    {
        if (!isset($_GET['m']) || empty($_GET['m'])) {
            $mode = 'su';
        } elseif ($_GET['m'] == 'create') {
            $mode = 'create';
        } elseif ($_GET['m'] == 'su') {
            $mode = 'su';
        } elseif ($_GET['m'] == 'edit') {
            $mode = 'edit';
        } elseif ($_GET['m'] == 'aliases') {
            $mode = 'aliases';
        } elseif ($_GET['m'] == 'rspamd') {
            $mode = 'rspamd';
        } else {
            die('Unconditional');
        }
        if (!isset($_GET['act'])) {
            $action = 'search';
        } elseif ($_GET['act'] == 'edit') {
            $action = 'edit';
        } elseif ($_GET['act'] == 'add') {
            $action = 'add';
        } elseif ($_GET['act'] == 'delete') {
            $action = 'delete';
        } else {
            die('Unconditional');
        }
        if (!$_SESSION['normie']) {
            $tpl = file_get_contents("./templates/mail-user-{$mode}.tpl");
        } else {
            $tpl = '</div>
</div>

<div class="container flex-centered m-2">
<div>
    [CONTENT]';
        }
        $rc = new PDO($this->db['c'], $this->db['u'], $this->db['p']);
        if (!$rc) {
            return str_replace('[STATUS]', '<span class="err">Fehler: Keine Verbindung zur Datenbank<span>', $tpl);
        }

        $content = '';
        $option = '';

        $stSearchDomains = $rc->prepare('SELECT domain FROM domains');


        if (!$stSearchDomains->execute()) {
            die("Fehler: Datenbankabfrage (Suche nach Domains) fehlgeschlagen\n");
        }
        $domains = $stSearchDomains->fetchColumn();
        if(is_string($domains)){
            $domains = [$domains];
        }

        //var_dump($_SESSION['user_level']);

        if (!isset($_SESSION['normie']) || $_SESSION['normie'] == false) {
            if ($_SESSION['user_level'] == '0') {
                $option_arr = $domains;
            } else if ($_SESSION['user_level'] == '1') {
                $option_arr = array($_SESSION['domain']);
            }
            foreach ($option_arr as $opt) {
                $option .= '<option value="' . $opt . '"';
                if ($opt == $_SESSION['domain']) {
                    $option .= 'selected>' . $opt . '</option>';
                } else {
                    $option .= '>' . $opt . '</option>' . "\n";
                }
            }
        }


        $rc2 = 1;#new PDO($this->db2['c'], $this->db2['u'], $this->db2['p']);
//if(!$rc2) { return str_replace('[STATUS]', '<span class="err">Fehler: Keine Verbindung zur Datenbank<span>', $tpl); }


# Benutzer anlegen
        if ($mode == 'create' && isset($_POST['username']) && !empty($_POST['username'])) {
            $username = $_POST['username'];
            $domain = $_POST['domain'];
            if (!isset($_POST['password']) || empty($_POST['password'])) {
                $newPassword = $this->pwGen();
            } else {
                $newPassword = $_POST['password'];
            }
            $name = $_POST['name'] ?? '';
            $fam = $_POST['family'] ?? '';


            if (!in_array($domain, $option_arr)) {
                die('Domain not supported.');
            }

            if (!filter_var($username . '@' . $domain, FILTER_VALIDATE_EMAIL)) {
                die('E-Mail Adresse ist nicht gültig.');
            }
            $rc->beginTransaction();

            $stSearch = $rc->prepare('SELECT * FROM accounts WHERE username = :u AND domain = :d');
            $stSearch->bindValue(':u', $username, PDO::PARAM_STR);
            $stSearch->bindValue(':d', $domain, PDO::PARAM_STR);

            $stSearchAlias = $rc->prepare('SELECT * FROM aliases WHERE source_username = :u AND source_domain = :d');
            $stSearchAlias->bindValue(':u', $username, PDO::PARAM_STR);
            $stSearchAlias->bindValue(':d', $domain, PDO::PARAM_STR);


            if (!$stSearch->execute() || !$stSearchAlias->execute()) {
                die("Fehler: Datenbankabfrage (Suche nach vorhandenem User) fehlgeschlagen\n");
            }
            if ($stSearch->rowCount() !== 0 || $stSearchAlias->rowCount() !== 0) {
                $content = '<span class="error">Der Benutzer ' . $username . '@' . $domain . ' existiert bereits</span>';
            } else {
                // generate a 16-character salt string
                if(!empty($_POST['contact_mail'])){
                    $this->sendNotificationMail($username . '@' . $domain,$newPassword,$_POST['contact_mail']);
                }

                $completeHashedPW = cleartextToArgon($newPassword);

                $stmt_user = $rc->prepare("INSERT INTO accounts (username, domain, password,name,family_name)
                        VALUES (:u, :d, :p_hash,:name,:fam)");

                $stmt_user->bindValue(':u', $username, PDO::PARAM_STR);
                $stmt_user->bindValue(':d', $domain, PDO::PARAM_STR);
                $stmt_user->bindValue(':p_hash', $completeHashedPW, PDO::PARAM_STR);
                $stmt_user->bindValue(':name', $name, PDO::PARAM_STR);
                $stmt_user->bindValue(':fam', $fam, PDO::PARAM_STR);
                //var_dump("Username:".$username."<br> Domain: ".$domain."<br> Complete Hashed PW: ".$completeHashedPW."<br> Salt: ".$salt."<br> Passwort: ".$newPassword);
                try {
                    $stmt_user_exec = $stmt_user->execute();
                } catch (PDOException $e) {
                    print_r($e);
                }

                //var_dump($stmt_user_exec);
                if (!$stmt_user_exec) {
                    die("Fehler: Datenbankabfrage (Credentials anlegen) fehlgeschlagen\n");
                }


                $rc->commit();
                $content .= '<h3>Benutzer angelegt</h3>';
                $content .= '<div class="row"><div class="c g2">
                        <table class="bordered">
                        <tbody>
                            <tr>
                                <th>E-Mail-Adresse:</th>
                                <td>' . $username . '@' . $domain . '</td>    
                            </tr>
                            <tr>
                                <th>Username:</th>
                                <td>' . $username . '</td>
                            </tr>
                            <tr>
                                <th>Realm:</th>
                                <td>' . $domain . '</td>
                            </tr>
                            <tr>
                                <th>Passwort:</th>
                                <td><span class="typewriter">' . $newPassword . '</span></td>
                            </tr>
                        </tbody></table></div></div>';
            }

        }

# Nach einem Benutzer suchen
        if (($mode == 'su' && isset($_GET['searchuser']) && !empty($_GET['searchuser'])) || $_SESSION['normie'] == true) {
            if ($_SESSION['normie'] == true) {
                $stRC = $rc->prepare("SELECT * FROM accounts WHERE concat(username,'@',domain) =:u ORDER BY domain");
                $stRC->bindValue(':u', $_SESSION['username'], PDO::PARAM_STR);
                if (!$stRC->execute()) {
                    die("Fehler: Datenbankabfrage (Check-Attribute (selektiv)) fehlgeschlagen\n");
                }
            } elseif ($_SESSION['user_level'] == '0') {
                if ($_GET['exact'] == 'true') {
                    $stRC = $rc->prepare("SELECT * FROM accounts WHERE concat(username,'@',domain) =:u ORDER BY domain");
                    $stRC->bindValue(':u', $_GET['searchuser'], PDO::PARAM_STR);
                    if (!$stRC->execute()) {
                        die("Fehler: Datenbankabfrage (Check-Attribute (selektiv)) fehlgeschlagen\n");
                    }
                } else {
                    $stRC = $rc->prepare("SELECT * FROM accounts WHERE concat(username,'@',domain) LIKE :u ORDER BY domain");
                    $stRC->bindValue(':u', '%' . $_GET['searchuser'] . '%', PDO::PARAM_STR);
                    if (!$stRC->execute()) {
                        die("Fehler: Datenbankabfrage (Check-Attribute (selektiv)) fehlgeschlagen\n");
                    }
                }
            } else {
                if ($_GET['exact'] == 'true') {
                    $stRC = $rc->prepare("SELECT * FROM accounts WHERE concat(username,'@',domain) =:u AND domain =:realm ORDER BY domain");
                    $stRC->bindValue(':u', $_GET['searchuser'], PDO::PARAM_STR);
                    $stRC->bindValue(':realm', $_SESSION['domain'], PDO::PARAM_STR);
                    if (!$stRC->execute()) {
                        die("Fehler: Datenbankabfrage (Check-Attribute (selektiv)) fehlgeschlagen\n");
                    }
                } else {
                    $stRC = $rc->prepare("SELECT * FROM accounts WHERE concat(username,'@',domain) LIKE :u AND domain=:realm ORDER BY domain");
                    $stRC->bindValue(':u', '%' . $_GET['searchuser'] . '%', PDO::PARAM_STR);
                    $stRC->bindValue(':realm', $_SESSION['domain'], PDO::PARAM_STR);
                    if (!$stRC->execute()) {
                        die("Fehler: Datenbankabfrage (Check-Attribute (selektiv)) fehlgeschlagen\n");
                    }
                }
            }


            // User genau gefunden: Details anzeigen
            if ($stRC->rowCount() === 1) {

                $udata = $stRC->fetch();
                $udata['email'] = $udata['username'] .'@'.$udata['domain'];
                // Zuerst moegliche Aenderungen durchfuehren. Die neuen Einstellungen werden dann ohne Refreh angezeigt

                // 1. Passwort aendern, wenn angefordert
                if (isset($_GET['reqnewpw']) && $_GET['reqnewpw'] == 'true') {
                    $newPw = $this->pwGen();
                    // generate a 16-character salt string

                    $completeHashedPW = cleartextToArgon($newPw);

                    $stUp = $rc->prepare('UPDATE accounts SET password = :newPw_hash WHERE username = :u AND domain = :d LIMIT 1');
                    $stUp->bindValue(':newPw_hash', $completeHashedPW, PDO::PARAM_STR);

                    $stUp->bindValue(':u', $udata['username'], PDO::PARAM_STR);
                    $stUp->bindValue(':d', $udata['domain'], PDO::PARAM_STR);

                    if (!$stUp->execute()) {
                        die("Fehler: Datenbankabfrage (Passwort-Update) fehlgeschlagen\n");
                    }
                    $content .= <<<EOF
<div class="card px-2 py-2">
  <h3 class="card-header">Passwort kann nur jetzt angezeigt werden.</h3>

  <details class="card-body">
    <summary>Neues Passwort Anzeigen</summary>
    <div>
      <p>
        <b>$newPw</b>
      </p>
    </div>
  </details>

</div>
<p></p>
EOF;
                }

                // 2. Aktivieren oder Deaktivieren
                if (isset($_GET['uact'])) {
                    if ($_GET['uact'] == 'active') {
                        $stSt = $rc->prepare("UPDATE accounts SET enabled = 1 WHERE username = :u AND domain = :d LIMIT 1");
                        $stSt->bindValue(':u', $udata['username'], PDO::PARAM_STR);
                        $stSt->bindValue(':d', $udata['domain'], PDO::PARAM_STR);
                        if (!$stSt->execute()) {
                            die("Fehler: Datenbankabfrage (Aktivieren) fehlgeschlagen\n");
                        }

                        header('Location: ' . $_SERVER['PHP_SELF'] . '?p=email-account&m=su&exact=true&searchuser=' . $udata['email']);
                    } elseif ($_GET['uact'] == 'inactive') {
                        $stSt = $rc->prepare("UPDATE accounts SET enabled = 0 WHERE username = :u AND domain = :d LIMIT 1");
                        $stSt->bindValue(':u', $udata['username'], PDO::PARAM_STR);
                        $stSt->bindValue(':d', $udata['domain'], PDO::PARAM_STR);
                        if (!$stSt->execute()) {
                            die("Fehler: Datenbankabfrage (Voll Sperren) fehlgeschlagen\n");
                        }
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?p=email-account&m=su&exact=true&searchuser=' . $udata['email']);
                    } else {
                        die('Undefinierte Aktion');
                    }
                }
                // 3. Passwort Setzen
                if (isset($_GET['setpw']) && $_GET['setpw'] == 'true') {
                    $content .= '<form method="post" action="index.php?p=email-account&m=su&setpw=submit&searchuser=' . $udata['email'] . '"><input name="newPw" type="password" class="form-input" placeholder="New Password">';
                    $content .= '<input name="newPwCheck" type="password" class="form-input" placeholder="Repeat Password">';
                    $content .= '<input type="submit" name="go" value="Submit">';
                    $content .= '</form>';
                } elseif (isset($_GET['setpw']) && $_GET['setpw'] == 'submit') {
                    if (isset($_POST['newPw']) && isset($_POST['newPwCheck']) && !empty($_POST['newPw']) && !empty($_POST['newPwCheck'])) {
                        $newPw = $_POST['newPw'];
                        $pwCheck = $_POST['newPwCheck'];
                        if ($newPw != $pwCheck) {
                            $content = '<span>Fehler: Passw&ouml;rter stimmen nicht überein.</span><p><a href="index.php">Verstanden</a></p>';
                        } else {

                            // generate a 16-character salt string

                            $completeHashedPW = cleartextToArgon($newPw);
                            $stUpPw = $rc->prepare('UPDATE accounts SET password = :newPw_hash WHERE username = :u AND domain = :d LIMIT 1');
                            $stUpPw->bindValue(':newPw_hash', $completeHashedPW, PDO::PARAM_STR);

                            $stUpPw->bindValue(':u', $udata['username'], PDO::PARAM_STR);
                            $stUpPw->bindValue(':d', $udata['domain'], PDO::PARAM_STR);
                            $stUpPw->execute();
                            $content = <<<EOF
<div>
<h2>Passwort geändert</h2>
<p>Das passwort wurde erfolgreich geändert</p>
</div>
EOF;

                        }
                    } else {
                        $content = '<span>Fehler: Passwort Felder dürfen nicht leer sein.</span><p><a href="index.php">Verstanden</a></p>';
                    }

                }
                if (isset ($_GET['remove_user'])) {
                    if ($_GET['remove_user'] == 'true') {

                        $stUserDel = $rc->prepare("DELETE FROM accounts WHERE username = :u AND domain = :d");
                        $stUserDel->bindValue(':u', $udata['username'], PDO::PARAM_STR);
                        $stUserDel->bindValue(':d', $udata['domain'], PDO::PARAM_STR);
                        if (!$stUserDel->execute()) {
                            die('Fehler beim entfernen aus der Datenbank.');
                        } else {
                            $content = 'Der Benutzer \'' . $udata['username'] .'@'.$udata['domain'] . '\' wurde erfolgreich entfernt.';
                            $content .= '<a href="index.php?m=su&exact=true&searchuser=' . $udata['username'] .'@'.$udata['domain'] . '" class="btn btn-primary">Weiter</a>';
                            exit($content);
                        }
                    } else {
                        $content = '<span>Benutzer <b><u>' . $udata['username'] .'@'.$udata['domain'] . '</u></b> wirklich l&ouml;schen?</span>';
                        $content .= '<div class=""><a href="index.php?m=su&exact=true&searchuser=' . $udata['username'] .'@'.$udata['domain'] . '&m=su&remove_user=true" class=" btn btn-primary" >Ja, L&ouml;schen</a><a class="btn btn-secondary input-group-btn" href="index.php?m=su&exact=true&searchuser=' . $udata['email'] . '">Nicht Löschen</a></form></div>';
                        exit($content);

                    }
                }

                if (isset($_GET['edit'])) {
                    if ($_GET['edit'] == 'edited' && (!empty($_POST['name']) || !empty
                            ($_POST['family_name']))) {
                        //print_r($_POST);
                        $stEdit = $rc->prepare("UPDATE accounts SET name = :name,family_name= :fam WHERE WHERE username = :u AND domain = :d");
                        $stEdit->bindValue(':name', $_POST['name'], PDO::PARAM_STR);
                        $stEdit->bindValue(':fam', $_POST['family_name'], PDO::PARAM_STR);
                        $stEdit->bindValue(':u', $udata['username'], PDO::PARAM_STR);
                        $stEdit->bindValue(':d', $udata['domain'], PDO::PARAM_STR);

                        if (!$stEdit->execute()) die("Fehler: DB-Error, could not update!");
                        $content .= '<a class="btn btn-primary" href="index.php?p=email-account&m=su&exact=true&searchuser=' . $udata['email'] . '">Refresh</a>';
                    } else {
                        error_log(print_r(['user data $udata'=>$udata],true));
                        // Check-Items
                        $stUC = $rc->prepare("SELECT * FROM accounts WHERE username = :u AND domain = :d LIMIT 1");
                        $stUC->bindValue(':u', $udata['username'], PDO::PARAM_STR);
                        $stUC->bindValue(':d', $udata['domain'], PDO::PARAM_STR);
                        if (!$stUC->execute()) {
                            die("Fehler: Datenbankabfrage (Check-Attribute) fehlgeschlagen\n");
                        }

                        $content .= '<h3>Benutzer Einstellungen Bearbeiten</h3>';
                        $content .= '<a class="btn btn-secondary bg-dark text-warning" href="index.php?p=email-account&m=su&exact=true&searchuser=' . $udata['email'] . '">Abbrechen</a>';
                        $content .= '<form action="index.php?p=email-account&m=su&exact=true&searchuser=' . $udata['email'] . '&edit=edited" method="post">';
                        $content .= '<table class="zebra"><tbody>';
                        $udata2 = $stUC->fetchAll(PDO::FETCH_ASSOC)[0];
                        error_log(print_r(['user data fresh from db $udata2'=>$udata2],true));
                        foreach ($udata2 as $key_s => $value_s) {
                            switch ($key_s) {
                                case 'name':
                                    $key2 = 'Name';
                                    $value2 = $value_s;
                                    $type = 'text';
                                    break;
                                case 'family_name':
                                    $key2 = 'Family Name';
                                    $value2 = $value_s;
                                    $type = 'text';
                                    break;

                                default:
                                    unset ($key2);
                                    break;
                            }
                            if (isset($key2)) {
                                $content .= '<tr><td>' . $key2 . ':</td><td><input name="' . $key_s . '" value="' . $value_s . '" type="' . $type . '"></td></tr>';
                            }
                        }
                        $content .= '</tbody></table>';
                        $content .= '<input class="btn btn-primary" type="submit" value="Save" name="Save">';
                        $content .= '</form>';
                    }
                } else {
                    $content .= '<h3>Benutzer gefunden</h3>';
                    $content .= '<div class="row">';
                    $content .= '<div class="c g2">';
                    $content .= '<table class="bordered"><tbody>';
                    $content .= '<tr><th>E-Mail:</th><td><b>' . $udata['email'] . '</b></td></tr>';
                    $content .= '<tr><th>Name:</th><td>' . $udata['name'] . '</td></tr>';
                    $content .= '<tr><th>Family Name:</th><td>' . $udata['family_name'] . '</td></tr>';
                    $content .= '<tr><th>Status:</th><td>';
                    switch ($udata['enabled']) {
                        case 1:
                            $content .= '<span class="badge success">Aktiv</span>';
                            break;
                        case 0:
                            $content .= '<span class="badge danger">Voll Gesperrt</span>';
                            break;
                    }
                    $content .= '</td></tr>';
                    $content .= '</tbody></table>';
                    $content .= '</div>';
                    $content .= '<div class="c g2 pl-20">';
                    $content .= '<table class="clean"><tbody>';
                    $content .= '<tr><td>';

                    switch ($udata['enabled']) {
                        case 1:
                            $content .= '<a class="btn btn-secondary bg-success disabled" href="#">Aktivieren</a> ';
                            $content .= '<a class="btn btn-secondary bg-warning" href="/?p=email-account&m=su&uact=inactive&exact=true&searchuser=' . $udata['email'] . '">Voll sperren</a> ';
                            break;
                        case 0:
                            $content .= '<a class="btn btn-secondary bg-success" href="/?p=email-account&m=su&uact=active&exact=true&searchuser=' . $udata['email'] . '">Aktivieren</a> ';
                            $content .= '<a class="btn btn-secondary bg-warning disabled" href="#">Voll sperren</a> ';
                            $content .= '<a class="btn btn-secondary bg-error" href="/?p=email-account&m=su&remove_user&exact=true&searchuser=' . $udata['email'] . '">L&ouml;schen</a> ';

                            break;

                        default:
                            break;
                    }
                    $content .= '</td></tr>';
                    $content .= '<tr><td>';
                    $content .= '<a class="btn btn-secondary bg-secondary ' . ($udata['enabled'] == 0 ? ' disabled' : '') . '" href="index.php?p=email-account&m=su&exact=true&reqnewpw=true&searchuser=' . $udata['email'] . '">Passwort Generieren</a> ';
                    $content .= '<a class="btn btn-secondary bg-dark text-warning ' . ($udata['enabled'] == 0 ? ' disabled' : '') . '" href="index.php?p=email-account&m=su&exact=true&searchuser=' . $udata['email'] . '&setpw=true">Neues Passwort Setzen</a> ';
                    $content .= '</td></tr>';
                    $content .= '<tr><td>';
                    $content .= '</td></tr>';
                    $content .= '</tbody></table>';
                    $content .= '</div>';
                    $content .= '</div>';

                    // Check-Items
                    $stUC = $rc->prepare("SELECT * FROM accounts WHERE username = :u AND domain = :d LIMIT 1");
                    $stUC->bindValue(':u', $udata['username'], PDO::PARAM_STR);
                    $stUC->bindValue(':d', $udata['domain'], PDO::PARAM_STR);
                    if (!$stUC->execute()) {
                        die("Fehler: Datenbankabfrage (Check-Attribute) fehlgeschlagen\n");
                    }

                    $content .= '<h3>Benutzer Einstellungen</h3>';
                    $content .= '<a class="btn btn-secondary bg-dark text-warning" href="index.php?p=email-account&m=su&exact=true&searchuser=' . $udata['email'] . '&edit=true">Bearbeiten</a>';
                    $content .= '<table class="zebra"><tbody>';
                    $udata2 = $stUC->fetchAll(PDO::FETCH_ASSOC)[0];
                    foreach ($udata2 as $key_s => $value_s) {
                        switch ($key_s) {
                            case 'user_level':
                                $key2 = 'OP Level';
                                $value2 = $value_s;
                                break;
                            case 'enabled':
                                $key2 = 'Aktiv';
                                $value2 = $value_s?'True':'False';
                                break;
                            case 'date_created':
                                $key2 = 'Erstellt am';
                                $value2 = $value_s;
                                break;
                            case 'last_modified':
                                $key2 = 'Zuletzt Bearbeitet';
                                $value2 = $value_s;
                                break;
                            default:
                                unset ($key2);
                                break;
                        }
                        if (isset($key2)) {
                            $content .= "<tr><td>$key2:</td><td>$value2</td></tr>";
                        }


                    }

                    $content .= '</tbody></table>';
                    $content .= '<div>
    <h3>Gefundene Aliase zu diesem Account:</h3>
</div>
<hr>';

                    $stAliasList = $rc->prepare("SELECT id,source_username,source_domain,destination_username,destination_domain FROM aliases WHERE concat(destination_username, '@', destination_domain) = :email ORDER BY concat(source_username,'@',source_domain)");
                    $stAliasList->bindValue(':email', $udata['email'], PDO::PARAM_STR);
                    $stAliasList->execute();
                    $results = $stAliasList->fetchAll(PDO::FETCH_ASSOC);
                    $content .= '<table class="table table-striped"><tr><thead><th>Aktion</th><th>Alias</th><th>Empfänger Adresse</th></thead></tr>';
                    foreach ($results as $entry) {
                        $id = $entry['id'];
                        $alias = $entry['source_username'].'@'.$entry['source_domain'];
                        $recipient = $entry['destination_username'].'@'.$entry['destination_domain'];
                        $content .= '<tr><td><a href="index.php?m=aliases&act=delete&id=' . $id . '">Entfernen</a><a href="index.php?m=aliases&act=edit&id=' . $id . '" class="btn btn-link btn-action btn-lg"><i class="icon icon-edit"></i></a></td><td>' . $alias . '</td><td>' . $recipient . '</td></tr>';
                    }
                    $content .= '</table>';
                }


            } elseif ($stRC->rowCount() === 0) {
                $content = 'Keine Ergebnisse bei diesem Suchbegriff';
            } else {
                $content .= '<h3>Ergebnisse</h3><ul>';
                foreach ($stRC->fetchAll(PDO::FETCH_ASSOC) as $ds) {
                    $udata['email'] = $ds['username'].'@'.$ds['domain'];
                    if ($_GET['search_type'] == 2) {
                        $content .= '<li><a href="?p=email-account&m=su&searchuser=' . $udata['email'] . '&exact=true&search_type=2">' . $udata['email'] . '</a></li>';

                    } else {
                        $content .= '<li><a href="?p=email-account&m=su&searchuser=' . $udata['email'] . '&exact=true">' . $udata['email'] . '</a></li>';

                    }
                }
                $content .= '</ul>';
                if ($stRC->rowCount() == 10) {
                    $content .= '<p/>Es k&ouml;nnen mehr als zehn Ergebnisse vorliegen.';
                }
            }

        }
        if (isset($_GET['searchuser']) && empty($_GET['searchuser'])) {
            $content .= '<span>Bitte Suchbegriff angeben.</span>';

        }


        if ($_SESSION['user_level'] == '0') {
            if ($mode == 'aliases') {
                $content .= '<form method="post" action="index.php?m=aliases&act=add"><div class="input-group col-12">';
                $content .= '<input class="btn btn-primary input-group-btn" type="submit" value="add" name="add"><input class="form-input col-5" placeholder="alias@domain.tld" type="text" name="alias"><input class="form-input col-5" type="text" name="recipient" placeholder="recipient@domain.tld" required>';
                $content .= '</div></form>';

                if ($mode == 'aliases' && isset($_POST['search']) && !empty($_POST['search']) || isset($_GET['search'])) {
                    if (isset($_POST['search'])) {
                        $search = $_POST['search'];
                    } else {
                        $search = $_GET['search'];
                    }
                    $_SESSION['search'] = $search;
                    $edit_id = $_GET['id'];
                    $joinedQuery = <<<EOF
SELECT a.id, 
       a.source_username, 
       a.source_domain, 
       a.destination_username, 
       a.destination_domain, 
       (IF(d.domain IS NULL, 1, 0)) as is_external
FROM aliases a
LEFT JOIN domains d ON a.destination_domain = d.domain
WHERE concat(a.destination_username, '@', a.destination_domain) LIKE :search
ORDER BY concat(a.source_username, '@', a.source_domain);
EOF;

                    $stAliasList = $rc->prepare($joinedQuery);
                    $stAliasList->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
                    $stAliasList->execute();
                    $results = $stAliasList->fetchAll(PDO::FETCH_ASSOC);
                    $content .= '<table class="table table-striped"><tr><thead><th>Aktion</th><th>Alias</th><th>Empfänger Adresse</th></thead></tr>';
                    foreach ($results as $entry) {
                        $id = $entry['id'];
                        $alias = $entry['source_username'].'@'.$entry['source_domain'];
                        $recipient = $entry['destination_username'].'@'.$entry['destination_domain'];
                        $location = $entry['is_external']?'Extern': 'Lokal';

                        if ($action == 'edit' && $id == $edit_id) {
                            $content .= '<tr><form method="post" action="index.php?m=aliases&act=edit&id=' . $id . '"><td><input type="submit" value="edit" name="edit"></td><td><input type="text" name="alias" value="' . $alias . '"></td><td><input type="text" name="recipient" value="' . $recipient . '"></td></form></tr>';
                        } elseif ($action == 'search') {
                            $content .= '<tr><td><a href="index.php?m=aliases&act=delete&id=' . $id . '">Entfernen</a><a href="index.php?m=aliases&act=edit&search=' . $search . '&id=' . $id . '" class="btn btn-link btn-action btn-lg"><i class="icon icon-edit"></i></a></td><td>' . $alias . '</td><td>' . $recipient . '</td><td><i class="icon icon-location'.($entry['is_external']?' text-warning':' text-success').'"></i><span>'.$location.'</span></td></tr>';
                        }


                    }
                    $content .= '</table>';

                } elseif ($mode == 'aliases' && $action == 'edit' && isset($_GET['id']) && isset($_POST['alias']) && isset($_POST['recipient'])) {
                    $id = $_GET['id'];
                    $alias = $_POST['alias'];
                    $recipient = $_POST['recipient'];

                    $alias_split = explode('@',$alias);
                    $recipient_split = explode('@',$recipient);

                    $stAliasUpdate = $rc->prepare("UPDATE aliases SET source_username=:sou, source_domain = :sod, destination_username=:deu,destination_domain = :ded WHERE Id=:id");
                    $stAliasUpdate->bindValue(':sou', $alias_split[0], PDO::PARAM_STR);
                    $stAliasUpdate->bindValue(':sod', $alias_split[1], PDO::PARAM_STR);
                    $stAliasUpdate->bindValue(':deu', $recipient_split[0], PDO::PARAM_STR);
                    $stAliasUpdate->bindValue(':ded', $recipient_split[1], PDO::PARAM_STR);
                    $stAliasUpdate->bindValue(':id', $id, PDO::PARAM_STR);
                    $stAliasUpdate->execute();

                    redirect('index.php?m=aliases&search=' . $_SESSION['search']);
                } elseif ($mode == 'aliases' && $action == 'add' && isset($_POST['alias']) && isset($_POST['recipient'])) {
                    $alias = $_POST['alias'];
                    $recipient = $_POST['recipient'];

                    $alias_split = explode('@',$alias);
                    $recipient_split = explode('@',$recipient);
                    $stAliasInsert = $rc->prepare("INSERT INTO aliases (source_username,source_domain,destination_username,destination_domain) VALUES (:sou,:sod,:deu,:ded)");
                    $stAliasInsert->bindValue(':sou', $alias_split[0], PDO::PARAM_STR);
                    $stAliasInsert->bindValue(':sod', $alias_split[1], PDO::PARAM_STR);
                    $stAliasInsert->bindValue(':deu', $recipient_split[0], PDO::PARAM_STR);
                    $stAliasInsert->bindValue(':ded', $recipient_split[1], PDO::PARAM_STR);
                    $stAliasInsert->execute();

                }
                if ($mode == 'aliases' && $action == 'delete' && isset($_GET['id'])) {
                    $id = $_GET['id'];
                    if ($_GET['sure'] == true) {
                        $stAliasDel = $rc->prepare("DELETE FROM aliases WHERE id = :id");
                        $stAliasDel->bindValue(':id', $id, PDO::PARAM_STR);
                        if (!$stAliasDel->execute()) {
                            die('Fehler beim entfernen aus der Datenbank.');
                        } else {
                            $content .= 'Der Eintrag wurde erfolgreich entfernt.';
                        }

                    } else {
                        $content .= '<div><div><span>Eintrag l&ouml;schen</span></div><div><a href="index.php?m=aliases" class="btn btn-secondary">Abbrechen</a><a class="btn btn-primary" href="index.php?m=aliases&act=delete&id=' . $id . '&sure=true">L&ouml;schen</a></div></div>';
                    }
                }
            }
            if ($mode == 'rspamd') {
                $content .= '<form method="post" action="index.php?m=rspamd&act=add"><div class="input-group col-12">';
                $content .= '<input class="btn btn-primary input-group-btn" type="submit" value="add" name="add">' . '<input class="form-input col-5" placeholder="domain.tld" type="text" name="entry">' . '<input class="form-input col-5" placeholder="whitelist" type="text" name="map_type">';
                $content .= '</div></form>';

                if ($mode == 'rspamd' && isset($_POST['search']) && !empty($_POST['search']) || isset($_GET['search'])) {
                    if (isset($_POST['search'])) {
                        $search = $_POST['search'];
                    } else {
                        $search = $_GET['search'];
                    }
                    $_SESSION['search'] = $search;
                    $edit_id = $_GET['id'];
                    $stAliasList = $rc->prepare("SELECT Id AS id,entry,map_type FROM rspamd_map WHERE entry LIKE :search ORDER BY entry");
                    $stAliasList->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
                    $stAliasList->execute();
                    $results = $stAliasList->fetchAll(PDO::FETCH_ASSOC);
                    $content .= '<table class="table table-striped"><tr><thead><th>Aktion</th><th>Entry</th><th>Map Type</th></thead></tr>';
                    foreach ($results as $res) {
                        $id = $res['id'];
                        $entry = $res['entry'];
                        $map_type = $res['map_type'];
                        if ($action == 'edit' && $id == $edit_id) {
                            $content .= '<tr><form method="post" action="index.php?m=rspamd&act=edit&id=' . $id . '"><td><input type="submit" value="edit" name="edit"></td><td><input type="text" name="entry" value="' . $entry . '"></td><td><input type="text" name="map_type" value="' . $map_type . '"></td></form></tr>';
                        } elseif ($action == 'search') {
                            $content .= '<tr><td><a href="index.php?m=rspamd&act=delete&id=' . $id . '">Entfernen</a><a href="index.php?m=rspamd&act=edit&search=' . $search . '&id=' . $id . '" class="btn btn-link btn-action btn-lg"><i class="icon icon-edit"></i></a></td><td>' . $entry . '</td><td>' . $map_type . '</td></tr>';
                        }


                    }
                    $content .= '</table>';

                } elseif ($mode == 'rspamd' && $action == 'edit' && isset($_GET['id']) && isset($_POST['entry']) && isset($_POST['map_type'])) {
                    $id = $_GET['id'];
                    $entry = $_POST['entry'];
                    $map_type = $_POST['map_type'];


                    $stAliasUpdate = $rc->prepare("UPDATE aliases SET alias=:alias, virtual_user_email=:recipient WHERE Id=:id");
                    $stAliasUpdate->bindValue(':alias', $alias, PDO::PARAM_STR);
                    $stAliasUpdate->bindValue(':recipient', $recipient, PDO::PARAM_STR);
                    $stAliasUpdate->bindValue(':id', $id, PDO::PARAM_STR);
                    $stAliasUpdate->execute();

                    redirect('index.php?m=rspamd&search=' . $_SESSION['search']);
                } elseif ($mode == 'rspamd' && $action == 'add' && isset($_POST['entry']) && isset($_POST['map_type'])) {
                    $entry = $_POST['entry'];
                    $map_type = $_POST['map_type'];


                    $stAliasInsert = $rc->prepare("INSERT INTO rspamd_map (entry,map_type) VALUES (:entry, :map_type)");
                    $stAliasInsert->bindValue(':entry', $entry, PDO::PARAM_STR);
                    $stAliasInsert->bindValue(':map_type', $map_type, PDO::PARAM_STR);
                    $stAliasInsert->execute();

                }
                if ($mode == 'rspamd' && $action == 'delete' && isset($_GET['id'])) {
                    $id = $_GET['id'];
                    if ($_GET['sure'] == true) {
                        $stAliasDel = $rc->prepare("DELETE FROM rspamd_map WHERE Id = :id");
                        $stAliasDel->bindValue(':id', $id, PDO::PARAM_STR);
                        if (!$stAliasDel->execute()) {
                            die('Fehler beim entfernen aus der Datenbank.');
                        } else {
                            $content .= 'Der Eintrag wurde erfolgreich entfernt.';
                        }

                    } else {
                        $content .= '<div><div><span>Eintrag l&ouml;schen</span></div><div><a href="index.php?m=rspamd" class="btn btn-secondary">Abbrechen</a><a class="btn btn-primary" href="index.php?m=rspamd&act=delete&id=' . $id . '&sure=true">L&ouml;schen</a></div></div>';
                    }
                }
            }
        } elseif ($_SESSION['user_level'] > 0 && ($mode == 'aliases' || $mode == 'rspamd')) {
            $tpl = '<span>Unzureichende Berechtigungen.</span>';

        }

        return str_replace(array('[CONTENT]', '[OPTION]'), array($content, $option), $tpl);
    }


    function pwGen($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $count = mb_strlen($chars);

        for ($i = 0, $result = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }

        return $result;
    }

    function sendNotificationMail($mailUser, $password, $toAddress){


//Create an instance; passing `true` enables exceptions
        $mail = new PHPMailer(true);

        try {
            //Server settings
            //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host       = 'mail.planitprima.com';                     //Set the SMTP server to send through
            $mail->SMTPAuth   = false;                                   //Enable SMTP authentication
            $mail->Port       = 25;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom('system@planitprima.com', 'PlanitPrima Mail');
            $mail->addAddress($toAddress);     //Add a recipient
            $mail->addReplyTo('mail@planitprima.com', 'Information');


            //Content
            $mail->CharSet = 'UTF-8'; // Set the character set to UTF-8
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = 'Neue E-Mail Adresse | planitprima.com';
            $mail->Body    = <<<EOF

<div style="font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; margin: 20px;">
    <p style="margin-bottom: 10px;">Moin,</p>
    <p style="margin-bottom: 10px;">es wurde ein E-Mail Account f&uuml;r dich angelegt.</p>
    <p style="margin-bottom: 10px;">Hier die Zugangsdaten:</p>
    <table style="border-collapse: collapse; width: 50%; margin-bottom: 20px;">
    <tr style="border-bottom: 1px solid #ccc;">
        <td style="padding: 5px 10px;">E-Mail</td>
        <td style="padding: 5px 10px;">$mailUser</td>
    </tr>
    <tr>
        <td style="padding: 5px 10px;">Passwort</td>
        <td style="padding: 5px 10px;">$password</td>
    </tr>
</table>

<p style="margin-bottom: 10px;">Bitte melde dich beim <a href="https://mailadmin.planitprima.com" style="color: #0070C0; text-decoration: none;">Mailadmin</a> an und &auml;ndere dein Passwort!</p>
<p style="margin-bottom: 20px;"><a href="https://mailadmin.planitprima.com" style="color: #0070C0; text-decoration: none;">https://mailadmin.planitprima.com</a></p>
</div>

EOF;

            $mail->AltBody = <<<EOF
Moin,

es wurde ein E-Mail Account für dich angelegt.

Hier die Zugangsdaten:

    E-Mail: $mailUser
    Passwort: $password

Bitte melde dich beim Mailadmin an und ändere dein Passwort!
https://mailadmin.planitprima.com
EOF;


            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

    }

}

?>
