acc = {};
acc.demo = 'edublogs-demo';
acc.createUrl = './create_account.php'
$(document).ready(function(){
	$("p.loading").hide()
	if (cal.account == acc.demo) {
		$("#make-account").show();
	} else {
		$("#account-info").show();
	}
	
	if ( $("#account-errors")[0] ) $("#vis").hide();
	
	acc.accountsList();
	acc.formControls();
	acc.faq();
	acc.halp();

});


/* functions for styling stuff
**********************************************************************/
acc.formControls = function() {
	var accVal = $("#account").val();
	var descVal = $("#desc").val();
	
	$("#account").focus(function() {
		if ($(this).val() == accVal) $(this).val('');
	});
	$("#desc").focus(function() {
		if ($(this).val() == descVal) $(this).val('');

	});
	
	$("form")
	.submit(function() {
		if ($("#account").val() == accVal || $("#account").val() == '') { //oops, no name:
			$("#account")
			.css( { background:"#fab002" } );
			return false;
		}
		if ($("#desc").val() == descVal) { //no description; that's ok.
			$("#desc").val('');
			return true;
		}
	});
}

	
acc.accountsList = function() {
	$("#other-feeds h2")
	.append("<a href='#'>expand</a>")
	.find("a")
	.toggle(
		function() {
			$(this)
			.text('collapse')
			.parents("#other-feeds")
			.find("ul")
			.animate( {height:"30em"} );
		}, function() {
			$(this)
			.text('expand')
			.parents("#other-feeds")
			.find("ul")
			.animate( {height:"6em"} );
		});
		
	return false;
}
acc.faq = function() {
	$("#faq h2")
	.append("<a href='#'>close</a>")
	.find('a')
	.click(function() {
		$("#faq").slideUp(300)
		return false;
	});
	
	$("#nav")
	.find("a:contains('FAQ')")
	.click(function() {
		$("#faq").slideDown(300)
		.find("h2")
		return false;
	});
}
acc.halp = function() {
	$("label[for='opml'] a").click(function() {
		$("#nav a:contains('FAQ')").click();
		$("#faq dt:contains('OPML')").addClass("selected");
		return false;
	});
}
	
			