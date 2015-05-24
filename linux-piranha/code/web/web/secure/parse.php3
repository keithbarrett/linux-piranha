<?

$LVS	=	"/etc/sysconfig/ha/lvs.cf";	/* Global */

/* Bah 8( ....
$serv = array ( "",
		array ( "",
			array (
				"server"	=> "",
				"address"	=> "",
				"active"	=> "",
				"nmask"		=> "",
				"weight"	=> ""
			),
		),
	);
*/

$virt = array ( "",
		array (
			"virtual"	=> "",
			"address"	=> "",
			"vip_nmask"	=> "",
			"active"	=> "",
			"port"		=> "",
			"load_monitor"	=> "",
			"scheduler"	=> "",
			"method"	=> "",
			"protocol"	=> "",
			"persistent"	=> "",
			"pmask"		=> "",
			"send"		=> "",
			"expect"	=> "",
			"persistent"	=> "",
			"timeout"	=> "",
			"reentry"	=> ""
		)
	);

$fail = array ( "",
		array (
			"failover"	=> "",
			"address"	=> "",
			"active"	=> "",
			"vip_nmask"	=> "",
			"port"		=> "",
			"timeout"	=> "",
			"send"		=> "",
			"expect"	=> "",
			"start_cmd"	=> "",
			"stop_cmd"	=> ""
		)
	);

$prim = array (
		"primary"		=> "",
		"rsh_command"		=> "",
		"backup_active"		=> "",
		"backup"		=> "",
		"heartbeat"		=> "",
		"heartbeat_port"	=> "",
		"keepalive"		=> "",
		"network"		=> "",
		"nat_router"		=> "",
		"nat_nmask"		=> "",
		"service"		=> "",
		"deadtime"		=> "",
		"debug_level"		=> ""
	);

$fd = 0;
$service = "lvs";

// $debug=1;

if (empty($debug)) { $debug = 0; } /* if unset, leave debugging off */

$buffer = "";

