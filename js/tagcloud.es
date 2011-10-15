
wordInfo = {};
wordInfo.postsInfoUrl = "./words_ajax.php";
newTc = {};
newTc.tagcloud = {};

//horrid hack required by IE's odd support of animation for font-size;
//IE needs pixels, but they don't resize right.  There's got to be a better way...
newTc.pxForIe = function(val) {
	return ($.browser.msie) ? val * 30 + "px" : val + "em";
}
	
		
/***********************************************************************************
* 
* 
* newTc: 
* Makes and animates the tagcloud, using a server-supplied array of words and their citations.                        
*
*
************************************************************************************/	
		
		

// calls everything needed to get the latest tagcloud, and does the animation.
newTc.updateTc = function (ul) {
	newTc.addNorms();
	
	//if the tagcloud hasn't run before, we append a seed for the insertNewWords() method to use
	if (!$("a", ul)[0]) $("<li><span class='stem'></span><a style='display:none'>zzz</a></li>").appendTo(ul); 
	
 	newTc.insertNewWords(ul);
	

	$(ul).find("a").each(function(){
		var thisStem = $(this).siblings("span.stem").text();
		var newSize;
		var newColor;
		var windowFreq;
		var freqDiff;
		
		if (typeof newTc.tagcloud[thisStem] == 'object') { //this word is in the new tagcloud from the server.
			var oldSize = $(this).css("fontSize")
			newSize = newTc.tagcloud[thisStem]['windowFreqNormed'] * .01;
			newColor = newTc.freqDiffToRgb(Math.round(newTc.tagcloud[thisStem]['freqDiff']));
			
			windowFreq = newTc.tagcloud[thisStem]['windowFreq'];
			freqDiff = newTc.addPlus(newTc.tagcloud[thisStem]['freqDiff']) + "%";
			$(this)
			.animate( {
				fontSize:newTc.pxForIe(newSize), 
				height:"1em",
				color:newColor 
				}, 3000, function() {
					$("#blog-names:hidden").fadeIn().siblings("#tagcloud");//the first time we want the blog names hidden so we can see the cool animation
					cal.active = true; //the calandar was deactivated while the animation ran
				}
			)
			.siblings("div").find("span")
				.filter(".window-freq").text(windowFreq)
				.end()
				.filter(".freq-change").text(freqDiff)
				.css( {color:newColor} )
					
		} else {//this word isn't in the latest tagcloud: its life is forfeit.
			$(this)
			.animate( {fontSize:0, opacity:0}, 3000) 
			.parent()
 			.animate({height:"0"}, 3001, function() { $(this).remove() })
		};
	});
}




//adds any words from the server that aren't already in the cloud.  On the first run, all the words come from here.
newTc.insertNewWords = function(ul) {
	for (var word in newTc.tagcloud) {
		if (!($(ul).find("span.stem:content("+word+")").html())) { //the word's not in the tagcloud; we'll have to add it
			var topVersion = newTc.getTopKey(newTc.tagcloud[word].version);
			var newLi$ = $("<li></li>")
						.append("<div class='stats'><span class='freq-change'></span><span class='window-freq'></span></div><a href=\"#\">"+topVersion+"</a>")
							.find("a")
							.css( {fontSize:0, height:"0", color:"#777"} ) 
							.end()
			  			.append("<span style='display:none' class='stem'>"+word+"</span>");
	
			$(ul).find("a").each(function(i) {
				if (i == 0 && $(this).text() > topVersion) {
					$(this).parent().before(newLi$);
					return;
				}
				if (topVersion > $(this).text()) {
					$(this).parent().after(newLi$);
					return;
				} 
			});
		}
	}
	
	//initialize the tooltip handler:
	halp.freq($("#tagcloud span.window-freq"));
	halp.freqDiff($("#tagcloud span.freq-change"));
}



//norms the tagcloud
newTc.addNorms = function() {
	var freqArr =[];
	
	for (var word in newTc.tagcloud) {
		freqArr[word] = newTc.tagcloud[word]['windowFreq'];
	}
	var maxi = newTc.objMax(freqArr);
	var mini = newTc.objMin(freqArr);
	
	for (var word2 in newTc.tagcloud) {
		newTc.tagcloud[word2]['windowFreqNormed'] = Math.round(freqArr[word2] * 100 / maxi);

	}
}




