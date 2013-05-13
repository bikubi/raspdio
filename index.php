<?php
	define('n',"\n");
	include 'stations.php';
	$fifodir="fifos";
	$icydir="dump";
	$icylatest="$icydir/latest.txt";
	$dumpdir="dump";
	$dumplatest="$dumpdir/latest.mp3";
	$runninguris=array();
	function b($var) {
		return var_export($var,true);
	}
	//function start($station) {
	//	global $stations,$fifodir,$icydir,$dumpdir,$icylatest,$dumplatest;
	$kill = array_key_exists('kill',$_GET) ? intval($_GET['kill']) : false;
	$start = array_key_exists('start',$_GET) ? $_GET['start'] : false;
	/* ------------------ check processes ----------------- */
	$runninguris=array();
	$ps=`ps -u www-data -o pid,%cpu,args --no-headers`;
	$proc = array();
	foreach(explode("\n",$ps) as $_) {
		$pid=intval(substr($_,0,5));
		$cpu=floatval(substr($_,6,4));
		$args=substr($_,11);
		echo "<!-- $pid,$cpu,$args, -->\n";
		if(substr($args,0,7)=='mplayer') {
			//echo "<tr>";
			$proc[$pid]=array('pid'=>$pid, 'cpu'=>$cpu, 'uri'=>$uri, 'args'=>$args, 'killing'=>false);
			list(,$uri)=explode(' ',$args);
			if(($start && $uri!='-') || $kill==$pid) {
				//echo "<td>killing…".
				$proc[$pid]['killing']=true;
				echo "<!-- killing...".
					b(posix_kill($pid,SIGTERM)).
					"-->\n";
				//	"</td>";
			}
			else {
				if($uri!='-' && $uri!='') {
					//echo "<td><a href=\".?kill=$pid\">kill</a></td>";
					$runninguris[$uri]=$pid;
				}
				else {
					//echo "<td>&nbsp;</td>";
				}
			}
			//echo "<td>$pid</td><td>$cpu</td><td>$uri</td><td>$args</td>";
			//echo "</tr>";
		}
	}
	echo "<!-- runninguris = ".print_r($runninguris,1)."-->";
	$runningstation = false;
	foreach($stations as $station => $_) {
		if(array_key_exists($_['uri'], $runninguris)) {
			$runningstation = $station;
		}
	}
	/* ----------------- start station ------------------------- */	
	$startlog = array();
	if($start) {
		$station = $start;
		$uri=$stations[$station]['uri'];
		$cache=$stations[$station]['cache'];
		$startlog[]="<pre>start station $station, uri $uri cache $cache";
		$ts=date('Y-m-d_H-i-s');
		$icylntgt="$ts.$station.txt";
		$icyfile="$icydir/$icylntgt";
		$dumplntgt="$ts.$station.mp3";
		$dumpfile="$dumpdir/$dumplntgt";
		$null=null;
		$cleanupdump='undef';
		$cleanupicy='undef';
		$fifo=$fifodir."/".$station;
		if(file_exists($fifo)) {
			$startlog[]="fifo $fifo is there.";
		}
		else {
			$startlog[]="mkfifo $fifo: ".b(posix_mkfifo($fifo,0644));
		}
		/* realpath returns no trailing /, if omitted, find finds the directory itself */
		/* ctime +0 finds files older than one day; plus = greater than */
		$gccmddump = 'find '.escapeshellarg(realpath($dumpdir)).'/ -ctime +0 -delete';
		$gccmdicy = 'find '.escapeshellarg(realpath($icydir)).'/ -ctime +0 -delete';
		$startlog[]="gc: "
			."dump: ".exec($gccmddump, $null, $cleanupdump)
			.$cleanupdump."<!-- $gccmddump -->"
			." icy: ".exec($gccmdicy, $null, $cleanupicy)
			.$cleanupicy."<!-- $gccmdicy -->";
		$startlog[]="unlink icylatest $icylatest: ".
			b(unlink($icylatest));
		$startlog[]="unlink dumplatest $dumplatest: ".
			b(unlink($dumplatest));
		$startlog[]="symlink icylatest $icylntgt → $icylatest: ".
			b(symlink($icylntgt,$icylatest));
		$startlog[]="symlink dumplatest $dumplntgt → $dumplatest: ".
			b(symlink($dumplntgt,$dumplatest));
		//TODO escape all these shellargs
		$cmd="bash -c \"mplayer $uri -cache $cache -dumpstream -dumpfile $fifo < /dev/null | stdbuf -o L grep ICY | ts > $icyfile & tee <$fifo >(mplayer - -quiet -cache 128) > $dumpfile & \" 1>/tmp/b1 2>/tmp/b2 & ";
		$startlog[]="command is '$cmd'";
		exec($cmd);
	}
	//}

	/* ------------------ volume ------------------------- */
	if(isset($_GET['setvol'])) {
		$newvol=intval($_GET['setvol']);
		//$setvolmsg=`amixer set PCM $newvol%`;
		$setvolmsg=`amixer -- set PCM {$newvol}dB`;
	}
	//$volnowpercent=intval(`amixer sget PCM|grep -oPm 1 '\d+%'`);
	$volnowdb=intval(`amixer sget PCM|grep -oPm 1 '\-?\d+(.\d\d+)?dB'`);

	foreach($stations as $station=>$params) {
		$running=array_key_exists($params['uri'],$runninguris);
		$stations[$station]['running']=$running;
	}
	echo "<!--".print_r($stations,1)."-->";

	
