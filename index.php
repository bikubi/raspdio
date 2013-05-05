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
	function start($station) {
		global $stations,$fifodir,$icydir,$dumpdir,$icylatest,$dumplatest;
		$uri=$stations[$station]['uri'];
		$cache=$stations[$station]['cache'];
		echo "<pre>start station $station, uri $uri cache $cache".n;
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
			echo "fifo $fifo is there.".n;
		}
		else {
			echo "mkfifo $fifo: ".b(posix_mkfifo($fifo,0644)).n;
		}
		/* realpath returns no trailing /, if omitted, find finds the directory itself */
		/* ctime +0 finds files older than one day; plus = greater than */
		$gccmddump = 'find '.escapeshellarg(realpath($dumpdir)).'/ -ctime +0 -delete';
		$gccmdicy = 'find '.escapeshellarg(realpath($icydir)).'/ -ctime +0 -delete';
		echo "gc: "
			."dump: ".exec($gccmddump, $null, $cleanupdump)
			.$cleanupdump."<!-- $gccmddump -->".n
			." icy: ".exec($gccmdicy, $null, $cleanupicy)
			.$cleanupicy."<!-- $gccmdicy -->"
			.n;
		echo "unlink icylatest $icylatest: ".
			b(unlink($icylatest)).n;
		echo "unlink dumplatest $dumplatest: ".
			b(unlink($dumplatest)).n;
		echo "symlink icylatest $icylntgt → $icylatest: ".
			b(symlink($icylntgt,$icylatest)).n;
		echo "symlink dumplatest $dumplntgt → $dumplatest: ".
			b(symlink($dumplntgt,$dumplatest)).n;
		//TODO escape all these shellargs
		$cmd="bash -c \"mplayer $uri -cache $cache -dumpstream -dumpfile $fifo < /dev/null | stdbuf -o L grep ICY | ts > $icyfile & tee <$fifo >(mplayer - -quiet -cache 128) > $dumpfile & \" 1>/tmp/b1 2>/tmp/b2 & ";
		echo "command is '$cmd'".n;
		exec($cmd);
		echo "</pre>";
	}
	function ps($killall=false) {
		global $runninguris,$kill;
		$runninguris=array();
		$ps=`ps -u www-data -o pid,%cpu,args --no-headers`;
		echo '<table>
			<caption>processes</caption>
			<thead>
				<th>?</th>
				<th>PID</th>
				<th>%CPU</th>
				<th>uri</th>
				<th>ARGS</th>
			</thead>
			<tbody>';
		foreach(explode("\n",$ps) as $_) {
			$pid=intval(substr($_,0,5));
			$cpu=floatval(substr($_,6,4));
			$args=substr($_,11);
			echo "<!-- $pid,$cpu,$args, -->\n";
			if(substr($args,0,7)=='mplayer') {
				echo "<tr>";
				list(,$uri)=explode(' ',$args);
				if(($killall && $uri!='-') || $kill==$pid) {
					echo "<td>killing…".
						b(posix_kill($pid,SIGTERM)).
						"</td>";
				}
				else {
					if($uri!='-') {
						echo "<td><a href=\".?kill=$pid\">kill</a></td>";
						$runninguris[$uri]=$pid;
					}
					else {
						echo "<td>&nbsp;</td>";
					}
				}
				echo "<td>$pid</td><td>$cpu</td><td>$uri</td><td>$args</td>";
				echo "</tr>";
			}
		}
		echo '</tbody></table>';
	}
	$kill = array_key_exists('kill',$_GET) ? intval($_GET['kill']) : false;
	$start = array_key_exists('start',$_GET) ? $_GET['start'] : false;

	if(isset($_GET['setvol'])) {
		$newvol=intval($_GET['setvol']);
		//$setvolmsg=`amixer set PCM $newvol%`;
		$setvolmsg=`amixer -- set PCM {$newvol}dB`;
	}
	//$volnowpercent=intval(`amixer sget PCM|grep -oPm 1 '\d+%'`);
	$volnowdb=intval(`amixer sget PCM|grep -oPm 1 '\-?\d+(.\d\d+)?dB'`);


header('Content-type: text/html; charset=utf-8');
?>
<html>
	<head>
		<title>raspdio</title>
		<style type="text/css">
			.std {
				font-size: 80%;
			}
		</style>
		<link rel="shortcut icon" type="image/x-icon" href="./raspdio.ico" />
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
			if($start || $kill) {
				ps($start!==false); 
				//sleep(2);
			}
			if($start!==false) start($start); 
			ps();
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
		<iframe src="<?=$icylatest?>"></iframe>
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
		var doreload=<?=b((bool)($kill||$start))?>;
		if(doreload) {
			window.setTimeout(function() {
				document.location.href='./';
			},30000);
		}
	})();
	/* ]]> */
	</script>
</html>
