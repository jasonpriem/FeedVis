/***********************************************************************************
* 
* 
* cal: 
* Makes and updates the calendar graphic, and attaches the handlers to it for making tagclouds for specific periods.
* Everything is based on the cal.calArr array, which stores each days (in local time) and the posts that go with it.
*
* The cal.win object keeps track of the days that are in the selected window; those are the ones that the tagcloud
* logic is interested in.                     
*
*
************************************************************************************/	

//settings:
cal = {
	//the settings can be customized:
	ul: "#cal ul.calendar", 			//the place in the markup where the calendar will go
	numberOfDays: 30, 		//you can display any number of days (assuming, of course, you actually have the information server-side)
	ajaxUrl: './ajax.php',
	selWidth: 7, 			//the default for how many days get selected at once
	
	//these shouldn't be changed:
	active:true,
	calArr: [],
	blogName:'',
	allowDisplayUpdate:false //we want to lock the tagcloud animation until after other animations have run.
}





/*Make the array of caledar days, with start and end timestamps for each.
*********************************************************************************/

cal.makeCalArr = function() {
	var d = new Date();
	d.setDate(d.getDate() + 1);
	for (var i = 0; i < cal.numberOfDays;  i++) {
		cal.calArr[i] = {};
		d.setDate(d.getDate() - 1);
		
		//gets the utc timestamp for local midnight last night.
	 	d.setHours(0);
	 	d.setMinutes(0);
	 	d.setSeconds(0);
		d.setMilliseconds(0);
		cal.calArr[i]['dayStart'] = Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate(), d.getUTCHours(), d.getUTCMinutes(), d.getUTCSeconds()) / 1000;
		
		//gets the utc timestamp for local 23:59:59 tonight.
		d.setHours(23);
	 	d.setMinutes(59);
	 	d.setSeconds(59);
		cal.calArr[i]['dayEnd'] = Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate(), d.getUTCHours(), d.getUTCMinutes(), d.getUTCSeconds()) / 1000;
 	}
}
//turns the array into json. lighter than using a whole library.
cal.calArr.stringify = function() {
	len = this.length;
	var str = '[';
	for (var i = 0; i < len; i++) {
		if (!i == 0) str += ",";
		str += '{"dayStart":"' + this[i]['dayStart'] + '","dayEnd":"' + this[i]['dayEnd'] + '"}';
	}
	str += ']';
	return str;
}



/* Request the calendar data (posts per day); append the calendar and format it.
*********************************************************************************/


cal.makeCal = function() {
	$.post(cal.ajaxUrl, {calArr:cal.calArr.stringify(), blogName:cal.blogName, account:cal.account}, function(json) {
		var liWidth = 100 / cal.calArr.length;
		
		//we've already made a calendar; we're updating it
		if ($("li", cal.ul).length) { 
			var len = cal.calArr.length
			for (var i = len - 1; i >= 0; i--) {
				$("li", cal.ul).eq(len - 1 - i).find("dd.total").text(json[i].length)
				cal.calArr[i]['posts'] = json[i];
			}
			
		//this is just for the first run:
		} else { 
			
			for (var i = cal.calArr.length - 1; i >= 0; i--) {
				//add the posts to the cal array, to go with the start and end timestamps
				cal.calArr[i]['posts'] = json[i];
				
				//print the list items 
				$("<li></li")
				.append("<span class='day'>"+i+"</span>")
				.append("<h3 class='window'></h3>")
				.append("<dl><dt class='total'>Total posts</dt></dl>")
				.find("dl").append("<dd class='total'>" + json[i].length + '</dd>')
				.end()
				.width(liWidth + "%")
				.appendTo(cal.ul);
			}
			$("li", cal.ul).click(function(e){
				cal.clickHandler(e);
			});
			$("#blog-names li").click(function(e) {
				cal.filterByBlog(e);
			});
			$("#blog-names h3 span").click(function(e) {
				cal.filterByBlog(e);
			});			
			cal.hoverHandler();
		}
		cal.animate_cal();

			
		
	},"json");
}	

	
/* methods for formatting the calendar:
*********************************************************************************/

//sets the max and min value of posts in the cal ul
cal.getMaxMin = function() {
	var totals = new Array;
	$("dd", cal.ul).each(function(){
		totals.push($(this).text()); 
	});
	this.maxi = Math.max.apply(null, totals);
	this.mini = Math.min.apply(null, totals);
}
//animates the height of the bars
cal.animate_cal = function() {
	cal.getMaxMin();
	cal.allowDisplayUpdate = false; //we don't want the display trying to animate while we animate the height
	var totals = new Array;
	$("dd", cal.ul).each(function(){totals.push($(this).text())});
	var normFactor = 100 / cal.maxi;
	var ranOnce = false;
	$("dd", cal.ul).each(function(){ //we want to animate each bar:
	
		var thisHeight = $(this).text() * normFactor;
		$(this).animate( {height:thisHeight+"%"}, 1500, function() {//there's a bunch of stuff we want to do only after the animation is complete:
			
			if (ranOnce) return false; //we don't want to run this callback for every dd
			
			cal.displayBenchmarks();
			if (cal.win.getFirst()) { //if there's a window selected, we must want a new tagcloud
				cal.allowDisplayUpdate = true; //ok, now we're done animating.
				cal.getWords(); 
			}
			
			ranOnce = true;
		});
	});
}

