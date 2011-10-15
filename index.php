<?php 
include './print_blog_names.php';
include './account.php';
include './backend/posts_getter.php';
include './backend/cache_updater.php';
include './backend/stemmer.php';

error_reporting(0);
date_default_timezone_set("UTC"); //everything on the server gets done in UTC...the client will sort out the timezones.
$demo = 'edublogs-demo';
$acc = new account();
//$acc->delete_accounts('edublogs-demo');

//if the sign-up form has been submitted, we set up the account.
if ($_POST['submitted'] && $_FILES['opml']['size'] < 30000) { 
	$acc->create_account($_POST['account'], $_POST['desc'], $_FILES['opml']['tmp_name']);
	if (!$acc->get_errors()){  //worked fine; get my feeds.
		header("Location: " . $acc->print_success());
	}
} 

//if there's no valid account requested, we send 'em to the demo page.
if (!$_GET['account'] || !is_dir('./accounts/' . $_GET['account'])) {
		header("Location: ?account=" . $demo);
	}
	
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"><html><head>';
$acc->print_errors(); //if there was a problem creating the account, they see it here.

?>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="shortcut icon" href="http://feedvis.com/feedvis.ico"> 

<!--  scripts  -->
<script type="text/javascript" src="./js/jquery-1.2.6.min.js"></script>
<script type="text/javascript" src="./js/jquery.plugins.es"></script>
<script type="text/javascript" src="./js/tagcloud.es"></script>
<script type="text/javascript" src="./js/calendar.es"></script>
<script type="text/javascript" src="./js/accounts.es"></script>

<script type="text/javascript">
digg_url = 'http://feedvis.com/';
digg_bgcolor = '#ff9900';
digg_skin = 'compact';
digg_window = 'new';
</script>

<!--  styles  -->
<link rel="stylesheet" type="text/css" href="./styles/main.css">
<link rel="stylesheet" type="text/css" href="./styles/vis.css">
<!--[if IE 7.0]>
  <link rel="stylesheet" type="text/css" href="./styles/ie7.css" />
<![endif]-->


