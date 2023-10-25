<?php
require "main.php";

$GET = $_GET;

function get_map($type, $format){
    $query = "SELECT entry FROM rspamd_map WHERE map_type = :map_type";
    $stmtGet = db()->prepare($query);
    $stmtGet->bindValue(':map_type',$type,PDO::PARAM_STR);
    $stmtGet->execute();
    $res = $stmtGet->fetchAll(PDO::FETCH_COLUMN);
    //print_r($res);
    $filtered = [];
    $filtersRegex = [
        'ip' => '/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}(\/[0-9]{1,2})?$/',
        'domain' => '/^(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/',
        'email' => '/@(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/'
    ];
    foreach ($res as $entry ){
        if(preg_match($filtersRegex[$format], $entry))
        {
            $filtered[] = $entry;
        }
    }
    return $filtered;
}


if(isset($GET['type']) && isset($GET['format'])) {
    if($GET['type'] == 'blacklist') $type = 'blacklist';
    elseif ($GET['type'] == 'whitelist') $type = 'whitelist';
    else die();
    if (!in_array($GET['format'],['ip','domain','email'])) die("No Match");
    else {
        foreach (get_map($type,$GET['format']) as $entry) {
            echo($entry);
	    print_r("\n");
        }
    }
}
else{
    echo "Invalid";
}