//shows the number of posts for the highest and lowest days on the chart
cal.displayBenchmarks = function() {
	var minFound = false;
	var maxFound = false;
	var lowest = (cal.mini == 0) ? 1 : cal.mini;
	
	$("dd", cal.ul).each(function(){
		$(this).removeClass("benchmark")
		if ($(this).text() == cal.maxi && !maxFound) {
			$(this).addClass("benchmark");
			maxFound = true; //we only want to show one of each; more is confusing.
		}
		if ($(this).text() == lowest && !minFound) {
			$(this).addClass("benchmark");
			minFound = true;
		}
	})
}


/* methods for selecting a part of the calendar:
*********************************************************************************/

//manages the selected day and their data
cal.win = {
	days:{},
	set:function(center) {
			span = Math.round(cal.selWidth / 2); //rounds .5 up.
			//the selection shouldn't be able to fall off the ends:
			center = Math.max(center, span - 1);
			center = Math.min(center, cal.calArr.length - span);
			
			var inWin = {};
			$.each(cal.calArr, function(i, val) {
				if (i > center - span && i < center + span) inWin[i] = val;
			});
			cal.win.days = inWin;
	},
	getFirst: function() {
			for (k in cal.win.days) {
				var kNum = parseInt(k);
				if (!(kNum <= largestKey) && kNum >= 0) var largestKey = k
			}	
			return cal.win.days[largestKey];
	},
	getLast: function() {
			for (k in cal.win.days) {
				var kNum = parseInt(k);
				if (!(kNum >= smallestKey) && kNum >= 0) var smallestKey = k;
			}	
			return cal.win.days[smallestKey];
	},			
	stringify: function() {
			var allPosts = [];
			$.each(cal.win.days, function(i, val) {
				allPosts = allPosts.concat(this.posts);
			});
			return "[" + allPosts.join() +"]";
	}, 
	countPosts: function() {
		var postsStr = this.stringify();
		if (!postsStr.match(/\d/)) return 0;
		if (!postsStr.match(/,/)) return 1;
		return postsStr.match(/,/g).length + 1; 
	},		
	getRange: function(sep) {//return a formatted string showing the timespan of the window.
		var d1 = new Date(cal.win.getFirst().dayStart * 1000);
		var d2 = new Date(cal.win.getLast().dayEnd * 1000);
		
		//if there's just one day, don't return a range:
		if (d1.getDay() == d2.getDay()) return d1.monthName() + '. ' + d2.getDate();
		
		//if there are multiple days:
		if (d1.getMonth() == d2.getMonth()) {
			return d1.monthName() + '. ' + d1.getDate() + sep + d2.getDate();
		} else {
			return d1.monthName() + '. ' + d1.getDate() + sep + d2.monthName() + '. ' + d2.getDate();
		}
	}
} 

//check the current window; return a formatted string showing the range.

//on hover, set the the window of active days and assign styles to the posts in those days
cal.hoverHandler = function() {
	$("li", cal.ul).bind("mouseover",  
		function(){ 
			if (!cal.active) return; //we want to disable this sometimes
			//find the center of the selection, so the arrow can point there:
			cal.center = this;
			cal.labelOffset(this);
			
			//find which days are in the sample, so we can highlight them:	
			//first, find which days in the cal.calArr are in the selection; this gives us the cal.win.days array.
			cal.win.set(parseInt($(this).find("span.day").text()));
			
			//then, add the class to each calendar day that's in the selections array:
			//this lags too much.  I tried finding the position of this, then using gt:() and lt:() ; didn't seem to be any faster.
			$.each(cal.win.days, function(i) {
				$(cal.ul).find("span.day:content("+i+")").parent().addClass("window");
			});
			

			
			//change the h3 of the center li to show the dates in the selection.  It might be better to 
			//just make and append a single h3, then position it where we want.
			var winStart = cal.win.getFirst().dayStart;
			var winEnd = cal.win.getLast().dayEnd;
			$(cal.center).addClass("center").find("h3").text(cal.win.getRange(" - "))
				
		});
	$("li", cal.ul).bind("mouseout",  
		function() {
			$(this).siblings().andSelf().removeClass("window center");
		});
}	
//makes the labels under each sample window look purty; without this, when they are on the end
//they overlap the edges of the calendar.
//maybe i should redo this so that there's just one h3 sitting below the calendar, that gets relatively
//	positioned to be under the right day...
cal.labelOffset = function(li) {
	var h3width = $("h3", li).width()
	liWidth = $(li).width();
	var offsetR = liWidth * $(li).nextAll().length;
	var offsetL = liWidth * $(li).prevAll().length;
	var h3offset;
	
	if (offsetR < h3width / 2) {
		h3offset = offsetR - h3width / 2;
		$("h3", li).css( {left: h3offset, textAlign:"right"} );
		
	} else if (offsetL < h3width / 2) {
		h3offset = h3width / 2 - offsetL;
		$("h3", li).css( {left: h3offset, textAlign:"left"} );
		
	} else {
		var h3offset = 0;
	}
}
	

