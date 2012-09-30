<?php
/*
Plugin Name: Twitter Access Control
Plugin URI: https://github.com/lgladdy/wordpress-twitter-access-control
Description: Limit access to a wordpress blog to only followers of a specific twitter account. Until setting-ized, configuration must be done in the tac-settings.php file.
Author: Liam Gladdy
Version: 1.0 Beta
Author URI: http://www.gladdy.co.uk
*/

session_start();
include('tac-settings.php');
include('oauth/twitteroauth.php');

if (!is_admin()) {

	add_action('template_redirect', 'checkAccess');
	add_action('template_redirect', 'processOAuth');
	add_filter('the_content', 'addSigninButton');

}

function getAccessPath() {
	global $auth_page;
	if (isset($_SERVER["SCRIPT_URI"])) {
		return $_SERVER["SCRIPT_URI"];
	} else {
		return "http://".$_SERVER["SERVER_NAME"].$auth_page;
	}
}

function processOAuth() {
	global $auth_page;
	global $consumer_key;
	global $consumer_secret;
	global $account;
	
	$page = get_page_by_path($auth_page);
	$page_id = $page->ID;
	
	if (is_page($page_id)) {
		if (isset($_GET['oauth']) && $_GET['oauth'] == "go") {
			
			$callback = getAccessPath()."?oauth=response";
			
			/* Build TwitterOAuth object with client credentials. */
			$connection = new TwitterOAuth($consumer_key, $consumer_secret);
			 
			/* Get temporary credentials. */
			$request_token = $connection->getRequestToken($callback);
			
			/* Save temporary credentials to session. */
			$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
			$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
			 
			/* If last connection failed don't display authorization link. */
			switch ($connection->http_code) {
			  case 200:
			    /* Build authorize URL and redirect user to Twitter. */
			    $url = $connection->getAuthorizeURL($token);
			    header('Location: ' . $url); 
			    break;
			  default:
			    /* Show notification if something went wrong. */
			    die("Twitter is currently unavailable.");
			}
			
		} else if (isset($_GET['oauth']) && $_GET['oauth'] == "response") {
			if (isset($_REQUEST['denied'])) die("You declined to authenticate with twitter and cannot access the site.");
			
			if (!isset($_REQUEST['oauth_token'])) {
				header("Location: ".getAccessPath()."?oauth=go");
				exit();
			}
			
			/* If the oauth_token is old redirect to the connect page. */
			if (isset($_REQUEST['oauth_token']) && isset($_SESSION['oauth_token']) && $_SESSION['oauth_token'] !== $_REQUEST['oauth_token']) {
				$_SESSION['oauth_status'] = 'oldtoken';
				header("Location: ".getAccessPath()."?oauth=go");
				exit();
			}
			
			if (!isset($_SESSION['oauth_token'])) {
				//Repeated request. This shouldn't happen.
				header("Location: ".getAccessPath()."?oauth=go");
				exit();
			}
			
			/* Create TwitteroAuth object with app key/secret and token key/secret from default phase */
			$connection = new TwitterOAuth($consumer_key, $consumer_secret, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
			
			/* Request access tokens from twitter */
			$access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);
			
			/* Save the access tokens. Normally these would be saved in a database for future use. */
			$_SESSION['access_token'] = $access_token;
			
			/* Remove no longer needed request tokens */
			unset($_SESSION['oauth_token']);
			unset($_SESSION['oauth_token_secret']);
			
			$result = $connection->get('friendships/lookup', array('screen_name' => $account));
			$connections = $result[0]['connections'];
			$name = $result[0]['screen_name'];
			if (!in_array('following',$connections) && $name != $account) {
				unset($_SESSION['authorised']);
				die("You are not following $account and therefore are unable to visit this blog.");
			} else {
				$_SESSION['authorised'] = true;
				header("Location: /");
			}
			
		}
	}
}

function checkAccess() {
	global $auth_page;
	if (!isset($_SESSION['authorised']) && !is_user_logged_in()) {
		$page = get_page_by_path($auth_page);
		$page_id = $page->ID;
		$link = get_permalink($page_id);
		if (!is_page($page_id)) {
			//We're not on the access page.
				header("Location: ".$link);
				exit();
		}
	}
}

function addSigninButton($content) {
	global $auth_page;
	$page = get_page_by_path($auth_page);
	$page_id = $page->ID;
	if (is_page($page_id)) {
		if (isset($_SESSION['authorised'])) {
			$content = "You have successfully authenticated with twitter. You can now view the site.";
		} else {
			$dir = plugin_dir_url(__FILE__);
			$content .= "<a href='$auth_page/?oauth=go'><img src='$dir/sign-in-with-twitter-gray.png' /></a>";
		}
	}
	return $content;
}