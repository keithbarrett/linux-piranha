<?
	if ($help_action =="Close") {
		header("Location: control.php3");	/* Redirect browser to editing page */
		exit;  					/* Make sure that code below does not get executed when we redirect. */
	}	

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
<TITLE>Piranha (Help file)</TITLE>
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
                <TD>&nbsp;<BR><FONT SIZE="+2" COLOR="#CC0000">PASSWORD</FONT><BR>&nbsp;</TD>
        </TR>
</TABLE>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="0" CELLPADDING="0"><TR><TD BGCOLOR="#FFFFFF">

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
                <TD WIDTH="25%" ALIGN="CENTER" BGCOLOR=#ffffff> <A HREF="control.php3" NAME="Control/Monitoring" CLASS="tabon"><B>CONTROL/MONITORING</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="global_settings.php3" NAME="Global Settings" CLASS="taboff"><B>GLOBAL SETTINGS</B></A> </TD>
                <TD WIDTH="25%" ALIGN="CENTER"> <A HREF="redundancy.php3" NAME="Redundancy" CLASS="taboff"><B>REDUNDANCY</B></A> </TD>
		<? if ($prim['service'] == "fos") { ?>
			<TD WIDTH="25%" ALIGN="CENTER"> <A HREF="failover_main.php3" NAME="Failover" CLASS="taboff"><B>FAILOVER</B></A> </TD>
		<? } else { ?>
			<TD WIDTH="25%" ALIGN="CENTER"> <A HREF="virtual_main.php3" NAME="Virtual" CLASS="taboff"><B>VIRTUAL SERVERS</B></A> </TD>
		<? } ?>
        </TR>
</TABLE>

<?
	// echo "Query = $QUERY_STRING";
?>
<P>

<SPAN CLASS=title>PASSWORD CHANGE FOR PIRANHA</SPAN>
<P>
<FORM METHOD="GET" ENCTYPE="application/x-www-form-urlencoded" ACTION="passwd.php3">

<TABLE BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR>
                <TD>Passwords:</TD>
	<TR>
	<TD>The piranha GUI requires the user to log into the system as account 'piranha'.
The setting and changing of the password to this account is the resposibility of the system administrator.
Passwords are changed through the use of the piranha-passwd utility, located in /etc/sysconfig/ha. <TD>
	</TR>
	<TR>
	<TD>As the root user, from the command line, you should issue the following command:</TD>
	</TR>
	<TR>
	<TD>/etc/sysconfig/ha/piranha-passwd &lt;your password&gt;</TD>
	</TR>
	<TR>
	<TD>This should alter or create the file /etc/sysconfig/ha/conf/piranha.passwd</TD>
	<TR>
	<TD>ie you should have a file in /etc/sysconfig/ha/conf that looks similar to this<BR><PRE>-rw-------    1 piranha  piranha        22 Jun 28 16:24 piranha.passwd</PRE>

</TD>
	<TR>

</TABLE>

<SPAN CLASS=title>NOTE:</SPAN> Be aware that password changes have <U>immediate</U> effect and that you should  not have a blank password		

<P>

<TABLE WIDTH="100%" BORDER="0" CELLSPACING="1" CELLPADDING="5">
        <TR BGCOLOR="#666666">
		<TD>&nbsp;
                </TD>
        </TR>
</TABLE>

</FORM>
</TD></TR></TABLE>
</BODY>
</HTML>
