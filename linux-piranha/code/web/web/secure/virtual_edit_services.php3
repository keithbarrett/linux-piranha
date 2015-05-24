<?
	if ($gen_service == "CANCEL") {
		header("Location: virtual_edit_virt.php3?selected_host=$selected_host");	/* Redirect browser to editing page */
		exit;  						/* Make sure that code below does not get executed when we redirect. */	
	}

	/* try and make this page non cacheable */
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
	header("Pragma: no-cache");                                   // HTTP/1.0

	require('parse.php3'); /* read in the config! Hurragh! */

	// print_arrays(); /* before */

	// print_arrays(); /* after */

?>
<HTML>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML Strict Level 3//EN">

<HEAD>
<TITLE>Piranha (Virtual Servers - MONITORING SCRIPTS)</TITLE>
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
                <TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">EDIT MONITORING SCRIPTS</FONT><BR>&nbsp;</TD>
        </TR>
</TABLE>


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="0"><TR><TD BGCOLOR="#FFFFFF">


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="control.php3" NAME="Control/Monitoring" CLASS="taboff"><B>CONTROL/MONITORING</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="global_settings.php3" NAME="Global Settings" CLASS="taboff"><B>GLOBAL SETTINGS</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="redundancy.php3" NAME="Redundancy" CLASS="taboff"><B>REDUNDANCY</B></A> </TD>
		<? if ($prim['service'] == "fos") { ?>
			<TD WIDTH="25%" ALIGN="CENTER" BGCOLOR="#ffffff"> <A HREF="failover_main.php3" NAME="Failover" CLASS="tabon"><B>FAILOVER</B></A> </TD>
		<? } else { ?>
			<TD WIDTH="25%" ALIGN="CENTER" BGCOLOR="#ffffff"> <A HREF="virtual_main.php3" NAME="Virtual" CLASS="tabon"><B>VIRTUAL SERVERS</B></A> </TD>
		<? } ?>
        </TR>
</TABLE>
<?
	// echo "Query = $QUERY_STRING";

?>


<? if ($prim['service'] == "fos") { ?>
<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
        <TR BGCOLOR="#EEEEEE">
                <TD WIDTH="60%">EDIT:
		
		<A HREF="failover_edit_virt.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " NAME="FAILOVER">FAILOVER</A>
		&nbsp;|&nbsp;

                <A HREF="virtual_edit_services.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> "  CLASS="tabon" NAME="MONITORING SCRIPTS">MONITORING SCRIPTS</A></TD>

		<!-- <TD WIDTH="30%" ALIGN="RIGHT"><A HREF="virtual_main.php3">MAIN PAGE</A></TD> -->
        </TR>
</TABLE>

<? } else { ?>
<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
        <TR BGCOLOR="#EEEEEE">
                <TD WIDTH="60%">EDIT:
		
		<A HREF="virtual_edit_virt.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " NAME="VIRTUAL SERVER">VIRTUAL SERVER</A>
		&nbsp;|&nbsp;

                <A HREF="virtual_edit_real.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " NAME="REAL SERVER">REAL SERVER</A>
		&nbsp;|&nbsp;

                <A HREF="virtual_edit_services.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " CLASS="tabon" NAME="MONITORING SCRIPTS">MONITORING SCRIPTS</A></TD>

		<!-- <TD WIDTH="30%" ALIGN="RIGHT"><A HREF="virtual_main.php3">MAIN PAGE</A></TD> -->
        </TR>
</TABLE>
<? } ?>
<?
	// echo "Query = $QUERY_STRING";
	// echo "<PRE>[" . $virt[$selected_host]['send'] . "] [$send]</PRE><BR>";
	// echo "<PRE>[" . $virt[$selected_host]['expect'] . "] [$expect]</PRE><BR>";
	
	/* php escapes \n to \\n ! argh! { fortunately there is stripslashes(); } */
	$send = stripslashes($send);
	$expect = stripslashes($expect);
	$start_cmd = stripslashes($start_cmd);
	$stop_cmd = stripslashes($stop_cmd);