function parse($name, $datum) {
	global $debug;
	global $buffer;
	global $fd;
	global $prim;
	global $virt;
	global $fail;
	global $serv;
	global $service;

	static $level = 0 ;
	static $server_count = 0;
	static $virt_count = 0;
	static $fail_count = 0;
	

	if ($debug) {
		if (!empty($buffer)) {
			echo "Level $level &nbsp;&nbsp;&nbsp;&nbsp; $buffer<BR>";
		};
	};

	if (strstr($buffer,"{")) { 
		$buffer = "$name $datum";
		if ($debug) { echo "<FONT COLOR=\"GREEN\">Striping the \"{\". Level changed up. Calling parse(). <BR></FONT>"; };
		$level++;

		/* the following /mess/ is because I want to generate 'structures'	*/
		/* New VIRTUAL required virt[0]						*/
		/* New vitual.server required virt[0]:[0]				*/
		/* New vitual.server required virt[0]:[1]				*/
		/* New VIRTUAL required virt[1]						*/
		/* New vitual.server required virt[1]:[0]				*/
		/* New vitual.server required virt[1]:[1]				*/

		/* I'm sure my logic is flawed, however, this works			*/
		/* Note to self: NEVER TOUCH THESE TWO LINES AGAIN (I REALLY MEAN THAT)	*/
		if ($level == 1) { $server_count = -1; };
		if ($level >  1) { $server_count++ ; };
//		if ($level == 1) { $virt_count++; $fail_count++;  $server_count = -1; };

		parse($name, $datum);
		return; /* <--- HIGHLY IMPORTANT! do **NOT** remove this VITAL command */
	 };

	if (strstr($buffer,"}")) {
		$name = "";
		$datum = "";
		$buffer = "$name $datum";
		if ($debug) { echo "<FONT COLOR=\"RED\">Striping the \"}\". Level changed down. Calling parse(). <BR></FONT>"; };
		$level--;
		parse($name, $datum);
		return; /* <--- HIGHLY IMPORTANT! do **NOT** remove this VITAL command */
	};

	if ($level == 0) {
		switch ($name) {
			/* Level 0 */
			case "primary"		:	$prim[primary] 		= $datum;
							break;
			case "rsh_command"	:	$prim[rsh_command] 	= $datum;
							break;
			case "service"		:	$prim[service] 		= $datum;
							break;
			case "backup_active"	:	$prim[backup_active] 	= $datum;
							break;
			case "backup"		:	$prim[backup] 		= $datum;
							break;
			case "heartbeat"	:	$prim[heartbeat] 	= $datum;
							break;
			case "heartbeat_port"	:	$prim[heartbeat_port] 	= $datum;
							break;
			case "keepalive"	:	$prim[keepalive] 	= $datum;
							break;
			case "deadtime"		:	$prim[deadtime] 	= $datum;
							break;
			case "network"		:	$prim[network] 		= $datum;
							break;
			case "nat_router"	:	$prim[nat_router] 	= $datum;
							break;
			case "nat_nmask"	:	$prim[nat_nmask] 	= $datum;
							break;
			case "debug_level"	:	$prim[debug_level] 	= $datum;
							break;

			case "virtual"		:	/* new virtual definitition */
							$service="lvs"; echo $service;
							break;
			case "failover"		:	/* new failover definitition */
							$service="fos"; echo $service;
							break;

			case ""			:	break;
			default			:	if ($debug) { echo "<FONT COLOR=\"BLUE\">Lv0 - garbage [$name] (ignored line [$buffer])</FONT><BR>"; }
							break;
		}
	}

	if ($level == 1) {
		switch ($name) {
			case "failover"		:	$fail_count++;
							$service="fos";
							if ($debug) { echo "<I>Asked for failover service </I><B>\$fail[$fail_count]</B><BR>"; };
							$fail[$fail_count][failover] = $datum;
							break;
			case "start_cmd"	:	if ($service == "fos") $fail[$fail_count][start_cmd] = $datum;
							break;
			case "stop_cmd"		:	if ($service == "fos") $fail[$fail_count][stop_cmd] = $datum;
							break;
			case "address"		:	if ($service == "fos") { $fail[$fail_count][address] = $datum; } else { $virt[$virt_count][address] = $datum; }
							break;
			case "active"		:	if ($service == "fos") { $fail[$fail_count][active] = $datum; } else { $virt[$virt_count][active] = $datum; }
							break;
			case "port"		:	if ($service == "fos") { $fail[$fail_count][port] = $datum; } else { $virt[$virt_count][port] = $datum; }
							break;
			case "send"		:	if ($service == "fos") { $fail[$fail_count][send] = $datum; } else { $virt[$virt_count][send] = $datum; }
							break;
			case "expect"		:	if ($service == "fos") { $fail[$fail_count][expect] = $datum; } else { $virt[$virt_count][expect] = $datum; }
							break;
			case "timeout"		:	if ($service == "fos") { $fail[$fail_count][timeout] = $datum; } else { $virt[$virt_count][timeout] = $datum; }
							break;
			case "vip_nmask"	:	if ($service == "fos") { $fail[$fail_count][vip_nmask] = $datum; } else { $virt[$virt_count][vip_nmask] = $datum; }
							break;

			case "virtual"		:	$virt_count++;
							$service = "lvs";
							if ($debug) { echo "<I>Asked for virtual service </I><B>\$virt[$virt_count]</B><BR>"; };
							if ($service == "lvs") $virt[$virt_count][virtual] = $datum;
							break;
			case "load_monitor"	:	if ($service == "lvs") $virt[$virt_count][load_monitor] = $datum;
							break;
			case "scheduler"	:	if ($service == "lvs") $virt[$virt_count][scheduler] = $datum;
							break;
			case "protocol"		:	if ($service == "lvs") $virt[$virt_count][protocol] = $datum;
							break;
			case "vip_nmask"	:	if ($service == "lvs") $virt[$virt_count][vip_nmask] = $datum;
							break;
			case "persistent"	:	if ($service == "lvs") $virt[$virt_count][persistent] = $datum;
							break;
			case "pmask"		:	if ($service == "lvs") $virt[$virt_count][pmask] = $datum;
							break;
			case "reentry"		:	if ($service == "lvs") $virt[$virt_count][reentry] = $datum;
							break;
			case "server"		:	/* ignored */
							break;
			case ""			:	break;
			default			:	if ($debug) { echo "<FONT COLOR=\"BLUE\">Lv1 - garbage [$name] (ignored line [$buffer])</FONT><BR>"; }
							break;
		}
	}

	if ($level == 2 ) {
		switch ($name) {
			case "server"		:	if ($debug) { echo "<I>Asked for vitual.server (" 
									. ($server_count+1) . 
									")</I> - <B>\$serv[$virt_count]:["
									. ($server_count+1) . 
									"]</B><BR>"; };
							$serv[$virt_count][$server_count+1][server] = $datum;
							break;
			case "address"		:	$serv[$virt_count][$server_count+1][address] = $datum;
							break;
			case "nmask"		:	$serv[$virt_count][$server_count+1][nmask] = $datum;
							break;
			case "active"		:	$serv[$virt_count][$server_count+1][active] = $datum;
							break;
			case "weight"		:	$serv[$virt_count][$server_count+1][weight] = $datum;
							break;
			case ""			:	break;
			default			:	if ($debug) { echo "<FONT COLOR=\"BLUE\">Lv2 - garbage [$name] (ignored line [$buffer])</FONT><BR>"; }
							break;
		}
	}
}

