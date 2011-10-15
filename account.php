<?php

//all of this is pretty temporary; storing every user in a directory that has to always have the username?
//pretty non-extensible.  Need to bit the bullet and actually use a database here.

class account {
	private $home = "accounts/";
	private $accounts_data_loc = "./accounts.txt";
	private $accounts_data_arr;
	private $valid_name;
	private $errors;
	private $opml_str;
	private $account;
	private $interval = 86400; //one day
	
	function __construct() {
		error_reporting(0);
		$this->accounts_data_arr = unserialize(file_get_contents($this->accounts_data_loc));
		return true;
	}
	
	
	
	
	
	
	
	
	/* 
	* validation and error-handling
	****************************************************************************************
	****************************************************************************************/
	
	function validate_name($name) {
		$name = preg_replace('/\W/', '-', $name);
		$name = substr($name, 0, 15);
		return $name;
	}
	
	function validate_desc($desc) {
		$desc = strip_tags($desc);
		$desc = substr($desc, 0, 120);
		return $desc;
	}
	
	//this function needs to be split; it's getting the list of urls as well as finding the errors.
	function get_feed_urls() {
		if ( !$opml = simplexml_load_string($this->opml_str) ) {
			$this->errors[] = "It seems that your opml file is malformed.";
			return false;
		}
		
		foreach ($opml->xpath('//outline') as $outline) {
			$url = (string)$outline[xmlUrl];
			
			if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
			if (strlen($url) > 100) continue; 
			$this->feed_urls[] = $url;
		}
	}
		
	
	function find_errors() {
		$this->num_feeds = count($this->feed_urls);
		
		if ($this->num_feeds == 0) {
			$this->errors[] = "There don't seen to be any feeds in your opml.";
		}
		if ($this->num_feeds > 100) {
			$this->errors[] = "Sorry, but FeedVis can't handle more than 100 feeds (you've got " . $this->num_feeds . ").";
		}
		if (is_dir($this->home . $this->valid_name)) {
			$this->errors[] = "Sorry, looks like someone already has that account.";
		}
	}
	
	function get_errors() {
		return $this->errors;
	}
	
	function print_errors() {
		if (!isset($this->errors)) return false;
		echo '<div id="account-errors">';
		foreach( $this->errors as $k=>$msg) {
			echo '<p>' . $msg . '</p>';
		}
		echo '</div>';
	}
	//this is kind of a hack; used to redirect to the user's account page when a new account is made 
	function print_success() {
		return '?account=' . $this->valid_name;
	}
	
	
	
	
	
	
	
	
	
	
	/*  account creation and updating
	****************************************************************************************
	****************************************************************************************/
	
	//just a hack for now; any account should be able to load some custom stopwords.
	function select_stopwords($acc) {
		$loc = './backend/stopwords.txt';
		if ($acc == "Trendmatcher") $loc = './backend/stopwords-dutch.txt';
			
		$stopwords_arr = str_word_count( file_get_contents($loc), 1 );
		return $stopwords_arr;
	}
	
	//called after the user submits the form to make a new account.
	function create_account($name, $desc, $file_loc) {
		//both these write any errors into $this->errors:
		$this->valid_name = $this->validate_name($name);
		$this->opml_str = utf8_encode(file_get_contents($file_loc));
		
		$this->get_feed_urls();
		
		//validation:
		$this->find_errors();
		if (count($this->errors) > 0) {
			return false;
		}
		
		//make the new directory:
		$new_dir = $this->home . $this->valid_name;
		mkdir($new_dir);
		//make the array with user info
		$user_info = array('time'=>time(), 'desc'=>$this->validate_desc($desc), 'xmlUrls'=>$this->feed_urls);
		
		//put the new user in the users file.
		$this->accounts_data_arr[$this->valid_name] = $user_info;
		$this->cache($this->accounts_data_arr, $this->accounts_data_loc);
		
		return true;
	}
		