//assigns colors
//tried a log scale, but i think it was less informative.
//I'd love to use a true isoluminance algoithm, but this'll have to do for now...
newTc.freqDiffToRgb = function(freqDiff) {
	freqDiff *= 2;
		
	freqDiff = (freqDiff < -100) ? -100 : freqDiff; 
	freqDiff = (freqDiff > 100) ? 100 : freqDiff;
	var red = 0;
	var green = 0;
	
	if (freqDiff > 0) {
		 green = Math.round(1 * freqDiff + 100);
		 red = 200 - green;
	} else {
		red = Math.round(-1 * freqDiff + 100);
		green = 200 - red;
	 } 
	var blue = 75 - Math.min(Math.abs(red - green),75); 
	 
	return "rgb(" + red + "," + green + "," +blue+ ")";
}
	




/*
* Update the header for the date-info area:
************************************************************************************/	

newTc.updateHeader = function(loc) {
	
	//update the main calendar heading:
	var heading$ = 	$("#cal h2#title");
	var blogName$ = $("#blog-names li.selected");
	
	heading$.find("a.orig").hide().end().find("a.filtered").remove();
	
	if (blogName$.length) { 
		$("<a class='filtered'></a>")
		.append(blogName$.find("span").text())
		.attr( "href", blogName$.find("a").attr("href") )
		.prependTo(heading$);
	} else {
		heading$.find("a.orig").show();	
	}
	
	//update the date-info stuff:
	$(loc+" *").remove();
 	$("<h2 id='range'>"+cal.win.getRange(" - ")+"</h2>").appendTo(loc).hide().fadeIn(300);
	
	//add the distribution graph:
	var normConst = 100 / Math.max.apply(null, newTc.info.dist);
	
	$("<ul id='dist-graph'></ul>").
	each(function() {
		for (var i=0; i < newTc.info.dist.length; i++) {
			if (newTc.info.dist[i] > 1) {
				$("<span>"+newTc.info.dist[i]+"</span>")
				.height((newTc.info.dist[i] * normConst)+"%")
				.appendTo(this)
				.wrap("<li></li>")
			}
		}
	}).appendTo(loc);
	
	//add the window info 
	$("<p>"+newTc.info.uniqueWords+" unique keywords in "+newTc.info.postsCount+" posts (avg "+newTc.info.keywordsPerPost+" per post).</p>")
	.addClass("window-info")
	.appendTo(loc);
	
	halp.distGraph($("ul#dist-graph"));
}














/***********************************************************************************
* 
* 
* word info: the user clicks a word to get more detailed information about it
* This could go in another file, i guess...                                  
*
*
************************************************************************************/	


/* Make and remove the word-info div containers:
***********************************************************************************/

//handles the zooming out from the word-info div;
wordInfo.zoomOut = function() {
	//re-activate the calendar chart:
	$("#cal ul.calendar").removeClass("inactive")
	cal.active = true;
	
	//animate the word-info div out
	$("div#zoom")
		.animate( { //use the values we saved when we zoomed in
		top:wordInfo.pos.top,  
		left:wordInfo.pos.left, 
		width: wordInfo.dim.wide,
		height:wordInfo.dim.high,
		opacity:.5
		}, 
		500, function() {
			$(this).remove()
			//remove the handler that let you click on the date-info header to close word-info
			$("div.header").unbind("click"); 
		 })
		 .find("#posts").remove();
}

