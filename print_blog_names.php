<?php
//this should be redone as an ajax call.

function print_blog_names($account) {
	$posts_arr = unserialize(file_get_contents('./accounts/' . $account . '/posts_data.cache'));
	$titles = array();
	foreach ($posts_arr as $k => $data) {
		$titles[] = $data['blog_title'];
	}
	
	$titles = array_unique($titles);
	natcasesort($titles);
	
	foreach ($titles as $k => $title) {
		echo "<li><span>" . strip_tags($title) . "</span> <a href='" . $posts_arr[$k]['blog_url'] . "'>(visit)</a></li>\n";
	}
	unset ($posts_arr);
}

?>