What it does
============

Use raspdio to listen to web radio stations, while recording/dumping the same<sup>1</sup> stream for later use. Control it via http interface.
It runs on my [Raspberry Pi](http://www.raspberrypi.org/)/[raspbian](http://www.raspbian.org/) (hence the name), but should run on any \*nix that can satisfy the dependencies (porting wouldn't be hard either).

<sup>1</sup> only one connection is made. It saves your and the station's bandwith. This was the one of the original motivations for this project.

Where it's at
=============

As I do this in my freest of time, it *works, but barely works* at the moment. This is my first github project, too. While I normally would not release a project at a stage *that ugly*, I'm using it to get into git and -hub.
It's really super ugly.

Deps
====

*	Good old [mplayer](http://www.mplayerhq.hu/) does the streaming and playback.
*	Interface written in pretty basic (< 5) PHP.
*	It needs \*nixy PHP stuff, like `posix_mkfifo` (and support for FIFOs, of course).
*	\*nixy (or POSIXy?) stuff like `kill`, `ps`, `tee`.
*	ts
*	ALSA for volume control (`amixer` shell call)
*	bash to wrap up the piping.
*	I run it on [lighttpd](http://www.lighttpd.net/) with PHP-CGI.

Installation
============

1.	Set up a site in your httpd.
2.	There's basically two files:
	*	`index.php` is the app,
	*	`stations.php` has your stations; edit it.
3.	Two directories, make them writable for the httpd's user
	*	`dump` for the dumps, (I symlinked this to another parition)
	*	`fifos` where the app creates some fifos needed for piping business.

Usage
=====

Playback
--------

1.	Point your browser to wherever you installed raspdio.
2. 	In the stations overview, click on start. As soon the cache is full enough, you should hear something.
3.	You can stop playback there, too. Starting a different station will stop the current station, too.
4.	At the top, there's a volume control bar (measured in dBs, really superugly ATM).
5.	Debug info is all over the place.
6.	There's a playlist at the bottom (provided your station sends info), with links to search the song on Google or YouTube.

Dumping
-------

*	All playback is dumped automatically (like some DVRs do) into your dump folder, filenames are formatted
	>	`Y-m-d_H-i-s.station.extension`, e.g. `2013-03-20_07-42-05.wfmu.mp3`
	There's the mp3, obviously, and a .txt file with the timestamped playlist.
*	The `latest.*` symlinks point to the latest dump's files.
*	All dumps older than 24h are garbage-collected/removed upon (any) station start.
*	When I want to save a song, I open the `latest.mp3` from the cifs-mounted<sup>1</sup> dumps directory in [mp3splt-gtk](http://mp3splt.sourceforge.net/mp3splt_page/home.php)<sup>2</sup>, set the split-points<sup>3</sup>, set the file name<sup>4</sup> and save it!

<sup>1</sup> I do it manually ATM. `smb://` in KDE copies the whole file to `/tmp`, `.gvfs` has serious problems in Ubuntu 12. SSHFS is ok, too, but the Raspi seems to struggle with the encryption overhead.  
<sup>2</sup> No re-encoding!  
<sup>3</sup> Manually, which can be a drag, but mp3splt handles it quite well. Automatic splitpoints from playlist roadmapped.  
<sup>4</sup> Automatic file name roadmapped as well.  

$medium is killing music
========================

I'm not sure how legal dumping is. In your country. I buy music; I use the dumps solely for future reference, to remember for future purchase (or concert visit) and usually for obscure stuff that is impossible to find.

Internals
=========

*	Simultaneous playback/dumping is done by `mplayer -streamdump`ing into a named pipe which is then `tee`d into a file and another mplayer process
*	Raspdio scans your processes for the mplayer process and matches its args to find the pid
*	which is used for stopping by `kill`ing the streamdumping mplayer process. Playback continues for a while until the cache of the playback process is empty.
*	Don't worry about the *two* playback processes, one [handles the caching](http://lists.mplayerhq.hu/pipermail/mplayer-users/2008-June/073389.html).

Roadmap
=======

A whole lot.

*	Comment the source :)
*	Nicer interface. Way nicer.
*	Better volume control
*	Playlist interface
	*	<strike>Auto-reload + scroll to the bottom</strike>
	*	<strike>AJAXify</strike>
	*	<strike>Better processing (remove mplayer garbage, `uniq`ify as some stations repeat song info)</strike>
	*	Possibility to bookmark songs
	*	<strike>Automatic links to Youtube, Dailymotion, Google Video etc.</strike>
*	Handle pls/m3u instead of direct links, which may change. 
*	Better “splitability”
	*	Automatic cue/split files
	*	with automatic artist/title info
*	DVR/cron functionality

License
=======

Don't be stupid, support artists and radio stations. Otherwise free. Attribution and feedback welcome, though.

Please
======

*	donate to [WFMU](http://www.wfmu.org/). They're so good.
*	become a “friend” (=donate monthly to) of [Byte.FM](http://byte.fm/) so you can stream their archive, so no need for dumping anymore :)