function next_line() { /* this doesn't feel right... */
	global $fd;
	global $buffer;
	global $debug;
	global $test;

	while (!feof($fd)) {
		$buffer = fgets($fd, 4096);
		if ($debug) { echo "Buffer = [$buffer]<BR>"; }

		/* All data is comprised of a name, an optional seperator and a datum */

		/* oh wow!.. trim()!!! I could hug somebody! */
		$buffer = trim($buffer);

		if (strlen ($buffer) > 4) { /* basically 'if not empty',.. however 'if (!empty($buffer)' didn't work */
			/* explode! oh boy! */
			$pieces = explode(" ", $buffer);

			$name = $pieces[0];
			if (strstr($buffer, "=")) {
				$datum = $pieces[2];
			} else {
				$datum = $pieces[1];
			}
		}
	}
}

function read_config() {
	global $fd;
	global $buffer;


	while (!feof($fd)) {
		$buffer = fgets($fd, 4096);
		if ($debug) { echo "Buffer = [$buffer]<BR>"; }

		/* all data is comprised of a name, an optional seperator, and a datum */

		/* oh wow!.. trim()!!! I could hug somebody! */
		$buffer = trim($buffer);

		if (strlen ($buffer) > 4) { /* basically if not empty,.. however if (!empty($buffer) didn't work */
			/* explode! oh boy! */
			$pieces = explode(" ", $buffer);

			$name = $pieces[0];
			if (strstr($buffer,"=")) {
				$datum = $pieces[2];
			} else {
				$datum = $pieces[1];
			}
			if (!empty($pieces[3])) { $datum = $pieces[2] . " " . $pieces[3] ; }

			if (!empty($pieces[4])) { /* must be a send or expect string */
				$datum = strstr($buffer, "\"");
				$test = $datum;
			}
		}
	parse($name, $datum) ;
	}
	/* specials that need to be preset if unset */
	if (empty($prim['rsh_command'])) {
		$rsync_tool = $prim['rsh_command'] = "ssh";
	}

	if (empty($prim['debug_level'])) {
		$debug_level = $prim['debug_level'] = "NONE";
	}
	return;
}


function backup_lvs() {
	global $prim;
	global $LVS;
	global $SERVER_ADDR;
	global $debug;

	return; /* UNTIL SUCH TIME AS THE GUI/PULSE INTERACTION IS SORTTED OUT */

	$command = "";

	if ($debug) { echo "<HR>Scripts are running on $SERVER_ADDR - Primary server is: " . $prim['primary'] . "<BR>"; }


	/* ---- OLD method ---- I used to allow user nobody to do the scp to root on the other machine, now we use suexec
	                        This code fragment has been left in as a placeholder for future approved file sync scheme
//	if ($SERVER_ADDR == $prim['primary']) {
//		if (($prim['backup'] != "") && ($prim['backup_active'] != 0)) {
//			switch ($prim['rsh_command']) {
//				case "rsh"	:	$command = "rcp /etc/sysconfig/ha/lvs.cf piranha@" . $prim['backup'] . ":/etc/sysconfig/ha/lvs.cf";
//							if ($debug) { echo "<BR>SYNC active, Executing: $command<BR>"; }
//							system($command, $ret);
//							break;
//				case "ssh"	:	$command = "scp /etc/sysconfig/ha/lvs.cf piranha@" . $prim['backup'] . ":/etc/sysconfig/ha/lvs.cf";
//							if ($debug) { echo "<BR>SYNC active, Executing: $command<BR>"; }
//							system($command, $ret);
//							break;
//				default		:	echo "<BR>SYNC active, BUT, No copy to a remote machine made (no copy mode selected)<BR>";
//							break;
//			}
//
//			if (($ret !=0) && ($prim['backup_active'] != 0)) {
//				$user = `/usr/bin/id`;
//				echo "<TABLE BGCOLOR=\"c0c0c0\" WIDTH=\"100%\" BORDER=\"0\"CELLSPACING=\"0\"><TR><TD VALIGN=top><H2><FONT COLOR=red>WARNING</FONT>:&nbsp;</H2></TD>";
//				echo "<TD>It was not possible to syncronize the /etc/sysconfig/ha/lvs.cf file on " .  $prim['backup'] . " using the command <P>$command<BR>as $user<p>";
//				echo "It may be that that system is down OR that the required access privilages for user nobody and/or piranha are incorrect.<BR>";
//				echo "It may be prudent to turn off the backup feature in the '<A HREF=\"redundancy.php3\" NAME=\"Redundany\">Redundany</A>' panel ";
//				echo "until this issue is resolved</TD>";
//				echo "</TR></TABLE>";
//			}
//		}
//	}
	 ---- end OLD method ---- */

	if (($prim['primary'] != "") && ($SERVER_ADDR != $prim['primary'])) {
		echo "<TABLE BGCOLOR=\"c0c0c0\" WIDTH=\"100%\" BORDER=\"0\"CELLSPACING=\"0\"><TR><TD VALIGN=top><H2><FONT COLOR=red>WARNING</FONT>:</H2></TD>";
		echo "<TD>You are attempting to edit the lvs.cf file from a server that is not the cluster master<BR>";
		echo "Please use the primary server as any modifications made on the backup machine will be overwritten by the primary<BR>";
		echo "Based on your current lvs.cf configuration clicking <A HREF=\"HTTP://" . $prim['primary'] . "/piranha/piranha.html\" NAME=\"Primary\">HERE</A> will to attempt connection to the primary else please correct the 'Primary LVS server IP' from the global settings page</TD>";
		echo "</TR></TABLE>";
		return;
	}
	
	/* We use apache's suexec module to pass a killall -USR2 pulse */
	/* argh! not anymore since piranha is installed with a uid < 100 DAMN */

	if (($SERVER_ADDR == $prim['primary']) && ($prim['backup_active'] != "0")) {
		echo `/usr/bin/killall -q -USR2 pulse`;
	}

	return;
}