//zooms in on a word and displays a bunch of information about it, including posts that use the word
//in the selected time period.
wordInfo.zoomIn = function(a) {

	var li$ = $(a).parent();
	
	//we need to find it's position, so we can start the animation from here
    wordInfo.pos = li$.position();
    wordInfo.dim = {
	    "wide":li$.width(),
	    "high":li$.height()
    };
    
    //fade out the calendar control...there's nothing it can do now.
    $("#cal ul.calendar").addClass("inactive")
    cal.active = false;
    
    //allow clicking the date-info header
    var header = $("#date-info div.header");
	header.one("click", function(){wordInfo.zoomOut()});
	
	//now we append all the stuff that goes up here:
	$("<div id='zoom'></div>")
	.append("<div id='word-info'><div class='dropshadow'id='word-ds'></div></div>")
	.find("div#word-info")
	.append("<a class='close-button'>close</a>").find("a.close-button").click(function(){wordInfo.zoomOut()}).end()
	.append("<a class='exp-col'>hide details</a>")
	.append(li$.find("span").clone())
	.append("<span class='loading'>"+li$.find("a").text()+"<img src='./images/loading.gif'></span>")
	.end() //end div#word-info
	
	//now we give div#zoom absolute position so we can animate it elsewhere:
	.css( {position:"absolute", 
		top:wordInfo.pos.top, 
		left:wordInfo.pos.left, 
		width:wordInfo.dim.wide,
		opacity:.2 //ie ignores this if it's in the stylesheet
		} )		
	.appendTo("#date-info")
	
	//finally, animate it into place:
	.animate( {
		top: (header.height() - 1).pxToEm(),  //pxToEm() uses jquery; it's in the jquery plugins file.
		left:"0", 
		width: "100%",
		height: ($("#date-info").height() - header.height()).pxToEm(),
		opacity:1
		 },
		500,
		function() {
			$(this).append("<div id='posts' style='height:"+ ( $("div#zoom").height() - $("div#word-info").height() - 1) +"px'>");
			
			//we wait till the animation is over to do the ajax call; this could be tweaked to improve performance.
			wordInfo.getWordInfo(li$.find("span.stem").text());
		});
	
	//swap background and text color for the freq-change:
	$("#word-info span.freq-change").css( {background:$("#word-info span.freq-change").css("color")} ).css( {color:"#eee"} );
		
	
	//button to show and hide info about posts:
	$("a.exp-col").click(function() {
		$("#posts div.extry").toggle();
		 $(this).text( ($(this).text() == "hide details") ? "show details" : "hide details" );
	})
	return false;
}




	
	

/* Get and write the data about posts that cite the word
***************************************************************************************************************/

//takes a stem, and a list of posts in the window.
//returns a list of posts that cite that word, with a bunch of information about them.
wordInfo.getWordInfo = function(wordStem) {
	$.post(cal.ajaxUrl, {stem:wordStem, posts:cal.win.stringify(), account:cal.account}, function(json) {
		wordInfo.writePostsInfo(json);
		wordInfo.writeVersInfo(json);
		
		halp.stemUses($("span.stem-uses"));
	}, "json")
}

//accepts an object with information about posts that cite a certain word.
//makes a list of information for each posts, organized by day; appends it all to div#posts.	
//could really use some refactoring.
wordInfo.writePostsInfo = function(ajaxInfo) {
	var holder$ = $("<div></div>"); //just to hold the lists and headings in order.
	var ul$;
	var j = 1;
	
	 //run this once for each of the days in the live window, making a labelled ul for each.
	$.each(cal.win.days, function(i) {
		
		//the heading gets an id so we can link to it; one is made for each ul.
		ul$ = $("<ul class='day-of-posts'></ul>");
		
		for (k in ajaxInfo.posts) { //runs once for every post that the server tells us cites this word
	 		if (this.posts.inArray(k)) { //if the word's in this day, add it to this day's ul.
		 		thisPost = ajaxInfo.posts[k];
				$("<li></li>")
				.append("<span class='stem-uses'>"+thisPost['stemUses']+"</span>")
				.find("span.stem-uses").each(function() {
					if (thisPost['stemUses'] == 2) $(this).addClass("two");
					if (thisPost['stemUses'] >= 3) $(this).addClass("three");
				})
				.end()
				.append("<a target='_blank' class='title' href='"+thisPost['postURL']+"'>"+thisPost['title']+"</a>")
				.append("<cite>"+thisPost['blogTitle']+"</cite>")
				.append("<div class='extry'><p>"+thisPost['excerpt']+"</p></div>")
				.find("p")
				.append("<span class='stats'></span>")
				.find("span.stats")
				.append("(<span class='time'>"+ Date.str(thisPost['time'], "time") +",</span> ")
				.append("<span class='word-count'>"+thisPost['wordCount']+" words</span>)")
				.end() //of span.stats 
				.end() // of p
				.appendTo(ul$);
			}
		}
		//we're running this backwards. A much better way would be to have a function that actually checks the order and 
		//inserts things in the right place...
		holder$.prepend(ul$)
		if (ul$.contents().size()) {//no point in including a label to nothing.
			holder$.prepend("<h4 id='d"+ this.dayStart +"'><span>"+Date.str(cal.calArr[i].dayStart, "date") +"</span></h4>");
		}
		j++;
	});
	$("#posts").html(holder$.contents()); //puts the lists in the #posts div.
	
	//append the links to days:
	$("#word-info").append( wordInfo.addJumpLinks($("#posts h4")) ); //add the internal links that click straight to a day
	$.localScroll( {target:"#posts"} ); //localscroll is in the jQuery plugins file
	
}