header('Content-type: text/html; charset=utf-8');
?>
<html>
	<head>
		<title>raspdio<?= $runningstation ? ": $runningstation" : "" ?></title>
		<style type="text/css">
			.std {
				font-size: 80%;
			}
			caption {
				/* like a h1 in webkit */
				text-align: left;
				font-size: 2em;
				margin: 0.67em 0 0.67em 0;
				font-weight: bold;
			}
			#playlist a {
				margin-right: 0.33em;
			}
		</style>
		<link rel="shortcut icon" type="image/x-icon" href="./raspdio.ico" />
		<script src="./jquery-1.9.1.min.js" type="text/javascript">
		</script>
		<script>
		/* <![CDATA[ */
			$(function() {
				var	doreload=<?=b((bool)($kill||$start))?>,
					stations = <?=json_encode($stations)?>,
					runningstation = <?=b($runningstation)?>,
					stationre = runningstation ? new RegExp(stations[runningstation].re) : /.*/,
					linere = /(\w+ \d\d \d\d:\d\d:\d\d) ICY Info: StreamTitle=\'(.*?)(\';StreamUrl=\')?\'/;
				function refreshplaylist () {
					$.ajax({
						url: 'dump/latest.txt',
						success: function(data, textstatus, jqxhr) {
							var	lines = data.split('\n'),
								s, line, linematches, trackinfomatches,
								datetime, trackinfo, artist, song, lasttrackinfo = '',
								html = '', trackhtml, searchhtml;
							for(s = 0; s < lines.length; s++) {
								line = lines[s];
								linematches = line.match(linere);
								if(linematches) {
									//console.log(linematches);
									datetime = linematches[1];
									trackinfo = linematches[2];
									if(trackinfo == lasttrackinfo) {
										console.log('skipping', trackinfo);
									}
									else {
										lasttrackinfo = trackinfo;
										trackhtml = '<span class="datetime">'+datetime+'</span>';
										trackinfomatches = trackinfo.match(stationre);
										if(trackinfomatches) {
											artist = trackinfomatches[1];
											song = trackinfomatches[2];
											console.log(datetime, artist, song);
											trackhtml = '<td>' + artist + ' &mdash; ' + song + '</td>';
											searchhtml = '<a class="googlelazy" href="http://google.com/#q=' + encodeURI(trackinfo) + '">G~</a>'
												+ '<a class="googlequoted" href="http://google.com/#q=&quot;' + encodeURI(artist) + '&quot; &quot;' + encodeURI(song) + '&quot;">G&quot;</a>'
												+ '<a class="youtube" href="http://www.youtube.com/results?search_query=' + encodeURI(artist) + '+' + encodeURI(song) + '">Yt</a>';
										}
										else {
											console.warn('could not match', trackinfo, 'with', stationre);
											trackhtml = '<td>' + trackinfo + '</td>';
											searchhtml = '<a class="googlelazy" href="http://google.com/#q=' + encodeURI(trackinfo) + '">G~</a>'
												+ '<a class="googlequoted" href="http://google.com/#q=&quot;' + encodeURI(trackinfo) + '&quot;">G&quot;</a>'
												+ '<a class="youtube" href="http://www.youtube.com/results?search_query=' + encodeURI(trackinfo) + '">Yt</a>';
										}
										html = '<tr><td>' + datetime + '</td><td>' + searchhtml + '</td>' + trackhtml + '</tr>'
											+ html;
										console.log(stationre,trackinfo.match(stationre));
									}
								}
								else {
									console.warn('could not match: ', line, 'with', linere);
								}
							}
							$('#playlist tbody').html(html);
							
						}
					});
				}
				if(doreload) {
					window.setTimeout(function() {
						document.location.href='./';
					},3000);
				}
				refreshplaylist();
				$('#playlist button').on('click',refreshplaylist);
				window.setInterval(refreshplaylist, 60000);
				console.log('raspdio, hello, hello world');
			});	
		/* ]]> */
		</script>
	</head>
	<body>
		<div id="vol">
		<?php
			/*
			for($vols=0;$vols<=100;$vols+=5) {
				if($vols==$volnow) { 
					echo $vols;
				}
				else {
					echo "<a href=\"?setvol=$vols\">&mdash;</a>";
				}
			}*/
			for($vols=-20;$vols<=4;$vols+=2) {
				if($vols==$volnowdb) {
					echo $vols;
				}
				else {
					echo "<a href=\"?setvol=$vols\">+</a>";
				}
			}
		?>
		</div>
		<?php
			/*if($start || $kill) {
				ps($start!==false); 
				//sleep(2);
			}*/
			//if($start!==false) start($start); 
			//ps();
			//echo "<pre>".print_r($proc,1)."</pre>";
		?>
		<table>
			<caption>stations</caption>
			<thead>
				<th>name</th>
				<th>running</th>
			</thead>
			<tbody>