function print_arrays() {
	/* debugging function only */
	global $prim;
	global $virt;
	global $fail;
	global $serv;
	global $debug;

	$loop1 = $loop2 = 0;

	echo "<HR>DEBUG<HR>";
	echo "<B>Primary</B>";
	echo "<BR>primary = "		. $prim['primary'];
	echo "<BR>service = "		. $prim['service'];
	echo "<BR>rsh_command = "	. $prim['rsh_command'];
	echo "<BR>backup_active = "	. $prim['backup_active'];
	echo "<BR>backup = "		. $prim['backup'];
	echo "<BR>heartbeat = "		. $prim['heartbeat'];
	echo "<BR>heartbeat_port = "	. $prim['heartbeat_port'];
	echo "<BR>keepalive = "		. $prim['keepalive'];
	echo "<BR>deadtime = "		. $prim['deadtime'];
	echo "<BR>network = " 		. $prim['network'];
	echo "<BR>nat_router = "	. $prim['nat_router'];
	echo "<BR>nat_nmask = "		. $prim['nat_nmask'];
	echo "<BR>debug_level = "	. $prim['debug_level'];

	while ($fail[++$loop1]['failover'] != "" ) { /* NOTE: must use *pre*incrempent not post */
	echo "<P><B>Failover</B>";
		echo "<BR>Failover [$loop1] [failover] = "	. $fail[$loop1]['failover'];
		echo "<BR>Failover [$loop1] [active] = "	. $fail[$loop1]['active'];
		echo "<BR>Failover [$loop1] [port] = "		. $fail[$loop1]['port'];
		echo "<BR>Failover [$loop1] [timeout] = "	. $fail[$loop1]['timeout'];
		echo "<BR>Failover [$loop1] [send] = "		. $fail[$loop1]['send'];
		echo "<BR>Failover [$loop1] [expect] = "	. $fail[$loop1]['expect'];
		echo "<BR>Failover [$loop1] [start_cmd] = "	. $fail[$loop1]['start_cmd'];
		echo "<BR>Failover [$loop1] [stop_cmd] = "	. $fail[$loop1]['stop_cmd'];
	}
	
	$loop1 = $loop2 = 0;
	
	while ($virt[++$loop1]['virtual'] != "" ) { /* NOTE: must use *pre*incrempent not post */
		echo "<P><B>Virtual</B>";
		echo "<BR>Virtual [$loop1] [virtual] = "	. $virt[$loop1]['virtual'];
		echo "<BR>Virtual [$loop1] [active] = "		. $virt[$loop1]['active'];
		echo "<BR>Virtual [$loop1] [port] = "		. $virt[$loop1]['port'];
		echo "<BR>Virtual [$loop1] [address] = "		. $virt[$loop1]['address'];
		echo "<BR>Virtual [$loop1] [vip_nmask] = "		. $virt[$loop1]['vip_nmask'];
		echo "<BR>Virtual [$loop1] [load_monitor] = "	. $virt[$loop1]['load_monitor'];
		echo "<BR>Virtual [$loop1] [scheduler] = "	. $virt[$loop1]['scheduler'];
		echo "<BR>Virtual [$loop1] [method] = "		. $virt[$loop1]['method'];
		echo "<BR>Virtual [$loop1] [protocol] = "	. $virt[$loop1]['protocol'];
		echo "<BR>Virtual [$loop1] [persistent] = "	. $virt[$loop1]['persistent'];
		echo "<BR>Virtual [$loop1] [pmask] = "		. $virt[$loop1]['pmask'];
		echo "<BR>Virtual [$loop1] [send] = "		. $virt[$loop1]['send'];
		echo "<BR>Virtual [$loop1] [expect] = "		. $virt[$loop1]['expect'];
		echo "<BR>Virtual [$loop1] [persistent] = "	. $virt[$loop1]['persistent'];
		echo "<BR>Virtual [$loop1] [timeout] = "	. $virt[$loop1]['timeout'];
		echo "<BR>Virtual [$loop1] [retry] = "		. $virt[$loop1]['retry'];
		echo "<BR>";
	}

	$loop1 = 1; /* reuse loop1 */
	$loop2 = 1;

	echo "<P><B>Server</B>";
	while ( $serv[$loop1][$loop2]['server'] != "" ) { 
		while ($serv[$loop1][$loop2]['server'] != "") {
			echo "<BR>Server [$loop1]:[$loop2]['server'] = "	. $serv[$loop1][$loop2]['server'];
			echo "<BR>Server [$loop1]:[$loop2]['address'] = "	. $serv[$loop1][$loop2]['address'];
			echo "<BR>Server [$loop1]:[$loop2]['nmask'] = "	. $serv[$loop1][$loop2]['nmask'];
			echo "<BR>Server [$loop1]:[$loop2]['active'] = "	. $serv[$loop1][$loop2]['active'];
			echo "<BR>Server [$loop1]:[$loop2]['weight'] = "	. $serv[$loop1][$loop2]['weight'];
			echo "<BR>";
			$loop2++;
		}
		$loop1++;
		$loop2 = 1;
	}
	echo "<HR>";

}