//accepts a jQuery wrapped set of the things to link to; return a div full of links.
wordInfo.addJumpLinks = function(targ$) {
	var div$ = $("<div id='jump-to-day'><ul></ul></div>");
	
	if (targ$.size() < 2) return div$;
	var spacing = Math.ceil(targ$.size() / 6); //we want a maximium of 6 links
	targ$.each(function(i, val) {
		if (i % spacing == 0) { //space the links out evenly if we've got more days than links.
			div$.find("ul").prepend( "<li><a href='#" + $(this).attr("id") +  "'>" + $(this).text().match(/\d{1,2}/) + '</a></li>' );
		}
	});
	return div$.append("<h4>Jump:</h4>");
}







/*  write data about versions of the word
***************************************************************************************************************************************/


//accepts an object with information about posts that cite a certain word.
//makes a tagcloud-style ul to display the versions of a cited word (eg, "jump", "jumping", etc); the tagcloud hides until hovered over;
//replaces div#zoom div#word-info span.loading after the zoom is done.
wordInfo.writeVersInfo = function(wordInfo) {
		var workingVers = {};
		var $versionsUl = $("<ul id='versions'></ul>");
		var normFactor =(1 / newTc.objMax(wordInfo.versions));
		var sum = newTc.objSum(wordInfo.versions);
		
		$.extend(workingVers, wordInfo.versions) //we want to work on a copy, not a reference, because the loop changes the objects's vals
		for (var lex in wordInfo.versions) {
			var topVersion = newTc.getTopKey(workingVers); // top version changes every iteration, as the old top version gets set to zero
			var percent = Math.round(wordInfo.versions[topVersion] * 100 / sum);
			percent = (percent == 0) ? 1 : percent; //if a word is shown at "0%", it's confusing
			
			$("<li></li>")
			.append("<span class='percent'>"+percent+"%</span>")
			.append("<span class='version'>"+topVersion+"</span>")
			.find("span.version").css( {fontSize:(wordInfo.versions[topVersion] * normFactor +0.5)+"em"} )
			.end()
			.appendTo($versionsUl); //not appending $versionsUl itself yet; we've more work to do on it
			
			workingVers[topVersion] = 0;
		}
		
		$versionsUl
		.find("li:first").hover( 
			function() {
				$(this).siblings().slideDown(200).end().parent().addClass("expanded")
			},
			function() {
				$(this).siblings().slideUp(200, function() {
					$(this).parent().removeClass("expanded");
				});
			}
		)
		
		$("div#zoom div#word-info span.loading")
		.replaceWith($versionsUl);
		$("#versions li:not(:first)").hide(); //has to be after $versionsUl is appended for Safari
}









/***********************************************************************************
* 
* 
* Writes context-sensitive help in a pop-up tooltip.  The jquery tooltips plugin I'm using
* doesn't have shadows, so I kind of quickly hacked them in using the dropShadow() plugin.  
* That should be redone with a set of sliding-door style bg images.
*
*
************************************************************************************/	

