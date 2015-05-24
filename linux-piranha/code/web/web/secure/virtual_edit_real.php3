<?
	if ($real_service == "CANCEL") {
		header("Location: virtual_edit_virt.php3?selected_host=$selected_host");	/* Redirect browser to editing page */
		exit;  						/* Make sure that code below does not get executed when we redirect. */
	}

	/* Some magic used to allow the edit command to pull up another web page */
	if ($real_service == "EDIT") {
		header("Location: virtual_edit_real_edit.php3?selected_host=$selected_host&selected=$selected");	/* Redirect browser to editing page */
		exit;  							/* Make sure that code below does not get executed when we redirect. */
	}
	
	/* try and make this page non cacheable */
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
	header("Pragma: no-cache");                                   // HTTP/1.0
	
	require('parse.php3');

	if ($real_service == "ADD") {
		add_service($selected_host);
	}

	if ($real_service == "DELETE") {
		if ($debug) { echo "About to delete entry number $selected_host<BR>"; }
		echo "<HR><H2>Click <A HREF=\"virtual_edit_real.php3?selected_host=$selected_host\" NAME=\"Virtual\">HERE</A></TD> for refresh</H2><HR>";
		open_file("w+");
		write_config("2", $selected_host, $selected);
		exit;
	}

	if ($real_service == "(DE)ACTIVATE") {
		switch ($serv[$selected_host][$selected]['active']) {
			case	""	:	$serv[$selected_host][$selected]['active'] = "0"; break;
			case	"0"	:	$serv[$selected_host][$selected]['active'] = "1"; break;
			case	"1"	:	$serv[$selected_host][$selected]['active'] = "0"; break;
			default		:	$serv[$selected_host][$selected]['active'] = "0"; break;
		}
	}

	/* Umm,... just in case someone is dumb enuf to fiddle */
	if (empty($selected_host)) { $selected_host=1; }

?>
<HTML>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML Strict Level 3//EN">

<HEAD>
<TITLE>Piranha (Virtual Servers - Editing real server)</TITLE>
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
                <TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">EDIT REAL SERVER</FONT><BR>&nbsp;</TD>
        </TR>
</TABLE>


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="0"><TR><TD BGCOLOR="#FFFFFF">


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="control.php3" NAME="Control/Monitoring" CLASS="taboff"><B>CONTROL/MONITORING</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="global_settings.php3" NAME="Global Settings" CLASS="taboff"><B>GLOBAL SETTINGS</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="redundancy.php3" NAME="Redundancy" CLASS="taboff"><B>REDUNDANCY</B></A> </TD>
		<TD WIDTH="25%" ALIGN="CENTER" BGCOLOR="#FFFFFF"> <A HREF="virtual_main.php3" NAME="Virtual" CLASS="tabon"><B>VIRTUAL SERVERS</B></A> </TD>
        </TR>
</TABLE>
<?
	// echo "Query = $QUERY_STRING";

?>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
        <TR BGCOLOR="#EEEEEE">
                <TD WIDTH="60%">EDIT:
		
		<A HREF="virtual_edit_virt.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " NAME="VIRTUAL SERVER">VIRTUAL SERVER</A>
		&nbsp;|&nbsp;

                <A HREF="virtual_edit_real.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " CLASS="tabon" NAME="REAL SERVER">REAL SERVER</A>
		&nbsp;|&nbsp;

                <A HREF="virtual_edit_services.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " NAME="MONITORING SCRIPTS">MONITORING SCRIPTS</A></TD>

		<!-- <TD WIDTH="30%" ALIGN="RIGHT"><A HREF="virtual_main.php3">MAIN PAGE</A></TD> -->
        </TR>
</TABLE>

<P>

<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="virtual_edit_real.php3">

<TABLE WIDTH="70%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
	<TR>
		<TD CLASS="title">&nbsp;</TD>
		<TD CLASS="title">STATUS</TD>
		<TD CLASS="title">NAME</TD>
		<TD CLASS="title">ADDRESS</TD>
<?//		<TD CLASS="title">NETMASK</TD> </?>
	</TR>

<!-- Somehow dynamically generated here -->
	

	<?
	/* magic */
	echo "<INPUT TYPE=HIDDEN NAME=virtual VALUE=$selected_host>";

	$loop=1;
	while ($serv[$selected_host][$loop]['server'] != "" ) {
		echo "<TR>";
		echo "<TD><INPUT TYPE=RADIO NAME=selected VALUE=" . $loop; if ($selected == "" ) { $selected = 1; }; if ($loop == $selected) { echo " CHECKED"; }; echo "></TD>";
		echo "<TD><INPUT TYPE=HIDDEN NAME=active COLS=6 VALUE=";
			switch ($serv[$selected_host][$loop]['active']) {
				case "0"	:	echo "down><FONT COLOR=red>Down</FONT>";	break;
				case "1"	:	echo "up><FONT COLOR=blue>up</FONT>";		break;
				case "2"	:	echo "Active><FONT COLOR=green></FONT>";	break;
				default		:	$serv[$selected_host][$loop]['active'] = "0";
							echo "down><FONT COLOR=red></FONT>";		break;
			}
				
		echo "</TD>";
		echo "<TD><INPUT TYPE=HIDDEN NAME=name COLS=6 VALUE=";		echo $serv[$selected_host][$loop]['server']	. ">";
		echo $serv[$selected_host][$loop]['server']	. "</TD>";
		echo "<TD><INPUT TYPE=HIDDEN NAME=address COLS=10 VALUE=";	echo $serv[$selected_host][$loop]['address']	. ">";
		echo $serv[$selected_host][$loop]['address']	. "</TD>";
//		if (empty($serv[$selected_host][$loop]['nmask']) || $serv[$selected_host][$loop]['nmask'] == "0.0.0.0") {
//			echo "<TD>Unused</TD>";
//		} else {
//			echo "<TD><INPUT TYPE=HIDDEN NAME=netmask COLS=10 VALUE=";	echo $serv[$selected_host][$loop]['nmask']	. ">";
//			echo $serv[$selected_host][$loop]['nmask']	. "</TD>";
//		}
		echo "</TR>";
	
	$loop++;
	}
	echo "</TABLE>";

	?>
	

<!-- end of dynamic generation -->



<!-- should align beside the above table -->

<TABLE>
		<TR>
			<TD><INPUT TYPE="SUBMIT" NAME="real_service" VALUE="ADD"></TD>
			<TD><INPUT TYPE="SUBMIT" NAME="real_service" VALUE="DELETE"></TD>
			<TD><INPUT TYPE="SUBMIT" NAME="real_service" VALUE="EDIT"></TD>
			<TD><INPUT TYPE="SUBMIT" NAME="real_service" VALUE="(DE)ACTIVATE"></TD>
		</TR>
</TABLE>


<? echo "<INPUT TYPE=HIDDEN NAME=selected_host VALUE=$selected_host>" ?>

	<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5" BGCOLOR="#666666"> 
		<TR> 
			<TD ALIGN="right">
				<INPUT TYPE="SUBMIT" NAME="real_service" VALUE="CANCEL">
			</TD>
		</TR>
	</TABLE>


<? open_file ("w+"); write_config(""); ?>

</FORM>
</TD> </TR> </TABLE>
</BODY>
</HTML>
