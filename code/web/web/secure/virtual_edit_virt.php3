<?

		if (($vev_action == "CANCEL") && ($prim['service'] != "fos" )) {
			header("Location: virtual_main.php3?selected_host=$selected_host");	/* Redirect browser to editing page */
			exit;  					/* Make sure that code below does not get executed when we redirect. */
		}

		if (($vev_action == "CANCEL") && ($prim['service'] == "fos" )) {
			header("Location: failover_main.php3?selected_host=$selected_host");	/* Redirect browser to editing page */
			exit;  					/* Make sure that code below does not get executeed */
		}

	if (($selected_host == "") && ($prim['service'] == "fos")) {
		header("Location: failover_main.php3");
		exit;
	}

	if (($selected_host == "") && ($prim['service'] != "fos")) {
		header("Location: virtual_main.php3");
		exit;
	}

	if ($vev_action == "EDIT") {
		header("Location: virtual_edit_services.php3?selected_host=$selected_host");	/* Redirect browser to editing page */
		exit;  					/* Make sure that code below does not get executed when we redirect. */
	}
	/* try and make this page non cacheable */
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
	header("Pragma: no-cache");                                   // HTTP/1.0

	require('parse.php3'); /* read in the config! Hurragh! */

	if ($prim['service'] != "fos" ) {
		$temp = explode(" ", $virt[$selected_host]['address']);
	} else {
		$temp = explode(" ", $fail[$selected_host]['address']);
	}

	if ( $vev_action == "ACCEPT" ) {

		/* we don't allow for deletions from here */
		if ($hostname == "")	{ $hostname = $virt[$selected_host]['virtual']; } else {$virt[$selected_host]['virtual'] = str_replace (" ", "_", $hostname); }

		if ($prim['service'] != "fos" ) {
			$virt[$selected_host]['port']			=	$port;
			
			$virt[$selected_host]['address']		=	$temp[0];
			$virt[$selected_host]['protocol']		=	$protocol;
			$virt[$selected_host]['reentry']		=	$reentry;
			$virt[$selected_host]['timeout']		=	$timeout;
			$virt[$selected_host]['load_monitor']		=	$load;
			switch ($sched) {
				case "Round robin"			:	$sched="rr"; break;
				case "Weighted least-connections"	:	$sched="wlc"; break;
				case "Weighted round robin"		:	$sched="wrr"; break;
				case "Least-connection"			:	$sched="lc"; break;
				default					:	break;
			}
			$virt[$selected_host]['scheduler']		=	$sched;
			$virt[$selected_host]['persistent']		=	$persistent;
			if ($pmask != "Unused" ) {
				$virt[$selected_host]['pmask']		=	$pmask;
			} else {
				$virt[$selected_host]['pmask']		=	"";
			}

			if ($vip_nmask != "Unused" ) {
				$virt[$selected_host]['vip_nmask']		=	$vip_nmask;
			} else {
				$virt[$selected_host]['vip_nmask']		=	"";
			}

			$virt[$selected_host]['address']      		=       $address . " " . $device;
			
		} else {
			$fail[$selected_host]['port']			=	$port;
			$fail[$selected_host]['address']                =       $temp[0];
			$fail[$selected_host]['reentry']		=	$reentry;
			$fail[$selected_host]['timeout']		=	$timeout;
			$fail[$selected_host]['address']      		=       $address . " " . $device;
		}
	
	}

?>
<HTML>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML Strict Level 3//EN">

<HEAD>

<? if ($prim['service'] == "fos") { ?>
  	<TITLE>Piranha (Virtual Servers - Editing failover server)</TITLE>
<? } else { ?>
	<TITLE>Piranha (Virtual Servers - Editing virtual server)</TITLE>
<? } ?>

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
		<? if ($prim['service'] == "fos") { ?>
                <TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">EDIT FAILOVER SERVICE</FONT><BR>&nbsp;</TD>
		<? } else { ?>
		<TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">EDIT VIRTUAL SERVER</FONT><BR>&nbsp;</TD>
		<? } ?>
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
		
		<A HREF="virtual_edit_virt.php3<? if (!empty($selected_host)) { echo "?selected_host=$selected_host"; } ?> " CLASS="tabon" NAME="FAILOVER">FAILOVER</A>
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

