<?php

// methods for updating the stored data for posts and keywords.


class cache_updater {
	
	private $window_length = 31; //31 days; we need a day of padding for dealing with different client timezones
	private $posts_arr;
	private $new_posts;
	private $keywords_arr;
	private $stopwords;
	
	function __construct() {
		error_reporting(0);
	}

	
	/* getters and setters:
	***********************************************************************/
		
	function set_posts_arr($posts_arr) {
		$this->posts_arr = $posts_arr;
	}
	function get_posts_arr() {
		return $this->posts_arr;
	}
	function get_keywords_arr() {
		return $this->keywords_arr;
	}
	function set_stopwords($stopwords_arr) {
		$this->stopwords = $stopwords_arr;
	}
	function get_posts_count() {
		return count($this->posts_arr);
	}

	/* sorting posts:
	***********************************************************************/
		
	function time_cmp($a, $b) {
		return ($a['post_time'] > $b['post_time']) ? -1 : 1;
	}
	function sort_posts_by_time() {
		usort($this->posts_arr, array("self", "time_cmp"));
	}
	
	
	/* modifying the $posts_arr property:
	***********************************************************************/
	
	function update_posts($new_posts) {
		$this->new_posts = $new_posts;
		
		$this->add_new_posts();
		$this->sort_posts_by_time();
		$this->truncate_posts_by_time();
	}

	function add_new_posts() {
		//on the first run of this, we know the new posts are the same as the old posts:
		if (!is_array($this->posts_arr)) {
			$this->posts_arr = $this->new_posts;
			return; 
		}
		
		$not_in_arr = $this->new_posts;
		foreach ($this->new_posts as $k => $post_data) {
			foreach ($this->posts_arr as $m_k => $m_post_data) {
				//check two ways to see if we already have that post:
				//first,  title-and-blog; we have to do this because some people point to two urls:
				if ($post_data['post_title'] == $m_post_data['post_title'] && $post_data['blog_title'] == $m_post_data['blog_title']) {
					unset($not_in_arr[$k]);
					continue;
				}
				//if it passes that, check to see if someone republished a post at the same url
				//(happens when people make changes to a post); that doesn't count as a new post.
				if ($post_data['post_url'] == $m_post_data['post_url']) {
					unset($not_in_arr[$k]);
				}			
			}
		}
		$this->posts_arr = array_merge($not_in_arr, $this->posts_arr);
	}
	
	function truncate_posts_by_time() {
		$window_start = time() - $this->window_length * 86400;
		
		foreach ($this->posts_arr as $k => $data) {
			if ($data['post_time'] < $window_start) {
				unset($this->posts_arr[$k]);
			}
		}
	}
	
	/* making the keywords array:
	***********************************************************************/
	
	function build_keywords_arr() {
		//there are more complete lists for cleaning up html, but I think this is enough for our purposes.
		$punctuation = array('\'', '"', '!', '.', ',', ':', '?', '(', ')', '-');
		$script_tags_regex = '/<script\b[^>]*>(.*?)<\/script>/siu';
		
		//initialize vars
		$all_keywords = array();
		$post_keywords = array();
		foreach ($this->posts_arr as $post_key => $post_data) {
			$text = $post_data['content'];
			
			//do basic cleanup on the text; this has a lot of room for improvement...
			$text = html_entity_decode($text, ENT_QUOTES, "UTF-8");
			$text = strtolower($text);
			$text = preg_replace($script_tags_regex, ' ', $text); //gets rid of content between script tags
			$text = strip_tags($text); //this cuts file size of the serialize arr by around 25%
			$text = preg_replace('/&[^ ]+;/u', ' ', $text);//gets rid entities  html_entities decode() missed
			$text = str_replace($punctuation, ' ', $text); //remove problematic punctuation
		
			//make the words into an array; str_word_count() is not a bulletproof way to do this, and could use some improvement
			$post_keywords_raw = str_word_count($text, 1);
			$post_keywords_raw = array_keys(array_flip($post_keywords_raw));// throw out duplicates (keys, flip is faster than array_unique())
			
			//remove stopwords and reindex; stopwords is already an array, so no need for processing.
			$post_keywords_raw = array_values(array_diff($post_keywords_raw, $this->stopwords));
						
			//make a new array keyed by the word's stem; add this posts' keywords, citations, and word-versions
			foreach ($post_keywords_raw as $k => $keyword) {
				$keyword_stem = PorterStemmer::Stem($keyword); 
				if (isset($all_keywords[$keyword_stem]['version'][$keyword])) {
					$all_keywords[$keyword_stem]['version'][$keyword]++;
				} else{
					$all_keywords[$keyword_stem]['version'][$keyword] = 1;
				}
				
				$all_keywords[$keyword_stem]['citations'][] = $post_key;
			}
		}
		//make it easier to find a word's top version later:
		foreach ($all_keywords as $stem => $data) {
			arsort($all_keywords['version']);
		}
		return $all_keywords;
	}
	

}

?>