<?
	/* try and make this page non cacheable */
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
	header("Pragma: no-cache");                                   // HTTP/1.0

	require('parse.php3'); /* read in the config! Hurragh! */
	// print_arrays(); /* before */



?>
<HTML>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML Strict Level 3//EN">

<HEAD>
<TITLE>Piranha (Redundancy)</TITLE>
<STYLE TYPE="text/css">
<!-- 

TD      {
        font-family: helvetica, sans-serif;
        }
        
.logo   {
        color: #FFFFFF;
        }
        
A.logolink      {
        color: #FFFFFF;
        font-size: .8em;
        }
        
.taboff {
        color: #FFFFFF;
        }
        
.tabon  {
        color: #999999;
        }
        
.title  {
        font-size: .8em;
        font-weight: bold;
        color: #660000;
        }
        
.smtext {
        font-size: .8em;
        }
        
.green  {
        color: 

// -->
</STYLE>

</HEAD>
<BODY BGCOLOR="#660000">

<?

	$prim['heartbeat'] = 1; /* argh! - permently set afaik */

	// echo "[$redundancy_action] [$enable] [$redundant] [$hb_interval] [$dead_after] [$hb_port]<BR>";
	if ($redundancy_action == "ACCEPT") {
		$prim['backup'] 		= $redundant;
		$prim['keepalive'] 		= $hb_interval;
		$prim['deadtime'] 		= $dead_after;
		$prim['heartbeat_port'] 	= $hb_port;

	}

	if ($prim['backup_active'] == "") {
		$prim['backup_active'] = 0;
	}		

	if (($enable == "1") || ($enable == "0")) {
		$prim['backup_active']		= (1 - $enable) ;
	}

	if ($prim['backup'] == "") { $prim['backup'] = "0.0.0.0"; }
	if ($prim['keepalive'] == "") { $prim['keepalive'] = "6"; }
	if ($prim['deadtime'] == "") { $prim['deadtime'] = "18"; }
	if ($prim['heartbeat_port'] == "") { $prim['heartbeat_port'] = "539";}

	if ($redundancy_action == "RESET" ) {
		$prim['backup'] = "0.0.0.0";
		$prim['keepalive'] = "6";
		$prim['deadtime'] = "18";
		$prim['heartbeat_port'] = "539";
	}
	
	// echo "Query = $QUERY_STRING";

	if ($full_enable=="ENABLE") { $prim['backup_active'] = "1"; }
	if ($redundancy_action=="DISABLE") { $prim['backup_active'] = "0"; }
?>


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
	<TR BGCOLOR="#CC0000"> <TD CLASS="logo"> <B>PIRANHA</B> CONFIGURATION TOOL </TD>
	<TD ALIGN=right CLASS="logo">
              <a href="../docs.html class="logolink">
              DOCUMENTATION</a> | 
              <A HREF="introduction.html" CLASS="logolink">
              INTRODUCTION</A> | <A HREF="help.php3" CLASS="logolink">
              HELP</A></TD>
	</TR>
</TABLE>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
        <TR>
                <TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">REDUNDANCY</FONT><BR>&nbsp;</TD>
        </TR>
</TABLE>


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="0"><TR><TD BGCOLOR="#FFFFFF">


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="control.php3" NAME="Control/Monitoring" CLASS="taboff"><B>CONTROL/MONITORING</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="global_settings.php3" NAME="Global Settings" CLASS="taboff"><B>GLOBAL SETTINGS</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER" BGCOLOR="#FFFFFF"> <A HREF="redundancy.php3" NAME="Redundancy" CLASS="tabon"><B>REDUNDANCY</B></A> </TD>
		<? if ($prim['service'] == "fos") { ?>
			<TD WIDTH="25%" ALIGN="CENTER"> <A HREF="failover_main.php3" NAME="Failover" CLASS="taboff"><B>FAILOVER</B></A> </TD>
		<? } else { ?>
			<TD WIDTH="25%" ALIGN="CENTER"> <A HREF="virtual_main.php3" NAME="Virtual" CLASS="taboff"><B>VIRTUAL SERVERS</B></A> </TD>
		<? } ?>
        </TR>
</TABLE>

<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="redundancy.php3">
	


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD BGCOLOR="#EEEEEE" COLSPAN="2">Backup: 
        

	<?
		if ($prim['backup_active'] == "1") {
			echo "<FONT COLOR=green>active</FONT>";
		} else {
			echo "<FONT COLOR=\"#cc0000\">inactive</FONT>";
		}
	?> 
	</TD> </TR> </TABLE>
<P>

<? 
	if ($full_enable=="ENABLE") { $prim['backup_active'] = "1"; };
	if ($prim['backup_active'] == "1") { ?>
	<P>

	<HR>
	<TABLE>	
		<TR>
		<TD> Redundant server IP:</TD> <TD> <INPUT TYPE="TEXT" NAME="redundant" SIZE=16 VALUE=
			<?
			if ($prim['backup'] != "") {
				echo $prim['backup'];
			};
			echo ">";
			?>
		</TD></TR>
			<TR><TD COLSPAN="3"> <HR SIZE="1" WIDTH="100%" NOSHADE></TD>
		</TD></TR>
	
		<TR>
			<TD>Heartbeat interval (seconds):</TD>
			<TD><INPUT TYPE="TEXT" NAME="hb_interval" SIZE=5 VALUE=
			<?
			if ($prim['keepalive'] != "") {
				echo $prim['keepalive'];
			};
			echo ">";
			?>

			</TD>
		</TR>
		<TR>
			<TD>Assume dead after (seconds):</TD>
			<TD><INPUT TYPE="TEXT" NAME="dead_after" SIZE=5 VALUE=
			<?
			if ($prim['deadtime'] != "") {
				echo $prim['deadtime'];
			};
			echo ">";
			?>
			</TD>
		</TR>
		<TR>
			<TD>Heartbeat runs on port:</TD>
			<TD><INPUT TYPE="TEXT" NAME="hb_port" SIZE=5 VALUE=
			<?
			if ($prim['heartbeat_port'] != "") {
				echo $prim['heartbeat_port'];
			};
			echo ">";
			?>
			</TD>
		</TR>
	</TABLE>
	<HR>
<? } ?>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
        <TR BGCOLOR="#666666">
		<? if ( $prim['backup_active'] == "0" ) { ?>
			<TD><INPUT TYPE="submit" NAME="full_enable" VALUE=ENABLE></TD>
			<!-- <TD ALIGN=right ><INPUT TYPE="submit" NAME="enable" VALUE=DISABLE></TD> -->
		<? } else { ?>
			<TD><INPUT TYPE="Submit" NAME="redundancy_action" VALUE="ACCEPT">  <SPAN CLASS="taboff">-- Click here to apply changes to this page</SPAN></TD>
			<TD ALIGN=right><SPAN CLASS="taboff"></SPAN><INPUT TYPE="Submit" NAME="redundancy_action" VALUE="DISABLE"><INPUT TYPE="Submit" NAME="redundancy_action" VALUE="RESET">
		<? } ?>
        </TR>
</TABLE>


<? open_file ("w+"); write_config(""); ?>

</TD></TR></TABLE>
</FORM>
</BODY>
</HTML>