<?php
	foreach($stations as $station=>$params) {
		echo "<tr>";
		echo "<td>".$station."</td>";
		$uri=$params['uri'];
		$cache=$params['cache'];
		$running=array_key_exists($uri,$runninguris);
		$pid = $running ? $runninguris[$uri] : false;
		if($running) {
			echo "<td><a href=\".?kill=$pid\">stop</a></td>";
		}
		else {
			echo "<td><a href=\".?start=$station\">start</a></td>";
		}
		echo "</tr>";
	}
?>
			</tbody>
		</table>
		<?php if($start): ?>
		<div class="run">
			<h1>run</h1>
			<pre>
				<?=implode("\n",$startlog)?>
			</pre>
		</div>
		<?php endif; ?>
		<table class="proc">
			<caption>processes</caption>
			<thead>
				<th>&nbsp;</th>
				<th>pid</th>
				<th>cpu</th>
				<th>uri</th>
				<th>cmd</th>
			</thead>
			<tbody>
				<?php
				foreach($proc as $pid => $_) {
					echo "<tr>";
					echo $_['killing']
						? "<td>killing</td>"
						: "<td><a href=\".?kill=$pid\">kill</a></td>";
					echo "<td>$pid</td>".
						"<td>".$_['cpu']."</td>".
						"<td>".$_['uri']."</td>".
						"<td>".$_['args']."</td>".
						"";
					echo "</tr>";
				}
				?>
			</tbody>
		</table>
		<!--<iframe src="<?=$icylatest?>"></iframe>-->

		<table id="playlist">
			<caption>playlist <button>refresh</button></caption>
			<tbody></tbody>
		</table>
		<div class="std">
			<h1>stdout</h1>
			<pre class="std"><?php readfile('/tmp/b1'); ?></pre>

			<h1>stderr</h1>
			<pre class="std"><?php readfile('/tmp/b2'); ?></pre>
		</div>
	</body>
	<script type="text/javascript">
	/* <![CDATA[ */
	(function() {
	})();
	/* ]]> */
	</script>
</html>
