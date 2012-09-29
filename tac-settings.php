<?php

//For the next settings you need to create a new twitter app, at https://dev.twitter.com/apps/new
//You must create the app as the user you want to make sure viewers are following.
//Make sure you specify a callback url to your blog url.

$consumer_key = '';
$consumer_secret = '';

$account = 'lgladdy'; //The account they need to follow

//Finally, I need a page which explains to people they need to be authorised to view the blog. This page isn't protected. Gimme the path to this page.
$auth_page = '/access';

?>