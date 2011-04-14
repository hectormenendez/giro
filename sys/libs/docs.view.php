<!doctype html>

<head>
	<meta charset="utf-8">
	<title>Documentation</title>
	<style>
		html,body,div,span,h1,h2,h3,h4,h5,h6,p,blockquote,pre,abbr,address,cite,code,del,dfn,em,img,ins,kbd,q,samp,small,strong,sub,sup,var,b,i,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,article,aside,canvas,details,figcaption,figure,footer,header,hgroup,menu,nav,section,summary,time,mark,audio,video {
  			margin:0; padding:0; border:0; font-size:100%; font:inherit; vertical-align:baseline; }
		article,aside,details,form,figcaption,figure,footer,header,hgroup,menu,nav,section {
			display: block; }

		html,body { width:100%; height:100%; overflow:hidden; background:#CCC; }
		ul {list-style:none; }

		body { font:13px/1.231 sans-serif; }
		select,input,textarea,button { font:99% sans-serif; }
		pre,code,kbd,samp { font-family: monospace, sans-serif; }
		
		a { text-decoration:none; color:blue; }
		a:hover { background:blue; color:white !important; }

		code { font-size:.85em; }
		pre { margin-top:2em; }

		h1,h2,h3 { font-family:"Droid Sans",sans-serif; color:#333; }
		h1 { font-size:3em; text-align:right; font-weight:bold; letter-spacing:-.1em; }
		h2 { font-size:2em; text-align:right; }
		h3 { font-size:1.231em; }

		section { z-index:0; position:relative; height:100%; max-width:680px; overflow:auto; padding:1em; background:white};
		section div { position:relative; }

		.left { text-align:left !important; }

  		section#index { float:left; padding-top:3em; }
  		section#index form { margin-bottom:1em; white-space:nowrap; }
  		section#index ul { display:block; }
  		section#index ul.class li { margin-bottom:.3em; font-size:1.1em; font-weight:bold;}
  		section#index ul.class li,
  		section#index ul.class a { display:block; width:100%; }
	
  		section#index ul.method li,
  		section#index ul.method a { margin-bottom:0; display:auto; width:auto; }
  		section#index ul.method li { font-size:.8em; font-weight:normal;}
  		section#index ul.method a { padding:1px 1em 2px 1em; }

  		section#content { display:none; width:auto; padding-right:2em;}
  		section#content header { overflow:visible; }
  		section#content footer { margin-top:1em;}
  		section#content header > div { text-align:right; }
  		section#content h1 { display:inline; }
  		section#content h2  { display:inline; font-weight:bolder; }
  		section#content h2 a { font-weight:normal; color:#666;}
  		section#content h3 { display:inline !important; }

  		header#header { z-index:9; background:#CCC; padding:.3em 0 .35em 1em; left:0; position:absolute; border:1px solid #DDD; border-left:none; border-top:none; border-bottom-right-radius:.5em; }
  		header#header li { display:inline-block; margin-right:1em; }
  		header#header a  { color:#333; font-size:9pt; font-weight:bold; text-shadow:1px 1px white; }
  		header#header a:hover  {color:blue !important; background:none; }

		/*---------- acid Styles ---------*/
		.sh_acid{background:none; padding:0; margin:0; border:0 none;}.sh_acid .sh_sourceCode{background-color:#eee;color:#333;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_keyword{color:#bb7977;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_type{color:#8080c0;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_string{color:#a68500;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_regexp{color:#a68500;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_specialchar{color:#f0f;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_comment{color:#ff8000;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_number{color:#800080;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_preproc{color:#0080c0;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_symbol{color:#ff0080;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_function{color:#046;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_cbracket{color:#ff0080;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_url{color:#a68500;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_date{color:#bb7977;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_time{color:#bb7977;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_file{color:#bb7977;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_ip{color:#a68500;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_name{color:#a68500;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_variable{color:#0080c0;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_oldfile{color:#f0f;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_newfile{color:#a68500;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_difflines{color:#bb7977;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_selector{color:#0080c0;font-weight:normal;font-style:normal;}.sh_acid .sh_sourceCode .sh_property{color:#bb7977;font-weight:bold;font-style:normal;}.sh_acid .sh_sourceCode .sh_value{color:#a68500;font-weight:normal;font-style:normal;}

		/*-------- Snippet Base Styles ----------*/
		.snippet-wrap {position:relative;}
		*:first-child+html .snippet-wrap {display:inline-block;}
		* html .snippet-wrap {display:inline-block;}
		.snippet-reveal{text-decoration:underline;}
		.snippet-wrap .snippet-menu, .snippet-wrap .snippet-hide {position:absolute; top:10px; right:15px; font-size:.9em;z-index:1;background-color:transparent;}
		.snippet-wrap .snippet-hide {top:auto; bottom:10px;}
		*:first-child+html .snippet-wrap .snippet-hide {bottom:25px;}
		* html .snippet-wrap .snippet-hide {bottom:25px;}
		.snippet-wrap .snippet-menu pre, .snippet-wrap .snippet-hide pre {background-color:transparent; margin:0; padding:0;}
		.snippet-wrap .snippet-menu a, .snippet-wrap .snippet-hide a {padding:0 5px; text-decoration:underline;}
		.snippet-wrap pre.sh_sourceCode{padding:1em;line-height:1.1em;overflow:auto;position:relative;
		-moz-border-radius:15px;
		-webkit-border-radius:15px;
		border-radius:15px;
		box-shadow: 2px 2px 5px #000;
		-moz-box-shadow: 2px 2px 5px #000;
		-webkit-box-shadow: 2px 2px 5px #000;}
		.snippet-wrap pre.snippet-textonly {padding:2em;}
		*:first-child+html .snippet-wrap pre.snippet-formatted {padding:2em 1em;}
		* html .snippet-wrap pre.snippet-formatted {padding:2em 1em;}
		.snippet-reveal pre.sh_sourceCode {padding:.5em 1em; text-align:right;}
		.snippet-wrap .snippet-num li{padding-left:0em;}
		.snippet-wrap .snippet-no-num{list-style:none; padding:.6em 1em; margin:0;}
		.snippet-wrap .snippet-no-num li {list-style:none; padding-left:0;}
		.snippet-wrap .snippet-num {margin:1em 0 1em 1em; padding-left:1em;}
		.snippet-wrap .snippet-num li {list-style:decimal-leading-zero outside none;}
		.snippet-wrap .snippet-no-num li.box {padding:0 6px; margin-left:-6px;}
		.snippet-wrap .snippet-num li.box {border:1px solid; list-style-position:inside; margin-left:-3em; padding-left:6px;}
		*:first-child+html .snippet-wrap .snippet-num li.box {margin-left:-2.4em;}
		* html .snippet-wrap .snippet-num li.box {margin-left:-2.4em;}
		.snippet-wrap li.box-top {border-width:1px 1px 0 !important;}
		.snippet-wrap li.box-bot {border-width:0 1px 1px !important;}
		.snippet-wrap li.box-mid {border-width:0 1px !important;}
		.snippet-wrap .snippet-num li .box-sp {width:18px; display:inline-block;}
		*:first-child+html .snippet-wrap .snippet-num li .box-sp {width:27px;}
		* html .snippet-wrap .snippet-num li .box-sp {width:27px;}
		.snippet-wrap .snippet-no-num li.box {border:1px solid;}
		.snippet-wrap .snippet-no-num li .box-sp {display:none;}

		section#content footer ol { font-size:.85em !important;}
	</style>

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.js"></script>
	<script src="http://www.steamdev.com/snippet/js/jquery.snippet.min.js"></script>
	<script>
	// pseudo formatted print.
	String.prototype.format = function(){
		var a = arguments;
		return this.replace(/{(\d+)}/g, function(m, n){
			return typeof a[n] != 'undefined'? a[n] : '{'+n+'}';
		});
	};

	if (window.jQuery) $(document).ready(function(){

	var $content, template;
	// we must always have the request uri with a trailing slash
	var uri = '<?=$_SERVER["REQUEST_URI"]?>';
		uri = uri[uri.length-1] != '/'? uri + '/' : uri;

	$content = $('#content');
	template = $content.html();

	var set = function(){
		// fix relative links [unless they specify ANY kind of path].
		$('a').each(function(){
			var x = new RegExp(/href\=[\"']([^\.\/][a-zA-Z0-9\_\-\/]+)[\"']/);
				x = x.exec(this.outerHTML);
			if (x == null) return;
			this.href = uri + x[1];
		})
		$('section a').click(classclick);

		var wide_head = 0;
		var wide_foot = 0;
		var head = $('section#content header');
		var foot = $('section#content footer > *');

		head.find('h1,h2,h3').each(function(){
			wide_head = Math.max($(this).width());
		});

		foot.each(function(){
			wide_foot = Math.max($(this).width());
		});

		if (wide_head>0) {
			head.width(wide_head);
			head.css({'margin-left': Math.abs(wide_head - wide_foot)})
		}
	}

	var classload = function(data){
		if (typeof data.parent == 'undefined') data.parent = '';
		var html = template.format(
			data.title,
			data.parent,
			data.name,
			data.description,
			data.source
		);
		$content.html(html).show();
		$content.find('pre').snippet('php', { style:'acid' });
		console.dir(data);
		set();
	};

	var classclick = function(e){
		$.post(this.href, { view:true }, classload);
		return false;
	}
	
	set();

	});
	</script>
</head>

<body>
	<header id="header">
		<nav><ul>
			<li><a href="reload">Reload</a></li>
			<li><a href="search">Search</a></li>
		</ul></nav>
		<!--
		<form action="search" method="POST">
			<input name="search_input" type="search" placeholder="Search" autofocus required>
			<input name="search_start" type="submit" value="Go">
		</form>
		!-->
	</header>

	<section id="index">
		<ul class="class">
			<?php
			foreach ($db as $cname => $cbody):
			$action = 'class/'.$cname;
			?>
			<li>
				<a href="<?=$action?>"><?=$cname?></a>
				<?php if (isset($cbody['methods'])):?>
				<ul class="method">
					<?php foreach ($cbody['methods'] as $mname=>$mbody):?>
					<li class="method"><a href="<?=$action.'/'.$mname?>"><?=$mname?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<!-- template start -->
	<section id="content">
		<header>
			<div><h1>{0}</h1></div>
			<div><h2><a href="class/{1}">{1}</a> <b>{2}</b></h2></div>
			<div class="left"><h3>{3}</h3></div>
		</header>
		<footer>
			<pre>{4}</pre>
		</footer>
	</section>
	<!-- template end-->
</body>

</html>