	//called every time the user views her account.
	function update($account) {
		$this->account = $account;
		
		//first, find out if we need to update:
		if ($this->accounts_data_arr[$account]['time'] + $this->interval > time() && file_exists('accounts/' . $account . '/posts_data.cache')) {
			return false;
		}
		
		//ok, so we'll need to update this account.
		//first, let's update the time:
		//if there's enough traffic on the site, opening and changing this one text file will become a serious bottleneck...
		$this->accounts_data_arr[$account]['time'] = time();
		$this->cache($this->accounts_data_arr, $this->accounts_data_loc);
		
		
		//now we can update the cache:
		$this->xmlUrls = $this->accounts_data_arr[$account]['xmlUrls'];
		
		
		
		$posts_cache = 'accounts/' . $account . '/posts_data.cache';
		$keywords_cache = 'accounts/' . $account . '/keywords_data.cache';
		
		//get an array of the posts on everyone's feed pages:
		//echo "<p class='update'>getting posts (this may take a while)...</p>";
		$getter = new posts_getter($this->xmlUrls);
		$new_posts_arr = $getter->build_posts_arr();
		unset($getter); //free up some memory
		
		// set up the cache updater 
		//echo "<p class='update'>retrieving old posts...</p>";
		$updater = new cache_updater;
		@$updater->set_posts_arr(unserialize(file_get_contents($posts_cache)));                   
		$updater->set_stopwords( $this->select_stopwords($account) );	
		
		//update the cached posts		
		//echo '<p class="update">updating posts data...</p>';
		$updater->update_posts($new_posts_arr);
		unset($new_posts_arr);

		//cache the updated posts array
		//echo '<p class="update">storing posts data...</p>';
		$this->cache($updater->get_posts_arr(), $posts_cache);

		//make the keywords array
		//echo '<p class="update">extracting word usage data (this takes a minue or two)...</p>';
		$keywords_arr = $updater->build_keywords_arr();
		$posts_count = $updater->get_posts_count(); //find how many posts we've got before we throw them away.
		unset($updater);
		
		//add the baseline frequencies for the keywords
		//echo '<p class="update">calculating usage frequencies...</p>';
		$word_count = count($keywords_arr);
		foreach($keywords_arr as $stem => $data) {
			//if we don't cast these to strings, serilize() stores them as ginormous floats
			$keywords_arr[$stem]['baseline_freq'] = (string)(round(  count($data['citations']) / $word_count * 1000 ) ); 
		}
		
		//cache the keywords array
		//echo '<p class="update">storing word data...</p>';
		$this->cache($keywords_arr, $keywords_cache);

	}
	
	function cache($arr, $loc) {
		$serialized = serialize($arr);
		$handle = fopen($loc, 'w+');
		fwrite($handle, $serialized);
		fclose($handle);
	}
	
	function delete_accounts($account) {
		//first, get rid of the index data:
		if (!is_array($account)) {
			unset($this->accounts_data_arr[$account]);
		} else {
			foreach ($account as $k => $name) {
				unset($this->accounts_data_arr[$name]);
			}
		}
		
		//then, get rid of the files and directory
		$files = array('posts_data.cache', 'keywords_data.cache', 'feeds.opml');
		foreach ($files as $k => $file) {
			unlink($this->home . $account . '/' . $file);
		}
		rmdir($this->home . $account);
		
		//finally, cache the new directory index
		$this->cache($this->accounts_data_arr, $this->accounts_data_loc);
	}

	
	
	
	
	
	
	
	/* getting information about accounts
	****************************************************************************************
	****************************************************************************************/
	
	function print_accounts() {
		$acc = array_reverse($this->accounts_data_arr);
		foreach ($acc as $name => $data) {
			echo '<li>';
			echo '<a href="?account=' . $name . '">' . strip_tags($name) . '</a>';
			echo '<span class="desc">' . strip_tags(stripslashes($data['desc']));
				echo '<span class="num-feeds">(' . count($data['xmlUrls']) . '&nbsp;feeds)</span>';
				echo '</span>'; //span.desc contains span.num-feeds
			echo '</li>';
		}
	}
	
	function get_account_name() {
		return $this->account;
	}
	
	function get_next_update() {
		$last_update = $this->accounts_data_arr[$this->account]['time'];
		$till_next_update = $this->interval - (time() - $last_update);
		
		if ($till_next_update / 3600 < 1) {
			return "under 1 hour";
		} else {
			return floor($till_next_update / 3600) . " hours";
		}
	}
		
	
	
	
	
	
}

?>