<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="virtual_edit_virt.php3">
<TABLE>
	<TR>
		<TD>Name:</TD>
		<TD><INPUT TYPE="TEXT" NAME="hostname" VALUE= <? echo $virt[$selected_host]['virtual'] ; ?>></TD>
	</TR>
	<TR>

		<TD>Application port:</TD>
		<TD><INPUT TYPE="TEXT" NAME="port" VALUE=<? echo  $virt[$selected_host]['port'] ?>></TD>
	</TR>
	<TR>
		<TD>Protocol</TD>
		<TD>
			<SELECT NAME="protocol">
				<OPTION <? if (($virt[$selected_host]['protocol'] == "tcp") || 
					       ($virt[$selected_host]['protocol'] == "")) { echo "SELECTED"; } ?>> tcp
				<OPTION <? if ($virt[$selected_host]['protocol'] == "udp") { echo "SELECTED"; } ?>> udp
			</SELECT>
		</TD>

	</TR>

	<TR>
		<TD>Virtual IP Address: </TD>
		<TD><INPUT TYPE="TEXT" NAME="address" VALUE=<? echo $virt[$selected_host]['address'] ?>></TD>
	</TR>
	<TR>
		<TD> Virtual IP Network Mask </TD>
		<TD>
			<SELECT NAME="vip_nmask">
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "0.0.0.0") { echo "SELECTED"; } ?>> Unused
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.255.255") { echo "SELECTED"; } ?>> 255.255.255.255
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.255.252") { echo "SELECTED"; } ?>> 255.255.255.252
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.255.248") { echo "SELECTED"; } ?>> 255.255.255.248
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.255.240") { echo "SELECTED"; } ?>> 255.255.255.240
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.255.224") { echo "SELECTED"; } ?>> 255.255.255.224
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.255.192") { echo "SELECTED"; } ?>> 255.255.255.192
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.255.128") { echo "SELECTED"; } ?>> 255.255.255.128
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.255.0")   { echo "SELECTED"; } ?>> 255.255.255.0

				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.252.0")   { echo "SELECTED"; } ?>> 255.255.252.0
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.248.0")   { echo "SELECTED"; } ?>> 255.255.248.0
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.240.0")   { echo "SELECTED"; } ?>> 255.255.240.0
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.224.0")   { echo "SELECTED"; } ?>> 255.255.224.0
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.192.0")   { echo "SELECTED"; } ?>> 255.255.192.0
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.128.0")   { echo "SELECTED"; } ?>> 255.255.128.0
				<OPTION <? if ($virt[$selected_host]['vip_nmask'] == "255.255.0.0")   { echo "SELECTED"; } ?>> 255.255.0.0

			</SELECT>
		</TD>
	</TR>
	<TR>
		<TD>Device: </TD>
		<TD> <INPUT TYPE="TEXT" NAME="device" VALUE=<? echo $temp[1]; ?>></TD>
	</TR>	<TR>
		<TD>Re-entry Time: </TD>
		<TD> <INPUT TYPE="TEXT" NAME="reentry" VALUE=<? echo $virt[$selected_host]['reentry'] ?>></TD>
	</TR>
	<TR>
		<TD>Service timeout: </TD>
		<TD> <INPUT TYPE="TEXT" NAME="timeout" VALUE=<? echo $virt[$selected_host]['timeout'] ?>></TD>
	</TR>
	
	<TR>
		<TD> Load monitoring tool:</TD>
		<TD>
		<SELECT NAME="load">
			<OPTION <? if ($virt[$selected_host]['load_monitor'] == "none") { echo "SELECTED"; } ?> > none
			<OPTION <? if ($virt[$selected_host]['load_monitor'] == "uptime") { echo "SELECTED"; } ?> > uptime
			<OPTION <? if ($virt[$selected_host]['load_monitor'] == "rup") { echo "SELECTED"; } ?> > rup
			<OPTION <? if ($virt[$selected_host]['load_monitor'] == "ruptime") { echo "SELECTED"; } ?> > ruptime
		</SELECT>
		</TD>
	</TR>
	<TR>
		<TD> Scheduling: </TD>
		<TD>
			<SELECT NAME="sched">
				<OPTION <? if ($virt[$selected_host]['scheduler'] == "rr") { echo "SELECTED"; } ?>> Round robin
				<OPTION <? if ($virt[$selected_host]['scheduler'] == "wlc") { echo "SELECTED"; } ?>> Weighted least-connections
				<OPTION <? if ($virt[$selected_host]['scheduler'] == "wrr") { echo "SELECTED"; } ?>> Weighted round robin
				<OPTION <? if ($virt[$selected_host]['scheduler'] == "lc") { echo "SELECTED"; } ?>> Least-connection
			</SELECT>

		</TD>
	</TR>

	<TR>
		<TD>Generic service scripts: </TD><TD><INPUT TYPE="SUBMIT" NAME="vev_action" VALUE="EDIT"></TD><TD>(NOTE: <U>changes</U> made on this page will not be<BR> actioned if you use this, use the ACCEPT button first)</TD>
	</TR>


	<TR>
		<TD> Persistence: </TD>
		<TD><INPUT TYPE="TEXT" NAME="persistent" VALUE=<? echo $virt[$selected_host]['persistent'] ?>></TD>
	</TR>
	<TR>
		<TD> Persistence Network Mask </TD>
		<TD>
			<SELECT NAME="pmask">
				<OPTION <? if ($virt[$selected_host]['pmask'] == "") { echo "SELECTED"; } ?>> Unused
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.255.255") { echo "SELECTED"; } ?>> 255.255.255.255
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.255.252") { echo "SELECTED"; } ?>> 255.255.255.252
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.255.248") { echo "SELECTED"; } ?>> 255.255.255.248
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.255.240") { echo "SELECTED"; } ?>> 255.255.255.240
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.255.224") { echo "SELECTED"; } ?>> 255.255.255.224
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.255.192") { echo "SELECTED"; } ?>> 255.255.255.192
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.255.128") { echo "SELECTED"; } ?>> 255.255.255.128
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.255.0")   { echo "SELECTED"; } ?>> 255.255.255.0

				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.252.0")   { echo "SELECTED"; } ?>> 255.255.252.0
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.248.0")   { echo "SELECTED"; } ?>> 255.255.248.0
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.240.0")   { echo "SELECTED"; } ?>> 255.255.240.0
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.224.0")   { echo "SELECTED"; } ?>> 255.255.224.0
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.192.0")   { echo "SELECTED"; } ?>> 255.255.192.0
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.128.0")   { echo "SELECTED"; } ?>> 255.255.128.0
				<OPTION <? if ($virt[$selected_host]['pmask'] == "255.255.0.0")   { echo "SELECTED"; } ?>> 255.255.0.0

			</SELECT>
		</TD>
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
