<?php
/**
 * Created by PhpStorm.
 * User: dmitr
 * Date: 1/2/2018
 * Time: 7:18 PM
 */

require_once ("./helper_functions.php");
function db()
{
	$db['c'] = 'mysql:host=mariadb;dbname=vmail';
	$db['u'] = 'mailadmin';
	$db['p'] = '<secret>';
	try
	{
		$conn = new PDO($db['c'], $db['u'], $db['p']);

		return $conn;
	}
	catch (PDOException $e)
	{
		exit ($e->getMessage());
	}
}



/**
 * Get either a Gravatar URL or complete image tag for a specified email address.
 *
 * @param string $email The email address
 * @param string $s     Size in pixels, defaults to 80px [ 1 - 2048 ]
 * @param string $d     Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
 * @param string $r     Maximum rating (inclusive) [ g | pg | r | x ]
 * @param bool   $img   True to return a complete IMG tag False for just the URL
 * @param array  $atts  Optional, additional key/value attributes to include in the IMG tag
 * @return String containing either just a URL or a complete image tag
 * @source https://gravatar.com/site/implement/images/php/
 */
function get_gravatar($email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array())
{
	$url = 'https://www.gravatar.com/avatar/';
	$url .= md5(strtolower(trim($email)));
	$url .= "?s=$s&d=$d&r=$r";
	if ($img)
	{
		$url = '<img src="' . $url . '"';
		foreach ($atts as $key => $val)
			$url .= ' ' . $key . '="' . $val . '"';
		$url .= ' />';
	}

	return $url;
}

function refreshSessionData()
{
	if(!isset($_SESSION['username'])) return false;
	$stCheck = db()->prepare("SELECT * FROM accounts WHERE concat( username, '@', domain) = :mail LIMIT 1");
	$stCheck->bindValue(':mail', $_SESSION['username'], PDO::PARAM_STR);
	$stCheck->execute();
	$udata = $stCheck->fetch();

$_SESSION['user_level']  = $udata['user_level'];
$_SESSION['username']   = $udata['username'].'@'.$udata['domain'];
$_SESSION['name']        = $udata['name'];
$_SESSION['family_name'] = $udata['family_name'];
$_SESSION['auth']        = true;
$_SESSION['domain']      = $udata['domain'];
//$_SESSION['avatar']      = get_gravatar($udata['email']);
if ($udata['user_level'] >= 2)
{
	$_SESSION['normie'] = true;

}

if ($udata['user_level'] == 0)
{
	$user_title = 'Server Admin';
}
else if ($udata['user_level'] == 1)
{
	$user_title = 'Operator';
}
$_SESSION['user_title'] = $user_title;
return true;
}
/**
 * @param $email
 * @param $password
 */
function login($email, $password)
{
	try
	{
		$stPwHash = db()->prepare("SELECT password FROM accounts WHERE concat( username, '@', domain) = :mail");
		$stPwHash->bindValue(':mail', $email, PDO::PARAM_STR);
		$stPwHash->execute();
		$dbHashedPw = $stPwHash->fetch(PDO::FETCH_ASSOC);
        $pwHash = preg_replace('/^\{ARGON2I}/','',$dbHashedPw['password']);

		if (password_verify($password,$pwHash))
		{
			$stCheck = db()->prepare("SELECT * FROM accounts WHERE concat( username, '@', domain) = :mail LIMIT 1");
			$stCheck->bindValue(':mail', $email, PDO::PARAM_STR);
			$stCheck->execute();
			$udata = $stCheck->fetch();
		}
		else return false;

		$_SESSION['user_level'] = $udata['user_level'];
		$_SESSION['username']   = $udata['username'].'@'.$udata['domain'];
		$_SESSION['name']   = $udata['name'];
		$_SESSION['family_name']   = $udata['family_name'];
		$_SESSION['auth']       = true;
		$_SESSION['domain']     = $udata['domain'];
		$_SESSION['avatar']     = get_gravatar($udata['username'].'@'.$udata['domain']);
		if ($udata['user_level'] > 2)
		{
			$_SESSION['normie'] = true;

		}

		if ($udata['user_level'] == 0)
		{
			$user_title = 'Server Admin';
		}
		else if ($udata['user_level'] == 1)
		{
			$user_title = 'Operator';
		}
		$_SESSION['user_title'] = $user_title;

		return true;

	}
	catch (PDOException $e)
	{
		die($e->getMessage());
	}

}



function redirect($url)
{
	return header('Location: ' . $url);
}



if (isset($_GET['logout']))
{
	header("Location: ./logout.php");
}
