<?

	if ($vev_action == "EDIT") {
		header("Location: virtual_edit_services.php3?selected_host=$selected_host");
		exit;
	}

	if ($vev_action == "CANCEL") {
		header("Location: failover_main.php3?selected_host=$selected_host");
		exit;
	}

//	$hostname = ereg_replace(" ", "_", $hostname);

	/* try and make this page non cacheable */
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
	header("Pragma: no-cache");                                   // HTTP/1.0

	require('parse.php3'); /* read in the config! Hurragh! */

	$temp = explode(" ", $fail[$selected_host]['address']);

	if ( $vev_action == "ACCEPT" ) {
		/* we don't allow for deletions from here */
		
		if ($hostname == "")	{ $hostname = $fail[$selected_host]['failover']; } else { $fail[$selected_host]['failover'] = str_replace (" ", "_", $hostname); }


		$fail[$selected_host]['port']			=	$port;

		$fail[$selected_host]['address']		=	$temp[0];
		//$prim['nat_router']				=	$device;
		$fail[$selected_host]['reentry']		=	$reentry;
		$fail[$selected_host]['timeout']		=	$timeout;

		if ($nmask != "Unused" ) {
			$fail[$selected_host]['nmask']		=	$nmask;
		} else {
			$fail[$selected_host]['nmask']		=	"";
		}

	$fail[$selected_host]['address']	=	$address . " " . $device;
	}

?>
<HTML>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML Strict Level 3//EN">

<HEAD>
<TITLE>Piranha (Virtual Servers - Editing failover server)</TITLE>

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
	}
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
                <TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">EDIT FAILOVER SERVICE</FONT><BR>&nbsp;</TD>
        </TR>
</TABLE>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="0"><TR><TD BGCOLOR="#FFFFFF">


<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="control.php3" NAME="Control/Monitoring" CLASS="taboff"><B>CONTROL/MONITORING</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="global_settings.php3" NAME="Global Settings" CLASS="taboff"><B>GLOBAL SETTINGS</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="redundancy.php3" NAME="Redundancy" CLASS="taboff"><B>REDUNDANCY</B></A> </TD>
		<TD WIDTH="25%" ALIGN="CENTER" BGCOLOR="#ffffff"> <A HREF="failover_main.php3" NAME="Failover" CLASS="tabon"><B>FAILOVER</B></A> </TD>

        </TR>
</TABLE>
<?
	// echo "Query = $QUERY_STRING";

?>

<? if ($prim['service'] == "fos") { ?>
<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
        <TR BGCOLOR="#EEEEEE">
                <TD WIDTH="60%">EDIT:
		
		<A HREF="failover_edit_virt.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " CLASS="tabon" NAME="FAILOVER">FAILOVER</A>
		&nbsp;|&nbsp;

                <A HREF="virtual_edit_services.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " NAME="MONITORING SCRIPTS">MONITORING SCRIPTS</A></TD>

        </TR>
</TABLE>

<? } else { ?>
<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="5">
        <TR BGCOLOR="#EEEEEE">
                <TD WIDTH="60%">EDIT:
		
		<A HREF="virtual_edit_virt.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " CLASS="tabon" NAME="VIRTUAL SERVER">VIRTUAL SERVER</A>
		&nbsp;|&nbsp;

                <A HREF="virtual_edit_real.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " NAME="REAL SERVER">REAL SERVER</A>
		&nbsp;|&nbsp;

                <A HREF="virtual_edit_services.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " NAME="MONITORING SCRIPTS">MONITORING SCRIPTS</A></TD>

        </TR>
</TABLE>
<? } ?>

<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="failover_edit_virt.php3">
<TABLE>
	<TR>
		<TD>Name:</TD>
		<TD><INPUT TYPE="TEXT" NAME="hostname" VALUE= <? echo $fail[$selected_host]['failover'] ; ?>></TD>
	</TR>
	<TR>
		<TD>Address: </TD>
		<TD><INPUT TYPE="TEXT" NAME="address" VALUE=<? echo $fail[$selected_host]['address'] ?>></TD>
	</TR>
	<TR>
		<TD> Netmask </TD>
			<TD>
				<SELECT NAME="nmask">
				<OPTION <? if ($fail[$selected_host]['nmask'] == "0.0.0.0")         { echo "SELECTED"; } ?>> Unused
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.255.255") { echo "SELECTED"; } ?>> 255.255.255.255
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.255.252") { echo "SELECTED"; } ?>> 255.255.255.252
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.255.248") { echo "SELECTED"; } ?>> 255.255.255.248
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.255.240") { echo "SELECTED"; } ?>> 255.255.255.240
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.255.224") { echo "SELECTED"; } ?>> 255.255.255.224
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.255.192") { echo "SELECTED"; } ?>> 255.255.255.192
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.255.128") { echo "SELECTED"; } ?>> 255.255.255.128
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.255.0")   { echo "SELECTED"; } ?>> 255.255.255.0
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.252.0")   { echo "SELECTED"; } ?>> 255.255.252.0
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.248.0")   { echo "SELECTED"; } ?>> 255.255.248.0
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.240.0")   { echo "SELECTED"; } ?>> 255.255.240.0
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.224.0")   { echo "SELECTED"; } ?>> 255.255.224.0
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.192.0")   { echo "SELECTED"; } ?>> 255.255.192.0
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.128.0")   { echo "SELECTED"; } ?>> 255.255.128.0
				<OPTION <? if ($fail[$selected_host]['nmask'] == "255.255.0.0")     { echo "SELECTED"; } ?>> 255.255.0.0
			</SELECT>
		</TD>
	</TR>

		<TD>Application port:</TD>
		<TD><INPUT TYPE="TEXT" NAME="port" VALUE=<? echo  $fail[$selected_host]['port'] ?>></TD></TD>
	</TR>

	<TR>
		<TD>Device: </TD>
		<TD> <INPUT TYPE="TEXT" NAME="device" VALUE=<? echo $temp[1]; ?>></TD>
	</TR>
	<TR>
		<TD>Service timeout: </TD>
		<TD> <INPUT TYPE="TEXT" NAME="timeout" VALUE=<? echo $fail[$selected_host]['timeout'] ?>></TD>
	</TR>
	
	<TR>
		<TD>Generic service scripts: </TD><TD><INPUT TYPE="SUBMIT" NAME="vev_action" VALUE="EDIT"></TD>
	</TR>

</TABLE>
<? echo "<INPUT TYPE=HIDDEN NAME=selected_host VALUE=$selected_host>" ?>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
                <TD>
                        <INPUT TYPE="Submit" NAME="vev_action" VALUE="ACCEPT">  <SPAN CLASS="taboff">-- Click here to apply changes to this page</SPAN>
                </TD>
        </TR>
</TABLE>

<? open_file ("w+"); write_config(""); ?>
</FORM>
</TD></TR></TABLE>
</BODY>
</HTML>