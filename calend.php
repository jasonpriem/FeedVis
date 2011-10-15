<?php

/*
* works with the array of posts sorted by day
************************************************************************************************/

class calend {
	private $posts_calend;
	private $posts_arr;
	 
	function __construct($loc) {
		date_default_timezone_set('UTC');
		$this->posts_arr = unserialize(file_get_contents($loc));
	}
	
	function get_cal() {
		return $this->posts_calend;
	}
	
	//splits the posts into a given number of days, 
	//the $cal_arr argument is an array with start and end UTC timestamps for each day.
	function make_posts_calend($cal_arr) {
		$posts_calend = array();
		foreach ($cal_arr as $day => $times) {
			$start = $times['dayStart'];
			$end = $times['dayEnd'];
			$posts_calend[$day] = array();
			
			foreach($this->posts_arr as $post_key => $data) {
				if ($data['post_time'] > $start && $data['post_time'] < $end) {
					$posts_calend[$day][] = $post_key;
				}
			}
		}
		$this->posts_calend = $posts_calend;
	}
	
	function filter_by_blog($blog_name) {
		$cal = array();
		foreach($this->posts_calend as $day => $posts) {
			$cal[$day] = array();
			
			foreach($posts as $k => $post_key) {
				if ($this->posts_arr[$post_key]['blog_title'] == $blog_name) {
					$cal[$day][] = $post_key;
				}
			}
			
		}
		$this->posts_calend = $cal;
	}

}
?>
		
