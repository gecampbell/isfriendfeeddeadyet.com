<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/html4/loose.dtd">
<?php
// $Id: index.php,v 1.7 2010/03/05 21:59:26 glen Exp $
// log file to track status and sizes
define('LOGFILE', '/var/log/isfriendfeeddeadyet.log');
// pages smaller than this are really unusual, and might represent a problem
define('SMALL_PAGE_LIMIT', 2048);
// our target URL
define('FRIENDFEED', 'http://friendfeed.com/');
// timeout value
define('MAX_TIMEOUT', 3000);
// set the default timezone
date_default_timezone_set( 'America/Chicago' );

// when does it die?
$death = strtotime('2015-04-09 12:00:00');
$when = $death - time(); // number of seconds left
$days_left = (int) ($when / (60*60*24));

// logmsg() save a logfile message
function logmsg($target, $status, $size, $extra)
{
	// the value "3" indicates to log to a file
	error_log(sprintf("%s %s HTTP status=%d size=%d info=%s\n",
				date('r'),
				$target,
				$status,
				$size,
				$extra),
		3, // log to file
		LOGFILE);
}

// MAIN PROCSSING BEGINS HERE

// $ch is the curl handle
$ch = curl_init();

// define the URL
$username = isset($_GET['user']) ? $_GET['user'] : '';
if ($username != '')
{
	$target = FRIENDFEED . $username;
	$title = "Is FriendFeed user $username dead yet?";
}
else
{
	$target = FRIENDFEED;
	$title = "Is FriendFeed dead yet?";
}
curl_setopt($ch, CURLOPT_URL, $target);
// return the header in the output
curl_setopt($ch, CURLOPT_HEADER, TRUE);
// timeout on error
#curl_setopt($ch, CURLOPT_TIMEOUT_MS, MAX_TIMEOUT);
curl_setopt($ch, CURLOPT_TIMEOUT, MAX_TIMEOUT/1000);
// return the page
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
// set a very special user agent
curl_setopt($ch, CURLOPT_USERAGENT, 'isfriendfeeddeadyet.com');

$phrases = [
    "Sadly, it is",
    "It has ceased to exist",
    "It has passed on",
    "It has kicked the bucket",
    "It has gone to meet its maker",
    "It has joined the bleeding choir eternal",
    "It is an ex-FriendFeed",
    "It rests in peace",
    "It has shuffled off this mortal coil",
    "It has bought the farm"
];


// $page is the result of the fetch
$page = curl_exec($ch);

// $status holds the returned status
// values are Yes, No, Maybe
$status = 'Who the hell knows?'; // default value
$extra = '';

// check the returned value
if (curl_errno($ch)) // there is an error
{
	$status = $phrases[rand(0,count($phrases)-1)];
	$extra = curl_error($ch);
}
else // no error, need to look at the HTTP status
{
	// split the page into the header and body
	list($header, $body) = split("\n", $page, 2);
	preg_match('/HTTP\/1.1\s*([0-9]+)/', $header, $matches);
	$http_status = $matches[1];
	$pagesize = strlen($body);

	// determine the status
	switch($http_status)
	{
	case 200: // everything is ok
        if ($days_left <= 0)
            $status = "Like, who knows?";
		else if ($pagesize > SMALL_PAGE_LIMIT) 
            $status = sprintf('in %d day%s', $days_left, ($days_left==1)?'':'s');
		else {
			$status = 'Perhaps';
			$extra = 'Main page is unusually small';
		}
		break;
    case 301:
    case 302:
        $status = $phrases[rand(0,count($phrases)-1)];
        break;
	case 404: // not found
        if ($days_left <= 0) {
		    $status = $phrases[rand(0,count($phrases)-1)];
        }
        else
            $status = 'Probably';
		if ($username != '')
			$extra = 'That user does not appear to exist right now';
		break;
	default:
        if ($days_left <= 0)
            $status = $phrases[rand(0,count($phrases)-1)];
        else
            $status = 'Probably';
		$extra = sprintf('HTTP status code is %d', $http_status);
	}
}
// info for page
printf("<!-- FriendFeed HTTP status is %d, size is %d, on %s -->\n", $http_status, $pagesize, date('r'));

// we're going to keep a log of results
logmsg($target, $http_status, $pagesize, $extra);

?>
<html>
<head>
<title><?php print htmlentities($title);?></title>
<meta name="description" content="A site to tell you if FriendFeed is dead yet"/>
<meta name="keywords" content="friendfeed,friend,single-service site,monitoring"/>
<meta name="refresh" content="600"/>
<style type="text/css">
body { background-color: white; color: black; text-align: center; padding: 0; font-family: helvetica, helvetica neue, arial, sans-serif; width: 100%; }
#footer { width: 100%; display: block; position: absolute; bottom: 0; padding:1em; font-size: smaller; text-align: center; }
#status { font-size: <?php if ($days_left <= 0) echo "48pt"; else echo "2in";?>; letter-spacing: -2pt; line-height: 1in; color: black; font-weight: bold; }
#extra { margin-top: 2em; font-style: italic; color: silver; }
a { color: gray; text-decoration: none; }
a:hover { text-decoration: underline; }
marquee { font-family: helvetica, arial, sans-serif; font-weight: bold; color: red; }
</style>
<!-- (c)2010-2015 Glen Campbell. All rights reserved. -->
</head>
<body>
  <div id="main">
  	<p id="status"><?php print $status;?></p>
  	<?php if ($extra) { ?>
  	<p id="extra"><?php print $extra;?></p>
  	<?php } ?>
    <?php if ($days_left >= 0) { ?>
    <br>
    <a href="http://blog.friendfeed.com/2015/03/dear-friendfeed-community-were.html">FriendFeed
    is not long for this world</a>
    <?php } ?>
  	<?php
  	// release the curl handle to clear up memory
  	curl_close($ch);
  ?>

  </div>

  <div id="footer">
  <p>&copy;2010-<?php echo date('y');?> <a href="http://glen-campbell.com/" title="My boring blog">Glen Campbell</a>
  &bull;
  <a href="/help.html">Help</a>
  &bull;
  <a href="https://github.com/gecampbell/isfriendfeeddeadyet.com">Code</a>
  </p>
  </div>

</body>
</html>
