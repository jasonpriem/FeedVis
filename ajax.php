<?php
include './window_keywords.php';
include './calend.php';

$keywords_cache = "./accounts/" . $_POST['account'] . "/keywords_data.cache";
$posts_cache = "./accounts/" . $_POST['account'] . "/posts_data.cache";

//serving the posts calendar: 
if ($_POST['calArr']) {
	
	$blog_name = json_decode(stripslashes($_POST['blogName']), true);
	$cal_dates = json_decode(stripslashes($_POST['calArr']), true);
	$calend = new calend($posts_cache);
	$calend->make_posts_calend($cal_dates);
	if ( $blog_name ) $calend->filter_by_blog($blog_name);
	echo json_encode($calend->get_cal());
}

//serving information about all the words in a certain time window:
if ($_POST['allWords']) {
	$window_posts = json_decode(stripslashes($_POST['posts']), true);
	$window_keywords = new window_keywords($posts_cache, $keywords_cache, $window_posts);

	//get sorted, truncated lists of window keywords
	$tagcloud = $window_keywords->get_sorted_words('windowFreq', 25);
	$window_info = $window_keywords->get_window_data();
	
	echo json_encode(array("tagcloud"=>$tagcloud, "info"=>$window_info));
}


//data about a particular word within a window.
if ($_POST['stem']) {
	$posts = json_decode(stripslashes($_POST['posts']), true);
	$window_keywords = new window_keywords($posts_cache, $keywords_cache, $posts);
	
	$word_data = $window_keywords->get_word_data($_POST['stem']);
	echo json_encode($word_data);
}


?>