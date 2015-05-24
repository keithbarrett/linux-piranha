<?
	if ($control_action == "CHANGE PASSWORD") {
		header("Location: passwd.php3");
		exit;
	}
		
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
	header("Pragma: no-cache");                                   // HTTP/1.0

	require('parse.php3'); /* read in the config! Hurragh! */
	if ($auto_update == "1") {
		echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"$rate;control.php3\"> ";
	}
	if ($prim['service'] == "") {
		$prim['service'] = "lvs";
	}

?>


<HTML>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML Strict Level 3//EN">

<HEAD>
<TITLE>Piranha (Control/Monitoring)</TITLE>
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


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
	<TR BGCOLOR="#CC0000"> <TD CLASS="logo"> <B>PIRANHA</B> CONFIGURATION TOOL </TD>
	<TD ALIGN=right CLASS="logo">
            <a href="../docs.html" class="logolink">
            DOCUMENTATION</a> |
            <A HREF="introduction.html" CLASS="logolink">
            INTRODUCTION</A> | <A HREF="help.php3" CLASS="logolink">
            HELP</A></TD>
	</TR>
</TABLE>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
        <TR>
                <TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">CONTROL / MONITORING</FONT><BR>&nbsp;</TD>
        </TR>
</TABLE>


<?
	// echo "Query = $QUERY_STRING";
?>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="0"><TR><TD BGCOLOR="#FFFFFF">

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
                <TD WIDTH="25%" ALIGN="CENTER" BGCOLOR="#FFFFFF"> <A HREF="control.php3" NAME="Control/Monitoring" CLASS="tabon"><B>CONTROL/MONITORING</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="global_settings.php3" NAME="Global Settings" CLASS="taboff"><B>GLOBAL SETTINGS</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="redundancy.php3" NAME="Redundancy" CLASS="taboff"><B>REDUNDANCY</B></A> </TD>
		<? if ($prim['service'] == "fos") { ?>
			<TD WIDTH="25%" ALIGN="CENTER"> <A HREF="failover_main.php3" NAME="Failover" CLASS="taboff"><B>FAILOVER</B></A> </TD>
		<? } else { ?>
			<TD WIDTH="25%" ALIGN="CENTER"> <A HREF="virtual_main.php3" NAME="Virtual" CLASS="taboff"><B>VIRTUAL SERVERS</B></A> </TD>
		<? } ?>
        </TR>

</TABLE>