function write_config($level="0", $delete_virt="", $delete_item="") {
	global $fd;
	global $prim;
	global $virt;
	global $fail;
	global $serv;
	global $debug;
	
	$old_debug=$debug;
//	$debug=1;
	if ($debug) { echo "<BR>Delete array number = $delete_item from level = $level<BR>"; }

	$loop1 = $loop2 = 1;
	$loop3 = $loop4 = 1;

	$gap1 = "    ";
	$gap2 = $gap1 . $gap1;
	$egap1 = "&nbsp;&nbsp;&nbsp;&nbsp;";
	$egap2 = $egap1 . $egap1;
	
	if ($debug) { echo "<HR><B>Writing Config</B><HR><P><B>Primary</B><BR>"; };

	if ($prim['primary'] != "" ) {
		fputs ($fd, "primary = "		. $prim['primary'] . "\n", 80);
		if ($debug) { echo "primary = "		. $prim['primary'] . "<BR>"; };
	}	

	if ($prim['service'] != "" ) {
		fputs ($fd, "service = "		. $prim['service'] . "\n", 80);
		if ($debug) { echo "service = "		. $prim['service'] . "<BR>"; };
	}

	if ($prim['rsh_command'] != "" ) {
		fputs ($fd, "rsh_command = "		. $prim['rsh_command'] . "\n", 80);
		if ($debug) { echo "rsh_command = "	. $prim['rsh_command'] . "<BR>"; };
	}

	if ($prim['backup_active'] != "" ) {
		fputs ($fd, "backup_active = "		. $prim['backup_active'] . "\n", 80);
		if ($debug) { echo "backup_active = "	. $prim['backup_active'] . "<BR>"; };
	}

	if ($prim['backup'] != "" ) {
		fputs ($fd, "backup = "			. $prim['backup'] . "\n", 80);
		if ($debug) { echo "backup = "		. $prim['backup'] . "<BR>"; };
	}

	if ($prim['heartbeat'] != "" ) {
		fputs ($fd, "heartbeat = "		. $prim['heartbeat'] . "\n", 80);
		if ($debug) { echo "heartbeat = "	. $prim['heartbeat'] . "<BR>"; };
	}

	if ($prim['heartbeat_port'] != "" ) {
		fputs ($fd, "heartbeat_port = "		. $prim['heartbeat_port'] . "\n", 80);
		if ($debug) { echo "heartbeat_port = "	. $prim['heartbeat_port'] . "<BR>"; };
	}

	if ($prim['keepalive'] != "" ) {
		fputs ($fd, "keepalive = "		. $prim['keepalive'] . "\n", 80);
		if ($debug) { echo "keepalive = "	. $prim['keepalive'] . "<BR>"; };
	}

	if ($prim['deadtime'] != "" ) {
		fputs ($fd, "deadtime = "		. $prim['deadtime'] . "\n", 80);
		if ($debug) { echo "deadtime = "	. $prim['deadtime'] . "<BR>"; };
	}

	if ($prim['network'] != "" ) {
		fputs ($fd, "network = "		. $prim['network'] . "\n", 80);
		if ($debug) { echo "network = "		. $prim['network'] . "<BR>"; };
	}

	if (($prim['nat_router'] != "" ) && ($prim['nat_router'] != " " )) {
		fputs ($fd, "nat_router = "		. $prim['nat_router'] . "\n", 80);
		if ($debug) { echo "nat_router = "	. $prim['nat_router'] . "<BR>"; };
	}

	if (($prim['nat_nmask'] != "" ) && ($prim['nat_nmask'] != " " )) {
		fputs ($fd, "nat_nmask = "		. $prim['nat_nmask'] . "\n", 80);
		if ($debug) { echo "nat_nmask = "	. $prim['nat_nmask'] . "<BR>"; };
	}

	if ($prim['debug_level'] != "" ){
		fputs ($fd, "debug_level = "		. $prim['debug_level'] . "\n", 80);
		if ($debug) { echo "debug_level = "	. $prim['debug_level'] . "<BR>"; };
	}

	
	while ( $fail[$loop1]['failover'] != "" ) {
		if ((($loop1 == $delete_item ) && ($level == "1")) && ($prim['service'] == "fos")) {  $loop1++; $loop2 = 1; } else {
			if ($debug) { echo "<P><B>Failover</B><BR>"; };	


			if ($fail[$loop1]['failover'] != "") {
				fputs ($fd, "failover "				. $fail[$loop1]['failover'] . " {\n", 80);
				if ($debug) { echo "failover "			. $fail[$loop1]['failover'] . " {<BR>"; };
			}

			if ($fail[$loop1]['address'] != "") {
				fputs ($fd, "$gap1 address = "			. $fail[$loop1]['address'] . "\n", 80);
				if ($debug) { echo "$egap1 address = "		. $fail[$loop1]['address'] . "<BR>"; };
			}

			if ($fail[$loop1]['active'] != "") {
				fputs ($fd, "$gap1 active = "			. $fail[$loop1]['active'] . "\n", 80);
				if ($debug) { echo "$egap1 active = "		. $fail[$loop1]['active'] . "<BR>"; };
			}
			if ($fail[$loop1]['port'] != "") {
				fputs ($fd, "$gap1 port = "			. $fail[$loop1]['port'] . "\n", 80);
				if ($debug) { echo "$egap1 port = "		. $fail[$loop1]['port'] . "<BR>"; };
			}

			if ($fail[$loop1]['timeout'] != "") {
				fputs ($fd, "$gap1 timeout = "			. $fail[$loop1]['timeout'] . "\n", 80);
				if ($debug) { echo "$egap1 timeout = "		. $fail[$loop1]['timeout'] . "<BR>"; };
			}

			if ($fail[$loop1]['send'] != "") {
				fputs ($fd, "$gap1 send = "			. $fail[$loop1]['send'] . "\n", 80);
				if ($debug) { echo "$egap1 send = "		. $fail[$loop1]['send'] . "<BR>"; };
			}

			if ($fail[$loop1]['expect'] != "") {
				fputs ($fd, "$gap1 expect = "			. $fail[$loop1]['expect'] . "\n", 80);
				if ($debug) { echo "$egap1 expect = "		. $fail[$loop1]['expect'] . "<BR>"; };
			}

			if ($fail[$loop1]['start_cmd'] != "") {
				fputs ($fd, "$gap1 start_cmd = "		. $fail[$loop1]['start_cmd'] . "\n", 80);
				if ($debug) { echo "$egap1 start_cmd = "	. $fail[$loop1]['start_cmd'] . "<BR>"; };
			}

			if ($fail[$loop1]['stop_cmd'] != "") {
				fputs ($fd, "$gap1 stop_cmd = "			. $fail[$loop1]['stop_cmd'] . "\n", 80);
				if ($debug) { echo "$egap1 stop_cmd = "		. $fail[$loop1]['stop_cmd'] . "<BR>"; };
			}
				
			fputs ($fd,"}\n", 80);

			$loop1++;
			$loop2 = 1;
			
		}
	}
	
	while ( $virt[$loop3]['virtual'] != "" ) { 
		
		if ((($loop3 == $delete_item ) && ($level == "1"))  && ($prim['service'] != "fos")) { $loop3++; $loop4 = 1; } else {
			if ($debug) { echo "<P><B>Virtual</B><BR>"; };

			if ($virt[$loop3]['virtual'] != "") {
				fputs ($fd, "virtual "				. $virt[$loop3]['virtual'] . " {\n", 80);
				if ($debug) { echo "vitual "			. $virt[$loop3]['virtual'] . " {<BR>"; };
			}

			if ($virt[$loop3]['active'] != "") {
				fputs ($fd, "$gap1 active = "			. $virt[$loop3]['active'] . "\n", 80);
				if ($debug) { echo "$egap1 active = "		. $virt[$loop3]['active'] . "<BR>"; };
			}

			if ($virt[$loop3]['address'] != "") {
				fputs ($fd, "$gap1 address = "			. $virt[$loop3]['address'] . "\n", 80);
				if ($debug) { echo "$egap1 address "		. $virt[$loop3]['address'] . "<BR>"; };
			}

			if ($virt[$loop3]['vip_nmask'] != "") {
				fputs ($fd, "$gap1 vip_nmask = "			. $virt[$loop3]['vip_nmask'] . "\n", 80);
				if ($debug) { echo "$egap1 vip_nmask "		. $virt[$loop3]['vip_nmask'] . "<BR>"; };
			}

			if ($virt[$loop3]['port'] != "") {
				fputs ($fd, "$gap1 port = "			. $virt[$loop3]['port'] . "\n", 80);
				if ($debug) { echo "$egap1 port = "		. $virt[$loop3]['port'] . "<BR>"; };
			}

			if ($virt[$loop3]['persistent'] != "") {
				fputs ($fd, "$gap1 persistent = "		. $virt[$loop3]['persistent'] . "\n", 80);
				if ($debug) { echo "$egap1 persistent = "	. $virt[$loop3]['persistent'] . "<BR>"; };
			}

			if ($virt[$loop3]['pmask'] != "") {
				fputs ($fd, "$gap1 pmask = "			. $virt[$loop3]['pmask'] . "\n", 80);
				if ($debug) { echo "$egap1 pmask = "		. $virt[$loop3]['pmask'] . "<BR>"; };
			}

			if ($virt[$loop3]['send'] != "") {
				fputs ($fd, "$gap1 send = "			. $virt[$loop3]['send'] . "\n", 300);
				if ($debug) { echo "$egap1 send = "		. $virt[$loop3]['send'] . "<BR>"; };
			}

			if ($virt[$loop3]['expect'] != "") {
				fputs ($fd, "$gap1 expect = "			. $virt[$loop3]['expect'] . "\n", 300);
				if ($debug) { echo "$egap1 expect = "		. $virt[$loop3]['expect'] . "<BR>"; };
			}
			if ($virt[$loop3]['load_monitor'] != "") {
				fputs ($fd, "$gap1 load_monitor = "		. $virt[$loop3]['load_monitor'] . "\n", 80);
				if ($debug) { echo "$egap1 load_monitor = "	. $virt[$loop3]['load_monitor'] . "<BR>"; };
			}

			if ($virt[$loop3]['scheduler'] != "") {
				fputs ($fd, "$gap1 scheduler = "		. $virt[$loop3]['scheduler'] . "\n", 80);
				if ($debug) { echo "$egap1 scheduler = "	. $virt[$loop3]['scheduler'] . "<BR>"; };
			}

			if ($virt[$loop3]['deadtime'] != "") {
				fputs ($fd, "$gap1 deadtime = "			. $virt[$loop3]['deadtime'] . "\n", 80);
				if ($debug) { echo "$egap1 deadtime = "		. $virt[$loop3]['deadtime'] . "<BR>"; };
			}

			if ($virt[$loop3]['protocol'] != "") {
				fputs ($fd, "$gap1 protocol = "			. $virt[$loop3]['protocol'] . "\n", 80);
				if ($debug) { echo "$egap1 protocol = "		. $virt[$loop3]['protocol'] . "<BR>"; };
			}

			if ($virt[$loop3]['persistent'] != "") {
				fputs ($fd, "$gap1 persistent = "		. $virt[$loop3]['persistent'] . "\n", 80);
				if ($debug) { echo "$egap1 persistent = "	. $virt[$loop3]['persistent'] . "<BR>"; };
			}

			if ($virt[$loop3]['timeout'] != "") {
				fputs ($fd, "$gap1 timeout = "			. $virt[$loop3]['timeout'] . "\n", 80);
				if ($debug) { echo "$egap1 timeout = "		. $virt[$loop3]['timeout'] . "<BR>"; };
			}

			if ($virt[$loop3]['reentry'] != "") {
				fputs ($fd, "$gap1 reentry = "			. $virt[$loop3]['reentry'] . "\n", 80);
				if ($debug) { echo "$egap1 reentry = "		. $virt[$loop3]['reentry'] . "<BR>"; };
			}


			while ( $serv[$loop3][$loop4]['server'] != "" ) {

				if (($loop4 == $delete_item) && ($loop3 == $delete_virt) && ($level == "2"))	{ $loop4++; } else {

					if ($debug) { echo "<P><B>Server</B><BR>"; };
				
					if ($serv[$loop3][$loop4]['server'] != "" ) { 
						fputs ($fd, "$gap1 server "		. $serv[$loop3][$loop4]['server'] . " {\n", 80);
						if ($debug) { echo "$egap1 server "	. $serv[$loop3][$loop4]['server'] . " {<BR>"; };
					}
			
					if ($serv[$loop3][$loop4]['address'] != "" ) {
						fputs ($fd, "$gap2 address = "		. $serv[$loop3][$loop4]['address'] ."\n", 80);
						if ($debug) { echo "$egap2 address = "	. $serv[$loop3][$loop4]['address'] . "<BR>"; };
					}

					if ($serv[$loop3][$loop4]['nmask'] != "" ) {
						fputs ($fd, "$gap2 nmask = "		. $serv[$loop3][$loop4]['nmask'] ."\n", 80);
						if ($debug) { echo "$egap2 nmask = "	. $serv[$loop3][$loop4]['nmask'] . "<BR>"; };
					}
			
					if ($serv[$loop3][$loop4]['active'] != "" ) {
						fputs ($fd, "$gap2 active = "		. $serv[$loop3][$loop4]['active'] . "\n", 80);
						if ($debug) { echo "$egap2 active = "	. $serv[$loop3][$loop4]['active'] . "<BR>"; };
					}

					if ($serv[$loop3][$loop4]['weight'] != "" ) {
						fputs ($fd, "$gap2 weight = "		. $serv[$loop3][$loop4]['weight'] . "\n", 80);
						if ($debug) { echo "$egap2 weight = "	. $serv[$loop3][$loop4]['weight'] . "<BR>"; };
					}
				
					$loop4++;
					fputs ($fd,"$gap1 }\n", 80);
					if ($debug) { echo "$egap1 }<BR>"; }
				}
				
			}
			fputs ($fd,"}\n", 80);

			$loop3++;
			$loop4 = 1;
			
		} 
	}
	fclose($fd);
	backup_lvs();
	if ($debug) { echo "<HR>"; }
	$debug=$old_debug;
}

