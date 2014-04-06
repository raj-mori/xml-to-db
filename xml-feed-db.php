<?php


/**
 * Parse feed in add values into db
 * 
 * @author Raj Mori <raj.mori90@gmail.com>
 * @version 1.0
 * @package feed_to_db
 * @since April 1,2014
 * 
 */


// set time limit to 0 for larger feed
set_time_limit(0);

// track time
$time_start = microtime();


// general constantnt declaration
define('DB_HOST', 'localhost');
define('DB_UNAME', 'gps');
define('DB_PASSWORD', 'gps123');
define('DB_NAME', 'gps');
define('FEED_URL', 'https://valledmg.s3.amazonaws.com/moz/xrs_positions.xml');



/**
 *  Class to handle errors in flexible way
 *  Right now, it dies to console.
 */
class flexi_error {

    public static function _report($msg) {
        die($msg);
    }

}

/**
 * Small DB Class with single-ton pattern
 */

class db {

    public static $db;
    public $_link;

    public function __construct() {
        $this->_link = mysql_connect(DB_HOST, DB_UNAME, DB_PASSWORD) or flexi_error::_report('ERROR:L1:' . mysql_error());
        mysql_select_db(DB_NAME) or flexi_error::_report('ERROR:L1:' . mysql_error());
        mysql_query("SET NAMES 'utf8'"); // handle utf8 streams
    }

    public static function __d() {

        if (!isset($db)) {
            self::$db = new db();
        }
        return self::$db;
    }

    public function query($query) {
        if ($res = mysql_query($query))
            return $res;
        else {
            flexi_error::_report('ERROR:L5:Unable to load data into db' . mysql_error() . "- " . $query );
        }
    }

}

/**
 *  Utility function to get the feed from amazon
 * 
 */

function _gu($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0');
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}


if (!$_SESSION['string'] || 1) { // for localhost testing, bypass ping to amazon each time
    $feed_data = _gu(FEED_URL);
    $_SESSION['string'] = $feed_data;
}

// Flexible error reporting
if (!$feed_data) {
    flexi_error::_report('ERROR:L2:Unable to load feed');
}


// use xpath
$xml = simplexml_load_string($_SESSION['string']);
$each_row = $xml->xpath("//root/row");


// Flexible error reporting
if (empty($each_row)) {
    flexi_error::_report('ERROR:L3:Malformed feed recevied');
}


// Get through nodes and prepare data structure
$values = array();
$count = count($each_row);
foreach ($each_row as $rw) {
    $each_val = array();
    $each_val[] = ""; // auto-increment
    $each_val['position'] = mysql_real_escape_string($rw->position_id);
    $each_val['unit_id'] = mysql_real_escape_string($rw->unit_id);
    $each_val['date'] = str_replace("T", " ", mysql_real_escape_string($rw->datetime));
    $each_val['altitude'] = mysql_real_escape_string($rw->altitude);
    $each_val['speed'] = mysql_real_escape_string($rw->speed);
    $each_val['direction'] = mysql_real_escape_string($rw->direction);
    $each_val['latitude'] = mysql_real_escape_string($rw->latitude);
    $each_val['longitude'] = mysql_real_escape_string($rw->longitude);
    $each_val['position_text'] = mysql_real_escape_string($rw->position_text);

    $values[] = '("' . implode('","', $each_val) . '")';
}

// Fire DB Query
if (!empty($values)) {
    $sql_values = implode(", ", $values);
    $query = " INSERT INTO  location values {$sql_values} ";
    $db = db::__d();
    $db->query($query);
} else {
    flexi_error::_report("ERROR:L4:Unable to retrieve data from feed");
}

// Finally, show success message
$time_end = microtime();
$total_time = $time_end - $time_start;
flexi_error::_report("SUCCESS:L0:Data Imported: Count: {$count} - Total Time: {$total_time} microseconds");

?>