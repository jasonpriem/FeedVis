<?php
class posts_getter {
	
	private $opml_str;
	private $posts_to_get = 25; //  maximum number of posts to get from each feed
	private $posts_arr;
	
	function __construct($feed_urls) {
		error_reporting(0);
		$this->feed_urls = $feed_urls;
	}
	function get_posts() {
		return $this->posts_arr;
	}

	
	/*------------------------------------------------------------------------------------------------------
	this uses the cURL library to get the content of a web page.  It's heavier
	than it needs to be, but I might use some of the extra stuff later
	 from http://nadeausoftware.com/articles/2007/06/php_tip_how_get_web_page_using_curl
	arguments:	the url you want to get stuff from
	returns:	the content of that page
	--------------------------------------------------------------------------------------------------------*/
	
	function curl_get($url) {
		$options = array(
	        CURLOPT_RETURNTRANSFER => true,     // return web page
	        CURLOPT_HEADER         => false,    // don't return headers
	        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
	        CURLOPT_ENCODING       => "utf-8",       // handle all encodings
	        CURLOPT_USERAGENT      => "spider", // who am i
	        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
	        CURLOPT_CONNECTTIMEOUT => 20,      // timeout on connect
	        CURLOPT_TIMEOUT        => 20,      // timeout on response
	        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
	    );
	
	    $ch      = curl_init( $url );
	    curl_setopt_array( $ch, $options );
	    $content = curl_exec( $ch );
	    $err     = curl_errno( $ch );
	    $errmsg  = curl_error( $ch );
	    $header  = curl_getinfo( $ch );
	    curl_close( $ch );
	
	    $header['errno']   = $err;
	    $header['errmsg']  = $errmsg;
	    $header['content'] = $content;
	    return $header;
	}
	
	/*------------------------------------------------------------------------------------------------------
	Makes sure that a url is valid before we store it.
	------------------------------------------------------------------------------------------------------*/
	function good_url($url) {
		if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
		return 'invalid url';
	}
		
	
// 	/*------------------------------------------------------------------------------------------------------
// 	looks through the opml file and returns the urls for feeds.
// 	argument:	location of the opml file; doesn't have to be on your server
// 	returns:	the urls for all the feeds
// 	------------------------------------------------------------------------------------------------------*/
// 	function feed_urls() {
// 		$opml = simplexml_load_string($this->opml_str);
// 		
// 		foreach ($opml->xpath('//outline') as $outline) {
// 			$url = (string)$outline[xmlUrl];
// //			if (!$url) $url = (string)$outline[url];
// 			
// 			if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
// 			if (strlen($url) > 100) continue; 
// 			$feed_urls[] = $url;
// 		}
// 		return $feed_urls;
// 	}
	
	/*------------------------------------------------------------------------------------------------------
	Gets the articles from rss feeds.
	argument:  	an RSS feed item loaded as a simpleXML object 
	returns:	returns the content or, if there isn't any, returns the description
	------------------------------------------------------------------------------------------------------*/
	
	function rss_get_post_content($item) {
		$content_ns = 'http://purl.org/rss/1.0/modules/content/';
	
		//for normal rss feeds:
		if ($item->children($content_ns)->encoded) { 
			 $content = (string)$item->children($content_ns)->encoded;
			 
	    //for rss feeds where only the description's included:
		} elseif ($item->description){
			 $content = (string)$item->description;
	    } else {
		    $content = '';
	    }
		 return $content;
	}
	
	/*------------------------------------------------------------------------------------------------------
	Makes the big array of all the posts.  We sanitize everything EXCEPT the actual post content; that takes
	too long to do correctly, so we'll do it later.
	argument:  	none
	returns:	an numerically-indexed array with information about all posts from all the feeds.
	------------------------------------------------------------------------------------------------------*/
	
	
	function build_posts_arr() {
		//initialize variables that'll be used in the loop below
		$post_num = 0;
		$latest_posts = array();
		
		foreach ($this->feed_urls as $k => $v) {
			
			////////////// get the data ////////////////////////////////
			$raw_feed = $this->curl_get($v);
			$raw_feed['content'] = $raw_feed['content'];
			//the 'LIBXML_NOCDATA' makes sure we get the escaped cdata as well:
			$xml = simplexml_load_string($raw_feed['content'], 'SimpleXMLElement', LIBXML_NOCDATA);
		
			// if it's an rss feed:
			if ($xml->channel->item[0]) {
				$posts = $xml->channel->item;
				$posts_count = count($posts);
				for ($i = 0; $i <= $this->posts_to_get && $i < $posts_count; $i++) { // limit number of posts to get
				
					//data about the blog
					$latest_posts[$post_num]['blog_title'] = strip_tags((string)$xml->channel->title);
					$latest_posts[$post_num]['blog_url'] = $this->good_url((string)$xml->channel->link);
			
					//data about the post
					$latest_posts[$post_num]['post_title'] = strip_tags((string)$posts[$i]->title);
					$latest_posts[$post_num]['post_url'] = $this->good_url((string)$posts[$i]->link);
					
					$this_post_time = strtotime((string)$posts[$i]->pubDate);
					$latest_posts[$post_num]['post_time'] = $this_post_time; 
					
					$text = $this->rss_get_post_content($xml->channel->item[$i]);
					$latest_posts[$post_num]['content'] = $text;
					
					$post_num++;
				}
				
			//if it's an atom feed.  
			} elseif ($xml->entry[0]) {
				$posts = $xml->entry;
				$posts_count = count($posts);
				for ($i = 0; $i <= $this->posts_to_get && $i < $posts_count; $i++) { // limit number of posts to get
		
					//data about the blog:
					$latest_posts[$post_num]['blog_title'] = strip_tags((string)$xml->title);
					$latest_posts[$post_num]['blog_url'] =  $this->good_url((string)$xml->link[0]['href']);
					
					//data about the post:
					$latest_posts[$post_num]['post_title'] = strip_tags((string)$posts[$i]->title);
					$this_post_time = strtotime((string)$posts[$i]->published);
					$latest_posts[$post_num]['post_time'] = $this_post_time;
					$latest_posts[$post_num]['content'] = (string)$posts[$i]->content->asXML();
					
					foreach($posts[$i]->link as $link_num => $link_content) {
						if ($link_content['rel'] == "alternate") {
								$latest_posts[$post_num]['post_url'] = $this->good_url((string)$link_content['href']);
						}
					}
					$post_num++;
				} //end of the entries loop
			 } //end of the atom feed conditional 
		} //end of the main loop to build $latest_posts array		
		$this->posts_arr = $latest_posts;
		return $latest_posts;
	}
}
	
?>