function open_file($mode) {
        global $fd;
	global $LVS;
	global $debug;

        $fd = @fopen($LVS, $mode);
	if ($fd == false) {
		include ("lvserror.php3");
		exit;
	}
		
        rewind($fd); /* unnessecary but I'm paranoid */
}

function add_failover() {

	global $fail;
	$loop2=1;	

	/* find end of existing data */
	while ($fail[$loop2]['failover'] != "" ) { $loop2++; }
	
	$fail[$loop2]['failover']	= "[server_name]";
	$fail[$loop2]['address']	= "0.0.0.0 eth0:1";
	$fail[$loop2]['active']		= "0";
	$fail[$loop2]['timeout']	= "6";
	$fail[$loop2]['port']		= "80";
	$fail[$loop2]['send']		= "\"GET / HTTP/1.0\\r\\n\\r\\n\"";
	$fail[$loop2]['expect']		= "\"HTTP\"";	
	$fail[$loop2]['start_cmd']	= "\"/etc/rc.d/init.d/httpd start\"";
	$fail[$loop2]['stop_cmd']	= "\"/etc/rc.d/init.d/httpd stop\"";

	open_file("w+"); write_config(""); /* umm save this quick to file */
}

function add_virtual() {

	global $virt;
	$loop2=1;	

	/* find end of existing data */
	while ($virt[$loop2]['virtual'] != "" ) { $loop2++; }

	$virt[$loop2]['virtual']	= "[server_name]";
	$virt[$loop2]['address']	= "0.0.0.0 eth0:1";
	$virt[$loop2]['active']		= "0";
	$virt[$loop2]['port']		= "80";
	$virt[$loop2]['load_monitor']	= "rup";
	$virt[$loop2]['protocol']	= "tcp";
	$virt[$loop2]['persistent']	= "";
	$virt[$loop2]['pmask']		= "";
	$virt[$loop2]['send']		= "\"GET / HTTP/1.0\\r\\n\\r\\n\"";
	$virt[$loop2]['expect']		= "\"HTTP\"";	
	$virt[$loop2]['scheduler']	= "wlc";
	$virt[$loop2]['timeout']	= "6";
	$virt[$loop2]['reentry']	= "15";

	open_file("w+"); write_config(""); /* umm save this quick to file */
}

function add_service($virt_idx) {

	global $serv;
	
	$loop2=1;

	/* find end of existing data */
	while ($serv[$virt_idx][$loop2]['server'] != "" ) { $loop2++; }

	/* Insert default record */
	$serv[$virt_idx][$loop2]['server']		= "[unnamed]";
	$serv[$virt_idx][$loop2]['address']		= "0.0.0.0";
	$serv[$virt_idx][$loop2]['active']		= "0";
	$serv[$virt_idx][$loop2]['weight']		= "1";

	open_file("w+"); write_config(""); /* umm save this quick to file */;

}


/* -- Main action (open the config file and initialize a set of arrays with the config ------- */
open_file("r+"); /* uses global file descriptor */

read_config();
fclose($fd);

if ($debug) { print_arrays(); };

?>
