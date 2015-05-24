<?	
	/* try and make this page non cacheable */
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
	header("Pragma: no-cache");                                   // HTTP/1.0

	require('parse.php3'); /* read in the config! Hurragh! */
	if ($prim['service'] == "") {
		$prim['service'] = "lvs";
	}

	if ($lvs == "fos") $prim['service'] = "fos";
	if ($lvs == "lvs") $prim['service'] = "lvs";


	if (empty($nat_nmask)) {
		$NATRtrNmask	=	$prim['nat_nmask'];
	} else {
		$NATRtrNmask	=	$nat_nmask;
	}

	if ($global_action == "ACCEPT") {
		$prim['primary']	= $PriLVSIP;
		if ($prim['service'] != "fos") {
		$network = $prim['network'];
		switch ($network) {
			case "NAT"			:	$network="nat"; break;
			case "Direct Routing"		:	$network="direct"; break;
			case "Tunneling"		:	$network="tunnel"; break;
			default				:	break;
		}
		$prim['network']	=	$network;
		if ($prim['network'] == "nat") {
			$prim['nat_router']	= $NATRtrIP . " " . $NATRtrDev;
		} else {
			$prim['nat_router'] = "";
		}

		if ($nat_nmask != "" ) {
			$prim['nat_nmask']	=	$nat_nmask;
		} else {
			$nat_nmask		=	"255.255.255.0";
		}
	}
	}

	if ($rsync_tool == "" ) {
		if ($prim['rsh_command'] != "" ) {
			$rsync_tool = $prim['rsh_command'];
		} else {
			$rsync_tool = "rsh";
			$prim['rsh_command'] = "rsh";
		}
	} else {
		$prim['rsh_command'] = $rsync_tool;
	};

	if ($debug_level == "" ) {
		if ($prim['debug_level'] != "" ) {
			$debug_level = $prim['debug_level'];
		} else {
			$debug_level = "NONE";
			$prim['debug_level'] = "NONE";
		}
	} else {
		$prim['debug_level'] = $debug_level;
	};

	if ($prim['network'] == "") { $prim['network'] = "direct";}

	if ($network == "NAT") { $prim['network'] = "nat"; $prim['nat_router'] = $NATRtrIP . " " . $NATRtrDev; }
	if ($network == "Direct Routing") { $prim['network'] = "direct"; $prim['nat_router'] = ""; }
	if ($network == "Tunneling") { $prim['network'] = "tunnel"; $prim['nat_router'] = ""; }
	
	if ($prim['primary'] == "") { $prim['primary'] = $SERVER_ADDR; $PriLVSIP = $SERVER_ADDR;} /* semi sensible guess */
			

	// echo "Query = $QUERY_STRING";
?>

<HTML>
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML Strict Level 3//EN">

<HEAD>
<TITLE>Piranha (Global Settings)</TITLE>

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
                <TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">GLOBAL SETTINGS</FONT><BR>&nbsp;</TD>
        </TR>
</TABLE>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="0"><TR><TD BGCOLOR="#FFFFFF">
<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="control.php3" NAME="Control/Monitoring" CLASS="taboff"><B>CONTROL/MONITORING</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER" BGCOLOR="#FFFFFF"> <A HREF="global_settings.php3" NAME="Global Settings" CLASS="tabon"><B>GLOBAL SETTINGS</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="redundancy.php3" NAME="Redundancy" CLASS="taboff"><B>REDUNDANCY</B></A> </TD>
		<? if ($prim['service'] == "fos") { ?>
			<TD WIDTH="25%" ALIGN="CENTER"> <A HREF="failover_main.php3" NAME="Failover" CLASS="taboff"><B>FAILOVER</B></A> </TD>
		<? } else { ?>
			<TD WIDTH="25%" ALIGN="CENTER"> <A HREF="virtual_main.php3" NAME="Virtual" CLASS="taboff"><B>VIRTUAL SERVERS</B></A> </TD>
		<? } ?>
        </TR>
</TABLE>

<P>
<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="global_settings.php3">