<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="control.php3">
	<P>
	<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD CLASS="title">CONTROL</TD>
        </TR>


	<TR> <TD BGCOLOR="#EEEEEE">
	<?
		$pid = `/etc/rc.d/init.d/pulse status` ;
		
		if (empty($daemon))  {
			if ((strstr($pid,"stopped")) || (strstr($pid,"dead"))) {
				$daemon="STOP";
			} else {
				$daemon="START";
			}
		}

		if ($daemon == "START") { 
			echo "Daemon: <FONT COLOR=\"green\"> running </FONT>";
			if (strstr($pid,"stopped")) {
				echo `/etc/rc.d/init.d/pulse start > /dev/null` ;
			}
		}
		if ($daemon == "STOP")  {
			echo "Daemon: <FONT COLOR=\"#cc0000\"> stopped </FONT>";
			if (!strstr($pid,"stopped")) {
				echo `/etc/rc.d/init.d/pulse stop > /dev/null`  ;
			}
		}
	?>
		</TD> </TR> </TABLE>
		<BR>
	<P>

	<P><!-- 
		<INPUT TYPE="submit" NAME="resync" VALUE="  Shutdown LVS  ">
		<!-- Sudo not allowed, ripped this bit out
		<-?
			echo "The pulse daemon is currently";
			if ($check_pulse == "1") { 
				echo "<FONT COLOR=\"green\"><B>added </B></FONT> to these run levels<BR>";
				echo `/usr/bin/sudo /sbin/chkconfig --level 2 pulse on` ;
				echo `/usr/bin/sudo /sbin/chkconfig --level 3 pulse on` ;
				echo `/usr/bin/sudo /sbin/chkconfig --level 4 pulse on` ;
				echo `/usr/bin/sudo /sbin/chkconfig --level 5 pulse on` ;
			}
			if ($check_pulse == "" ) { 
				echo "<FONT COLOR=\"red\"> <B>deleted</B></FONT> from these run levels<BR>";
				echo `/usr/bin/sudo /sbin/chkconfig --del pulse` ;
			}
			echo `/usr/bin/sudo /sbin/chkconfig --list pulse` ;
		?->
		<BR> <INPUT TYPE="CHECKBOX" NAME="check_pulse" VALUE="1" <? if ($check_pulse == 1) { echo "CHECKED"; } ?>>
		Start pulse on system reboot <P>	
		Manually stop/start pulse <BR>
		<INPUT TYPE="SUBMIT" NAME="daemon" VALUE="STOP">
		<INPUT TYPE="SUBMIT" NAME="daemon" VALUE="START">
		
	<HR>
	-->

	<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD CLASS="title">MONITOR</TD>
        </TR>
	</TABLE>
	
	
	<INPUT TYPE="CHECKBOX" NAME="auto_update" VALUE="1" <? if ($auto_update == 1) { echo "CHECKED"; } ?> > Auto update
	<INPUT TYPE="TEXT" NAME="rate" SIZE=3 VALUE=
		<? 
			if (($auto_update == "1") && ($rate == "")) {
				$rate="10" ;
			}
			if ($rate < 10) { $rate=10; }
			echo $rate ;
			
		?>
	> update frequency in seconds<BR>
	Rates lower than 10 seconds are not recommended as, when the page updates, you will lose any<BR>
	modifications you have made which have not been actioned using the 'Accept' button<P>
	<INPUT TYPE="SUBMIT" NAME="refresh" VALUE="Update information now">
	<BR><P>
	<HR>


<? if ( $prim['service'] == "lvs" ) { ?>
	<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD CLASS="title">CURRENT LVS ROUTING TABLE</TD>
        </TR>
	</TABLE>
	
	<TABLE WIDTH="100%" BGCOLOR="#eeeeee"> <TR> <TD>
	<PRE>

	<?
		##########################################################################
		# Dump out the display of ipvsadm into a scrollable text window
		# 12 lines should be enough, but this can be manually altered here to suit
		#
		echo `/usr/sbin/ipvsadm -Ln` ;
	?>
	</PRE>
	</TD> </TR> </TABLE>
	
	<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD CLASS="title">CURRENT LVS PROCESSES</TD>
        </TR>
	</TABLE>
	
	<TABLE WIDTH="100%" BGCOLOR="#eeeeee"> <TR> <TD>
	<PRE><? echo `/bin/ps auxw | /bin/egrep "pulse|lvs|send_arp|nanny|fos|ipvs" | /bin/grep -v grep`; ?></PRE>
	&nbsp;	
	</TD> </TR> </TABLE>


<? } else { ?>

	<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD CLASS="title">CURRENT FOS PROCESSES</TD>
        </TR>
	</TABLE>

	<TABLE WIDTH="100%" BGCOLOR="#eeeeee"> <TR> <TD>
	<PRE><? echo `/bin/ps auxw | /bin/egrep "pulse|lvs|send_arp|nanny|fos|ipvs" | /bin/grep -v grep`; ?></PRE>
	&nbsp;	
	</TD> </TR> </TABLE>

	
<? } ?>

	<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5" >
 		<TR BGCOLOR="#666666">
 
               	<!-- Start of comment out
		<TD>
                        <INPUT TYPE="Submit" NAME="control_action" VALUE="ACCEPT"> <SPAN CLASS="taboff"> -- Click here to apply changes to this page</SPAN>
                </TD>
		End of comment -->
		<TD ALIGN=right>
			<INPUT TYPE="Submit" NAME="control_action" VALUE="CHANGE PASSWORD"> <SPAN CLASS="taboff">
		</TD>
       		</TR>
	</TABLE>
	
</FORM>
</TD></TR></TABLE>
</BODY>
</HTML>