if ($prim['service'] == "fos") {
	if ($gen_service == "ACCEPT") {
	
		/* take values and enclose them in quotes */
		if (!empty($send))	{
			$send		= "\"" . $send . "\"";
			$fail[$selected_host]['send'] = $send;
		}

		if (!empty($expect)) {
			$expect	= "\"" . $expect . "\"";
			$fail[$selected_host]['expect'] = $expect;
		}

		if (!empty($start_cmd )) {
			$start_cmd	= "\"" . $start_cmd . "\"";
			$fail[$selected_host]['start_cmd'] = $start_cmd;
		}

		if (!empty($stop_cmd)) {
			$stop_cmd	= "\"" . $stop_cmd . "\"";
			$fail[$selected_host]['stop_cmd'] = $stop_cmd;
		}
	}

	
	if ($gen_service == "BLANK SEND") {
		$send = "";
		if (!empty($selected_host)) {
			$fail[$selected_host]['send'] = "";

		}
	}
	if ($gen_service == "BLANK EXPECT") {
		$expect = "";
		if (!empty($selected_host)) {
			$fail[$selected_host]['expect'] = "";
		}
	}
	if ($gen_service == "BLANK START COMMAND") {
		$start_cmd = "";
		if (!empty($selected_host)) {
			$fail[$selected_host]['start_cmd'] = "";
		}
	}	
	if ($gen_service == "BLANK STOP COMMAND") {
		$stop_cmd = "";
		if (!empty($selected_host)) {
			$fail[$selected_host]['stop_cmd'] = "";
		}
	}	
} else {
	if ($gen_service == "ACCEPT") {
	
		/* take values and enclose them in quotes */
		if (!empty($send))	{
			$send		= "\"" . $send . "\"";
			$virt[$selected_host]['send'] = $send;
		}

		if (!empty($expect)) {
			$expect	= "\"" . $expect . "\"";
			$virt[$selected_host]['expect'] = $expect;
		}

	}

	if ($gen_service == "BLANK SEND") {
		$send = "";
		if (!empty($selected_host)) {
			$virt[$selected_host]['send'] = "";

		}
	}
	if ($gen_service == "BLANK EXPECT") {
		$expect = "";
		if (!empty($selected_host)) {
			$virt[$selected_host]['expect'] = "";
		}
	}
	
}


?>


<P>
<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="virtual_edit_services.php3">


