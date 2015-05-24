<?

	if ($edit_action == "CANCEL") {
		header("Location: virtual_edit_real.php3?selected_host=$selected_host&selected=$selected");		
		exit;
	}
	
	/* try and make this page non cacheable */
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
	header("Pragma: no-cache");                                   // HTTP/1.0

	require('parse.php3');


		
	if ($edit_action == "ACCEPT") {
		if ($name == "")	{ $name = $serv[$selected_host][$selected]['server']; } else { $serv[$selected_host][$selected]['server'] = str_replace(" ", "_", $name); }
		$serv[$selected_host][$selected]['address']	= $address;

		if ($nmask != "Unused" ) {
			$serv[$selected_host][$selected]['nmask']		=	$nmask;
		} else {
			$serv[$selected_host][$selected]['nmask']		=	"";
		}
		
		$serv[$selected_host][$selected]['weight']	= $weight;
	}

?>
<HTML>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML Strict Level 3//EN">

<HEAD>
<TITLE>Piranha (Virtual servers - Editing virtual server - Editing real server)</TITLE>
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

<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="virtual_edit_real_edit.php3">

	<TABLE>
		<TR>
			<TD>Name: </TD>
			<TD><INPUT TYPE="TEXT" NAME="name" VALUE=<? echo $serv[$selected_host][$selected]['server'] ?>></TD>
		</TR>
		<TR>
			<TD>Address: </TD>
			<TD><INPUT TYPE="TEXT" NAME="address" VALUE=<? echo $serv[$selected_host][$selected]['address'] ?>></TD>
		</TR>
<!--		<TR>
			<TD> Netmask </TD>
			<TD>
				<SELECT NAME="nmask">
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "0.0.0.0") { echo "SELECTED"; } ?>> Unused
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.255.255") { echo "SELECTED"; } ?>> 255.255.255.255
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.255.252") { echo "SELECTED"; } ?>> 255.255.255.252
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.255.248") { echo "SELECTED"; } ?>> 255.255.255.248
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.255.240") { echo "SELECTED"; } ?>> 255.255.255.240
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.255.224") { echo "SELECTED"; } ?>> 255.255.255.224
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.255.192") { echo "SELECTED"; } ?>> 255.255.255.192
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.255.128") { echo "SELECTED"; } ?>> 255.255.255.128
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.255.0")   { echo "SELECTED"; } ?>> 255.255.255.0

					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.252.0")   { echo "SELECTED"; } ?>> 255.255.252.0
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.248.0")   { echo "SELECTED"; } ?>> 255.255.248.0
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.240.0")   { echo "SELECTED"; } ?>> 255.255.240.0
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.224.0")   { echo "SELECTED"; } ?>> 255.255.224.0
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.192.0")   { echo "SELECTED"; } ?>> 255.255.192.0
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.128.0")   { echo "SELECTED"; } ?>> 255.255.128.0
					<OPTION <? if ($serv[$selected_host][$selected]['nmask'] == "255.255.0.0")   { echo "SELECTED"; } ?>> 255.255.0.0
				</SELECT>
			</TD>
		</TR>
-->
		<TR>
			<TD>Weight: </TD>
			<TD><INPUT TYPE="TEXT" NAME="weight" VALUE=<? echo $serv[$selected_host][$selected]['weight'] ?>></TD>
		</TR>
	</TABLE>
	
	<?	
		/* Welcome to the magic show */
		echo "<INPUT TYPE=HIDDEN NAME=selected_host VALUE=$selected_host>";
		echo "<INPUT TYPE=HIDDEN NAME=selected VALUE=$selected >";
	?>
<P>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
		<TR BGCOLOR="#666666">
			<TD><INPUT TYPE="SUBMIT" NAME="edit_action" VALUE="ACCEPT"></TD>
			<TD ALIGN=right><INPUT TYPE="SUBMIT" NAME="edit_action" VALUE="CANCEL"></TD>
		</TR>
</TABLE>
<? open_file ("w+"); write_config(""); ?>
</FORM>
</TD></TR></TABLE>
</BODY>
</HTML>