<title>FeedVis</title>
</head>
<body>
<div id="wrapper">
	<div id="about">
		<h1><a href="?account=<?php echo $demo; ?>"><span class='main'>FeedVis</span><span class='subtitle'>an interactive tagcloud for your feeds</span></a></h1>
		<div id='nav'>
			<ul id="nav-links">
				<li><a href="#faq">FAQ</a></li>
				<li><a href="http://jasonpriem.com/feedvis-dev/">comment</a></li>
				<li><a href="http://jasonpriem.com/feedvis-dev/">source code</a></li>
			</ul>
			
			
		</div>
		<div id="account-info">
			<h2>Welcome to your personal FeedVis page!</h2>
			<p>Bookmark this page; every time you return, your feed data will be updated.</p>
		</div>
		<div id="make-account">
			<h2>Create an account:</h2>
			<form method="POST" enctype="multipart/form-data" action="index.php?account=<?php echo $demo; ?>">
				<label for="account">Username: </label>
				<input id="account" name="account" value="max 15 letters/numbers">
				
				<label for="desc">Description:</label>
				<input id="desc" name="desc" value="120 chars; no html"></input>
				
				<label for="opml"><a href="#faq" class="halp">Your&nbsp;opml:</a><span class='paren'>(max 100 feeds)</span></label>
				<input type="hidden" name="MAX_FILE_SIZE" value="30000">
				<input name="opml" id="opml" type="file" value="limit 100 feeds" />
				
				<input type="submit" value="submit" id="submit">
				<input type="hidden" name="submitted" value="true" >
			</form>	
		</div>
		
		<div id="other-feeds">
			<h2>Other people's feeds:</h2>
			<ul>
			<?php $acc->print_accounts() ?>
			</ul>
		</div>
		
		<div id="expl">
			<h2>Ok, what's this thing do?</h2>
			<p>FeedVis is like most word cloud generators, but with some extra goodness:</p>
			<ul>
				<li>You don't just get one lump o' words: you can make tagclouds from subsets of your feeds, selecting by blog, time, or both.  You can then compare those clouds to ones from other subsets; the animation makes it easy to see differences.</li>
				<li>Any time you're interested in a word, you can click for more information, including summaries and links to posts that use it.</li>
			</ul>
			<p>Most of what FeedVis does is based on two numbers for each word:</p>
			<ul>
				<li>The first is <strong>frequency</strong>.  Frequency says how many times a word is used per 1000 words. If you hover over a word, you'll see its frequency to the left of the <em>frequency change</em> value.</li>
				<li>The second is <strong>frequency change</strong>. Often, a word will be more (or less) popular than usual in a certain time period (for instance, "election" in early November). Frequency change measures that difference as a percentage: greener words are unusually popular; redder words are the opposite.</li>
			</ul>
		</div>
	</div>

	</div>
	<div id="vis">
		<div id="faq">
			<h2>FAQ</h2>
			<dl>
				<dt>Why are all the posts clustered toward the right?  Where are the older posts?</dt>
				<dd>Feeds only supply their last 20 or so posts; if they post frequently, that may only go back a few days.  Don't worry, though; FeedVis saves the old posts, so over the next month the list will gradually fill out.</dd>
				
				<dt>What's OPML?</dt>
				<dd><a href="http://en.wikipedia.org/wiki/Opml">OPML</a> is a file format that can hold lists of feeds.  When you export feeds in your feedreader, it gives you OPML.</dd>
				
				<dt>The words are too small.</dt>
				<dd>Use ctrl+mouse-scroll-wheel to zoom in or out. (You can even run the animation from any zoom level.)</dd>
				
				<dt>Why does it take so long to update feeds?</dt>
				<dd>First, it takes some time to actually request and receive the rss/atom data from each blog.  Then it takes a while to do the keyword extraction. I'm sure both these can be improved; grab the code have at it.</dd>
				
				<dt>Can I delete an account?</dt>
				<dd>No. You can just make another one, though.</dd>
				
				<dt>What timezone does FeedVis use?</dt>
				<dd>Yours.  When you view the posts-per-day chart in Helsinki, each day starts at midnight and ends at 11:59 pm Helsinki time.</dd>
				
				<dt>Can I use a custom set of stopwords?</dt>
				<dd>Not now, but that's a planned feature.</dd>
				
				<dt>Can I get the raw feed data out of my account?</dt>
				<dd>Yes.  Everything is stored in two serialized php arrays, located at http://jasonpriem.com/feedvis/accounts/<em>your-account-name</em>.</dd>
				
				<dt>How permanent is FeedVis?</dt>
				<dd>Not very; it's mostly a project to help me learn programming.</dd>
				
				
				
			</dl>
		</div>
		
		<p class='loading' id="main-load">FeedVis is loading and analyzing your feeds; this takes 1-3 minutes.<img src="./images/big-loading.gif" alt="an animated loading graphic" /></p>
		
		<?php
		//this is where the tagcloud actually gets updated and displayed
		
		if ($_GET['account'] && is_dir('./accounts/' . $_GET['account'])) {
			$acc->update($_GET['account']);
			echo "<script type='text/javascript'>cal.account = '" . $_GET['account'] . "'\n";
			echo '	$(document).ready(function(){
						cal.makeCalArr();
						cal.makeCal();
						cal.setControls("#period-len")
					});
				</script>';
		}
		?>
		
		<div id="cal">
			<div id="cal-top">
				<h2 id="title">
					<a class="orig" href="#">
						<?php echo $acc->get_account_name(); ?>
					</a>
					<span>(<?php echo 'next update in '. $acc->get_next_update(); ?>) Posts per day:</span>
				</h2>
				<div id="cal-controls">
					<div id="period-len">
						<h2>days in sample:</h2>
						<ul>
							<li><a href="#">all</a></li>
							<li><a href="#" class="selected">7</a></li>
							<li><a href="#">1</a></li>
						</ul>
					</div>
				</div>
			</div>
			<ul class="calendar">
			</ul>
			
			<div id="point">
				<img id="triangle" src="./images/triangle.png">
			</div>
		</div>
		<div id="date-info">
			<div class="dropshadow" id="date-info-ds"></div>
			<div class="header">
				<p class="init">Select a period from the chart.</p>
			</div>
			
			<div id="blog-names">
				<h3>filter by blog:<span>(all)</span></h3>
				<ul>
				<?php print_blog_names($_GET['account']); ?>
				</ul>
			
			</div>
			<ul id="tagcloud">
			</ul>
		</div>
	</div>
	

	
</div><!--end of the wrapper div-->


<script type="text/javascript"><!--Google analytics code-->
var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
var pageTracker = _gat._getTracker("UA-1907792-3");
pageTracker._initData();
pageTracker._trackPageview();
</script>
</body>
</html>