<? if ($prim['service'] == "fos") { ?>
<HR>
<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">

	<TR>
		<TD CLASS="title">&nbsp;</TD>
		<TD CLASS="title">Current text</TD>
		<TD CLASS="title">Replacement text</TD>
		<TD CLASS="title"></TD>
	</TR>
	</TR>
	<? 	
		echo "<TD CLASS=title>Send:</TD>";
		echo "<TD VALIGN=center><PRE>" . $fail[$selected_host]['send'] . "</PRE></TD>";
		echo "<TD><INPUT NAME=send SIZE=30 MAXLENGTH=255 VALUE=" . $fail[$selected_host]['send'] . "></TD>";
		echo "<TD><INPUT TYPE=\"SUBMIT\" NAME=\"gen_service\" VALUE=\"BLANK SEND\"></TD>";
		echo "</TR>";

		echo "<TD CLASS=title>Expect:&nbsp;&nbsp;</TD>";	
		echo "<TD VALIGN=middle><PRE>" . $fail[$selected_host]['expect'] . "</PRE></TD>";
		echo "<TD><INPUT TYPE=TEXT NAME=expect SIZE=30 MAXLENGTH=255 VALUE=" . $fail[$selected_host]['expect'] . "></TD>";
		echo "<TD><INPUT TYPE=\"SUBMIT\" NAME=\"gen_service\" VALUE=\"BLANK EXPECT\"></TD>";
		echo "</TR>";
	
	?>

	<TR>
		<TD CLASS="title">&nbsp;</TD>
		<TD CLASS="title">Current command</TD>
		<TD CLASS="title">Replacement command</TD>
		<TD CLASS="title"></TD>
	</TR>

	<? 	
		echo "<TD CLASS=title>Start command:</TD>";
		echo "<TD VALIGN=center><PRE>" . $fail[$selected_host]['start_cmd'] . "</PRE></TD>";
		echo "<TD><INPUT NAME=start_cmd SIZE=30 MAXLENGTH=255 VALUE=" . $fail[$selected_host]['start_cmd'] . "></TD>";
		echo "<TD><INPUT TYPE=\"SUBMIT\" NAME=\"gen_service\" VALUE=\"BLANK START COMMAND\"></TD>";
		echo "</TR>";

		echo "<TD CLASS=title>Stop command:&nbsp;&nbsp;</TD>";	
		echo "<TD VALIGN=middle><PRE>" . $fail[$selected_host]['stop_cmd'] . "</PRE></TD>";
		echo "<TD><INPUT TYPE=TEXT NAME=stop_cmd SIZE=30 MAXLENGTH=255 VALUE=" . $fail[$selected_host]['stop_cmd'] . "></TD>";
		echo "<TD><INPUT TYPE=\"SUBMIT\" NAME=\"gen_service\" VALUE=\"BLANK STOP COMMAND\"></TD>";
		echo "</TR>";
	
	?>
</TABLE>
<? } else { ?>
<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">

	<TR>
		<TD CLASS="title">&nbsp;</TD>
		<TD CLASS="title">Current text</TD>
		<TD CLASS="title">Replacement text</TD>
		<TD CLASS="title"></TD>
	</TR>

	<? 	
		echo "<TD CLASS=title>Send:</TD>";
		echo "<TD VALIGN=center><PRE>" . $virt[$selected_host]['send'] . "</PRE></TD>";
		echo "<TD><INPUT NAME=send SIZE=30 MAXLENGTH=255 VALUE=" . $virt[$selected_host]['send'] . "></TD>";
		echo "<TD><INPUT TYPE=\"SUBMIT\" NAME=\"gen_service\" VALUE=\"BLANK SEND\"></TD>";
		echo "</TR>";

		echo "<TD CLASS=title>Expect:&nbsp;&nbsp;</TD>";	
		echo "<TD VALIGN=middle><PRE>" . $virt[$selected_host]['expect'] . "</PRE></TD>";
		echo "<TD><INPUT TYPE=TEXT NAME=expect SIZE=30 MAXLENGTH=255 VALUE=" . $virt[$selected_host]['expect'] . "></TD>";
		echo "<TD><INPUT TYPE=\"SUBMIT\" NAME=\"gen_service\" VALUE=\"BLANK EXPECT\"></TD>";
		echo "</TR>";
	
	?>
</TABLE>
<? } ?>
<TABLE>
	<TR>
		<TD VALIGN=TOP>Please note:</TD>
		<TD> message strings are limited to a maximum of 255 chars. Characters must be typical printable characters.
		     No binary, hex notation, or escaped characters. Case IS important! Also no wildcards are supported.
		     Additionally, at this time you are limited to, at most, one send and one expect entry</TD>
	</TR>
</TABLE>

<!-- should align beside the above table -->

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" BGCOLOR="#666666">
		<TR>
			<TD><INPUT TYPE="SUBMIT" NAME="gen_service" VALUE="ACCEPT"></TD>
			<TD ALIGN=right><INPUT TYPE="SUBMIT" NAME="gen_service" VALUE="CANCEL"></TD>
		</TR>
</TABLE>


<? echo "<INPUT TYPE=HIDDEN NAME=selected_host VALUE=$selected_host>" ?>

<? 
	open_file ("w+"); write_config("");
?>

</FORM>
</TD></TR></TABLE>
</BODY>
</HTML>