<P>
<TABLE  BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD CLASS="title" COLSPAN="2">ENVIRONMENT</TD>
        </TR>

	<TR>
		<TD>Primary server IP:</TD>
		<TD><INPUT TYPE="TEXT" NAME="PriLVSIP" SIZE=16 VALUE="<?
			
			echo $prim['primary'];
		?>"></TD>
	</TR>

	<TR>
		<TD>LVS type: <? echo "( <B>" . $prim['service'] . "</B> )"; ?></TD>
		<TD>
			<INPUT TYPE="SUBMIT" NAME="lvs" SIZE=16 VALUE="fos">
			<INPUT TYPE="SUBMIT" NAME="lvs" SIZE=16 VALUE="lvs">
		</TD>
	</TR>
	<? if ($prim['service'] != "fos") { ?>
	<TR>
		<TD>Use network type:<BR>(Current type is: <B><? echo $prim['network']; ?></B> ) </TD>
		<!--
		<TD>
		<SELECT NAME="network">
				<OPTION <? if ($prim['network'] == "nat") { echo "SELECTED"; } ?>> NAT
				<OPTION <? if ($prim['network'] == "direct") { echo "SELECTED"; } ?>> Direct Routing
				<OPTION <? if ($prim['network'] == "tunnel") { echo "SELECTED"; } ?>> Tunneling
		</SELECT>
		</TD>-->
		<TD><INPUT TYPE="SUBMIT" NAME="network" SIZE=16 VALUE="NAT">
		<INPUT TYPE="SUBMIT" NAME="network" SIZE=16 VALUE="Direct Routing">
		<INPUT TYPE="SUBMIT" NAME="network" SIZE=16 VALUE="Tunneling">
	</TR>
	<? if ($prim['network'] == "nat" ) { ?>
	 <TR>
		<TD>NAT Router IP:</TD>
		<TD><INPUT TYPE="TEXT" NAME="NATRtrIP" SIZE=16 VALUE="<?
			$ip = explode(" ", $prim['nat_router']);
			echo $ip[0];
			// echo $prim['...???? WHAT??
		?>"></TD>
	</TR>
	<TR>
		<TD>NAT Router netmask:</TD>
		<TD>
			<SELECT NAME="nat_nmask">
				<OPTION <? if ($NATRtrNmask == "255.255.255.255") { echo "SELECTED"; } ?>> 255.255.255.255
				<OPTION <? if ($NATRtrNmask == "255.255.255.252") { echo "SELECTED"; } ?>> 255.255.255.252
				<OPTION <? if ($NATRtrNmask == "255.255.255.248") { echo "SELECTED"; } ?>> 255.255.255.248
				<OPTION <? if ($NATRtrNmask == "255.255.255.240") { echo "SELECTED"; } ?>> 255.255.255.240
				<OPTION <? if ($NATRtrNmask == "255.255.255.224") { echo "SELECTED"; } ?>> 255.255.255.224
				<OPTION <? if ($NATRtrNmask == "255.255.255.192") { echo "SELECTED"; } ?>> 255.255.255.192
				<OPTION <? if ($NATRtrNmask == "255.255.255.128") { echo "SELECTED"; } ?>> 255.255.255.128
				<OPTION <? if ($NATRtrNmask == "255.255.255.0")   { echo "SELECTED"; } ?>> 255.255.255.0

				<OPTION <? if ($NATRtrNmask == "255.255.252.0")   { echo "SELECTED"; } ?>> 255.255.252.0
				<OPTION <? if ($NATRtrNmask == "255.255.248.0")   { echo "SELECTED"; } ?>> 255.255.248.0
				<OPTION <? if ($NATRtrNmask == "255.255.240.0")   { echo "SELECTED"; } ?>> 255.255.240.0
				<OPTION <? if ($NATRtrNmask == "255.255.224.0")   { echo "SELECTED"; } ?>> 255.255.224.0
				<OPTION <? if ($NATRtrNmask == "255.255.192.0")   { echo "SELECTED"; } ?>> 255.255.192.0
				<OPTION <? if ($NATRtrNmask == "255.255.128.0")   { echo "SELECTED"; } ?>> 255.255.128.0
				<OPTION <? if ($NATRtrNmask == "255.255.0.0")     { echo "SELECTED"; } ?>> 255.255.0.0

			</SELECT>
		</TD>
	</TR>
	<TR>
		<TD>NAT Router device:</TD>
		<TD><INPUT TYPE="TEXT" NAME="NATRtrDev" SIZE=8 VALUE="<?
			$dev = explode(" ", $prim['nat_router']);
			echo $dev[1];
			// echo $prim['..??
	?>"></TD>
	</TR>
	<? } ?>
	<? } ?>
</TABLE>
<HR>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD CLASS="title" COLSPAN="2">FILE SYNC</TD>
        </TR>
<TR> <TD>Select which sync tool you would prefer to use </TD> </TR>
<TR> <TD>
<INPUT TYPE="RADIO" NAME="rsync_tool" VALUE="rsh" <? if ($rsync_tool == "rsh") { echo "CHECKED"; } ?>> rsh
&nbsp;&nbsp;&nbsp;
<INPUT TYPE="RADIO" NAME="rsync_tool" VALUE="ssh" <? if ($rsync_tool == "ssh") { echo "CHECKED"; } ?>> ssh
</TD></TR>
</TABLE>

<HR>
<!--
<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD CLASS="title" COLSPAN="2">SYSTEM LOG MESSAGES</TD>
        </TR>

<TR> <TD>
<INPUT TYPE="RADIO" NAME="debug_level" VALUE="MAX" <? if ($debug_level == "MAX") { echo "CHECKED"; } ?>> MAX
&nbsp;&nbsp;&nbsp;
<INPUT TYPE="RADIO" NAME="debug_level" VALUE="NONE" <? if ($debug_level == "NONE") { echo "CHECKED"; } ?>> NONE
</TD></TR>
</TABLE>
-->

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
	<TR BGCOLOR="#666666">
		<TD>
			<INPUT TYPE="SUBMIT" NAME="global_action" VALUE="ACCEPT"> <SPAN CLASS="taboff"> -- Click here to apply changes on this page</SPAN>
		</TD>
	</TR>
</TABLE>

<? 
	open_file ("w+"); write_config(""); 
?>


</FORM>

</TD></TR></TABLE>
</BODY>
</HTML>
