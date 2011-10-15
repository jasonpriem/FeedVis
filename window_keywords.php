<?php
class window_keywords {
	
	private $win_posts;
	private $win_keywords;
	private $keywords_arr;
	private $posts_arr;
	private $freq_sorted_keywords;
	
	                                                            
	/********************************************************************************* 
	*
	* methods run when the object is initialized:
	*
	**********************************************************************************/
	
	function __construct($posts_cache_loc, $keywords_cache_loc, $win_posts) {
		error_reporting(0);
		$this->posts_arr = unserialize(file_get_contents($posts_cache_loc));
		$this->keywords_arr = unserialize(file_get_contents($keywords_cache_loc));
		$this->win_posts = $win_posts;
		
		$this->filter_words_by_date();
		$this->add_freqs();
	}
	
	//modifies the "citations" array of all the keywords to include only posts in this time window.
	//words cited by no posts in this window are removed.
	function filter_words_by_date() {
		$win_posts = array_flip($this->win_posts);
		$window_keywords = $this->keywords_arr;
		foreach ($this->keywords_arr as $stem => $data) {
			foreach ($data['citations'] as $k => $post_key) {
				if (!isset($win_posts[$post_key])) {
					unset($window_keywords[$stem]['citations'][$k]);
				}
			}
			if (count($window_keywords[$stem]['citations']) == 0) {
				unset($window_keywords[$stem]);
			}
		}
		$this->win_keywords = $window_keywords;
	}
	
	//right now it's making the frequency based on the traditional method: uses / total words.
	//the commented lines make a frequency that may be more useful: (number of posts that use the word) / total posts
	//in practice, they seem to give pretty similar results similar results.
	function add_freqs() {
		$posts_selected_prop = count($this->win_posts) / count($this->posts_arr);
		$word_count = count($this->win_keywords);
		
		foreach($this->win_keywords as $stem => &$data) {
			$citations_selected = count($data['citations']);
			$data['windowFreq'] = round($citations_selected / $word_count * 1000);
			
			$citations_selected_prop = $citations_selected / count($this->keywords_arr[$stem]['citations']);
			
			$data['freqDiff'] =  round(($citations_selected_prop - $posts_selected_prop) / $posts_selected_prop * 100);

		}
	}		
		
		

	
	
	
	/********************************************************************************* 
	*
	* methods run after __construct().
	* all these methods return something: they don't modify the object at all.
	* ( accept get_sorted_words(), adding $this->freq_sorted_keywords )
	*
	**********************************************************************************/
	
	
		
	//together, these three functions return a list with the top n words, sorted by either 
	//popularity in this window, or differnce between baseline and current popularity.
	//arguments:
	//$sort_key: what you want to sort by, either "windowFreq" or "freqChange" 
	//$len: how long the returned list should be.
	function windowFreq_cmp($a, $b) {
		return ($a['windowFreq'] < $b['windowFreq']) ? 1 : -1;
	}
	function freqChange_cmp($a, $b) {
		return ($a['freqChange'] < $b['freqChange']) ? 1 : -1;
	}
	function get_sorted_words($sort_key, $len) {
		$sort_these = $this->win_keywords;
		if ($sort_key == 'freqChange') {
			uasort($sort_these, array("self", "freqChange_cmp"));
		} elseif ($sort_key == 'windowFreq') {
 			uasort($sort_these, array("self", "windowFreq_cmp"));
 			$this->freq_sorted_keywords = $sort_these; //save this so we don't have to do it again for the distribution list.
		}
			
		return array_slice($sort_these, 0, $len);
	}
	
	
	//splits the words into percentile chunks by popularity, then finds the avg popularity of each
	//used to make a distribution graph.
	//arguments:
	//$chunks: the number of equal peices you want to split the keywords array into.
	function get_dist_list($chunks) { 
		$citations = array();
		$dist_arr = array();

		foreach($this->freq_sorted_keywords as $word => $v) {
			$citations[] = $v['windowFreq'];
		}
		$chunk_size = round(count($citations) / $chunks);
		if ($chunk_size < 1) return array(100);
		
		$chunk_arr = array_chunk($citations, $chunk_size);
		foreach($chunk_arr as $k => $v) {
			$dist_arr[] = round(array_sum($v) / count($v) * 100);
		}
		return $dist_arr;
	}
	
	//returns an printable excerpt from a chunk of text.
	//arguments:
	//$text: like it says.
	//$len: length in letters
	function make_excerpt($clean_text, $len) {
		$clean_text = trim(substr($clean_text, 0, $len));
		$clean_text = trim(preg_replace('/[^ ]*$/', '', $clean_text)) . '...'; //remove broken word at the end and add ellipsis
		return $clean_text;
	}
	
	
	//returns an array of data about the current window keywords.
	function get_window_data() {
		$window_data = array();
		$unique_uses = 0;
		$uses = 0;
		$window_data['dist'] = $this->get_dist_list(100);
		$window_data['postsCount'] = count($this->win_posts);
		foreach( $this->win_keywords as $word => $data) {
			$unique_uses ++;
			$uses = $uses + count($data['citations']);
		}
		$window_data['uniqueWords'] = $unique_uses;
		@$window_data['keywordsPerPost'] = round($uses / $window_data['postsCount']);
		return $window_data;
	}		
	
	//returns an array of data about a particular word
	//only counts data from within the current time window.
	//argument:
	//$stem: the word's stem, which is its primary key in the keywords array.
	function get_word_data($stem) {
		date_default_timezone_set("utc");

		$word['diff'] = $this->win_keywords[$stem]['diff'];
		$word['freqDiff'] = $this->win_keywords[$stem]['freqDiff'];
		$word['windowFreq'] = $this->win_keywords[$stem]['windowFreq'];
		$word['versions'] = $this->win_keywords[$stem]['version'];
		
		$word['posts'] = array();
		$post = array();
		$stem_uses = array_count_values($this->win_keywords[$stem]['citations']);		
		$formatted_citations = array_reverse(array_unique($this->win_keywords[$stem]['citations']));
		
		
		
		
		foreach ($formatted_citations as $k => $post_num) {
			
			$clean_text = $this->clean_text($this->posts_arr[$post_num]['content']);
			
			$post['wordCount'] = str_word_count($clean_text, 0);
			$post['excerpt'] = $this->make_excerpt($clean_text, 500);
			
			$post['stemUses'] = $stem_uses[$post_num];		
			$post['title'] = strip_tags($this->posts_arr[$post_num]['post_title']);
			$post['blogTitle'] = strip_tags($this->posts_arr[$post_num]['blog_title']);
			$post['time'] = $this->posts_arr[$post_num]['post_time'];
			$post['postURL'] = strip_tags($this->posts_arr[$post_num]['post_url']);
			
			

			$word['posts'][$post_num] = $post; //push the latest post onto the posts arr
		}
		return $word;
	}
	
	function clean_text($text) { // very basic...
		//unescaped html:
		$text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', ' ', $text); //gets rid of content between script tags
		$text = strip_tags($text);
		
		//escaped html:
		$text = html_entity_decode($text, ENT_QUOTES, "UTF-8");
		$text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', ' ', $text); //gets rid of content between script tags
		$text = strip_tags($text); 
		$text = preg_replace('/&[^ ]+;/', ' ', $text);//gets entities  html_entities decode missed
		
		return $text;
	}

			
			
			
			
}

?>