//runs the animation showing the selection; calls the ajax function to get the selection info
cal.clickHandler = function(e) {
	if (!cal.active) return; //we want to disable this sometimes.
	if (cal.win.countPosts() == 0) return; //no point in sending nothing.
	
		cal.allowDisplayUpdate = false;
		cal.newDataReady = false;
		cal.active = false; //reactiate this in the callback after the tagcloud animation.

		if (!$("#vis").hasClass("live")) { //only runs on the first click
			$("#date-info").animate( {minHeight:"35em"}, 3500 ); 
			$("#triangle").parent().animate( { height:"1em"}, 500 );
		}
		
		//prettify the selected list items.
		$("li", cal.ul).removeClass("selected").end().find(".window").addClass("selected").removeClass("window");
		$(".dropShadow").remove();
		$("li.selected dd")
		.dropShadow( {  //in the jquery plugins file.
			opacity: .2
			});
			
		$("#vis").addClass("live");
		
		//insert the animated 'loading...' graphic:
		$("#date-info .header").empty().append("<p class='loading'>loading<img src='./images/loading.gif'></p>");
		
		//add and move the pointer graphic
		var liPos = $(cal.center).position();
		var liCenter = (liPos.left + ($(cal.center).width() / 2));
		var triWidth = $("#triangle").width();
		
		if (liCenter + (triWidth / 2) > $("#vis").width()) { //it's hanging off the right edge
			var newTriPos = $("#vis").width() - triWidth;
		} else if (liCenter - (triWidth / 2) < 0) {
			var newTriPos = 0; //off the left edge
		} else {
			var newTriPos = liCenter - (triWidth / 2);
		}
		
		var percentWidth = (newTriPos / $("#cal").width() * 100) + "%"
		
		//updating while we move the pointer breaks the animation.  But, if we wait until the pointer is done
		//to make the ajax call, we waste that half-sec doing nothing.  Plus, we have to allow for when the response
		//takes more than half a second:
		$("#triangle").animate( {left:percentWidth }, 500, function() {
			if (cal.newDataReady) cal.updateDisplay(); //getWords() finished first and has loaded the new data for us; we can update.
				cal.allowDisplayUpdate = true;
		}); 
		
		//make the ajax call to get the date info
		cal.getWords(); 
	
}



/* ways for the user to control the display and content of the calendar:
*********************************************************************************/
cal.setControls = function(loc) {
	$("a", loc).click(function() {
		$("a", loc).removeClass("selected");
		$(this).addClass("selected");
		
		//if the user wants all the days, no need to make here click the calendar:
		//we call the hover and click handlers right away.
		if ($(this).text() == "all") {
			cal.selWidth = cal.numberOfDays + 1;
			
			cal.win.set()
		 	$("li", cal.ul)
		 	.eq(Math.ceil(cal.numberOfDays / 2))
		 	.trigger("mouseover").click();
		} else {	
		//for a specified length, we just change the selection width property and wait for a click or hover.		
		cal.selWidth = $(this).text();
		cal.win.set(); //set the array that contains all the selected days.
		}
	return false;
	});
	halp.sampleSize($("h2", loc));
}

cal.filterByBlog = function(e) {
	$("div.dropShadow").remove();
	
	//get the span text, whether the click was on that or elsewhere in the li
	var span$ = $(e.target).find("span").andSelf().filter("span");
	
	//wow, this is so ugly. no time.
	$("#blog-names").find(".selected").removeClass("selected");
	span$.parent().addClass("selected")
 	cal.blogName = (span$.text() == "(all)") ? "" : span$.text();
	cal.makeCal();

}


/* get and display the information from a calendar selection
*************************************************************************************************************/
cal.getWords = function() {
	$.post(cal.ajaxUrl, {posts:cal.win.stringify(), allWords:true, account:cal.account}, function(rock) {
		
	if (rock.tagcloud.length == 0) {
	}
		
	//update data for the tagcloud
	newTc.tagcloud = rock.tagcloud;
	newTc.info = rock.info;
	
	//if the the pointer has finished by this time, update; if not, we'll wait for the changeTimeWindow handler to deal with it.
	cal.newDataReady = true;
	if (cal.allowDisplayUpdate) cal.updateDisplay(); 
	}, "json");
}
cal.updateDisplay = function() {
	//run the functions that update everything
	newTc.updateHeader("#date-info .header");
	newTc.updateTc("#tagcloud");
		
	//set up the handlers for drilling down into info:
	$("#tagcloud li a").unbind("click")
	$("#tagcloud li a").click(function() {wordInfo.zoomIn(this); return false;});
}