halp = {
	freqDiff:function(a) { //the frequency difference in front of each tagcloud word
		$(a).tooltip({
		    bodyHandler: function() { 
				var word$ = $(this).parents("li").find("a");
				var freq = $(this).text();
			    var aboveBelow = function() {
			        if ( freq.match(/-/) ) return "below";
			        return "above";
		        }
			        
			    var str = 'From ' + $("h2#range").text() + ', "';
		        str +=   word$.text() + '" ';
		        str += 'was <span style="color:' + word$.css("color") + '">' + freq.match(/\d+%/) + ' ';
			    str += aboveBelow() + '</span> its community-wide average frequency.</p>';
			    
			    return str;
		    }, 
		    showURL: false,
		    delay: 0
		});	
	},
	freq:function(a) { //the window frequcncy in front of each tagcloud word
		$(a).tooltip({
		    bodyHandler: function() { 
			    var str = '<p>From ' + $("h2#range").text() + ', "';
		        str += $(this).parents("li").find("a").text() + '" was used ';
			    str += $(this).text() + ' times per 1000 words.</p>';   //for some reason, Safari needs the nbsp 
			    return str;
		    }, 
		    showURL: false,
		    delay: 0
		});
	}, 
	stemUses:function(a) {//the number of times each post uses a certain word stem, in the word-info zoom div
		$(a).tooltip({ 
		    bodyHandler: function() { 
		        return $('<p>"' + $("span.version:first").text() + '" is used ' + $(this).text().times() + ' in this post.</p>');
		    }, 
		    showURL: false,
		    delay: 0
		});
	},
	sampleSize:function(a) { //the control for the sample size
		$(a).tooltip({ 
		    bodyHandler: function() { 
		        return $('<p>Select how many days should be in the sample.</p>');
		    }, 
		    showURL: false,
		    delay: 0
		});
	},
	distGraph:function(a) { //the graph in the date info header.
		var str = "<p>This chart represents the <strong>distribution</strong> of the words from ";
		str += $("h2#range").text() + ".</p>";
		str += "<p>Each one-pixel column represents one percent of the words in the sample; the most popular 1% is furthest left, then the next-most-popular 1%, and so on.  The height of the column indicates the number of uses for that word.</p>"
		str += "<p>The idea is that more focused conversations will generate charts dominated by one thin spike on the left; more diffuse conversations should generate a flatter curve, as a wider variety of words remains relatively important.</p>"
		$(a).tooltip({
			bodyHandler: function() {
				return str;
		    }, 
		    showURL: false,
		    delay: 0,
		    extraClass:"grande",
		    track:false
		});
	}
		
	

}








/*utility functions: (these could definately use some cleaning up...
* there's some duplication in there.
*************************************************************************************************/

//utility functions for accessing properties:
newTc.objSum = function(arr) {
	var sum = 0;
	for (k in arr) {
		sum += arr[k];
	}
	return sum;
}
newTc.objMax = function(obj) {
	for (k in obj) {
		if (!(obj[k] <= maxi) && typeof obj[k] == 'number') var maxi = obj[k];
	}	
	return maxi;
}
newTc.objMin = function(obj) {
	for (k in obj) {
		if (!(obj[k] >= mini)) var mini = obj[k];
	}	
	return mini;
}
newTc.getTopKey = function(obj) {
	for (k in obj) {
			if (!(obj[k] <= obj[topKey])) var topKey = k;
		}
	return topKey;
}
newTc.addPlus = function(num) {
	if (num >= 0) num = "+" + num;
	return num;
}

Date.prototype.monthName = function() {
	var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "July", "Aug", "Sep", "Oct", "Nov", "Dec"]
	return months[this.getMonth()];
}
Date.str = function(timestamp, format) {
	var d = new Date(timestamp * 1000);
	if (format == "date") {
		return d.monthName() + " " + d.getDate();
	}
	if (format == "time") {
		var hours = d.getHours();
		var suffix = (hours < 12) ? "am" : "pm";
		if (hours > 12) hours -= 12;
		if (hours == 0) hours = 12;
		var mins = (d.getMinutes() < 10) ? "0" + d.getMinutes() : d.getMinutes();
		
		return hours + ':' + mins + ' ' + suffix
	}
}

Array.prototype.inArray = function (needle) {
	var i;
	var len = this.length
	for (i=0; i < len; i++) {
		if (this[i] == needle) return true;
	}
	return false;
};

String.prototype.times = function() {
	if (typeof parseInt(this) == 'number') var num = this;
	else return false;
	numString = ['three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty' ];
	
	if (num == 1) return "once";
	if (num == 2) return "twice";
	if (num > 20) return "over twenty times";
	return numString[num - 3] + " times";
}

