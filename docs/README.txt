README
-------------------------------------------------------------------------
The services and components of Piranha are described below.


LINUX VIRTUAL SERVER (LVS)
--------------------------

Based on Wensong Zhang's Linux Virtual Server (LVS) kernel routing patch.
LVS provides for cluster configurations involving 3 or more nodes
(not limited to Linux servers), where some of the nodes provide load
balanced services (such as http) and appear as a single node and tcp/ip
address.

Red Hat's Piranha software enhances this service even further to increase
load balancing abilities, and to provide automatic detection and failover
over of faulty servers.



FAILOVER SERVICES (FOS)
-----------------------

Using a cluster of only 2 Linux systems (primary and secondary node), it
is possible to set one system up as a standby for certain IP services
running on the other. Services will automatically be started and
stopped by the failover process, and "move" the virtual IP address
of each service as needed. No load balancing exists in this setup.
A typical use for this service would be for web or ftp servers.

Both systems must be identically configured linux systems. Failover
Services are defined very must like virtual servers except that some
parameters are not applicable, and there are no "real servers". Failover
Services are currently mutually exclusive to virtual servers -- you cannot
have both active in the same config (lvs.cf).


PIRANHA GUI
-----------

Web based interface for maintaining the contents of the piranha
configuration file (lvs.cf). 




PIRANHA SOFTWARE COMPONENTS
---------------------------

IPVS (used only by LVS)

  * Building virtual servers: floating IP addresses where requests for service
    arrive from the public internet.

  * Routing service requests from virtual servers to a pool of real Web and
    FTP servers on a private network. 

  * Migrating floating IP addresses and routing services from a failed primary
    node to a backup node.

  * Load-balancing modules: round-robin and least connections, which weighted
    versions of both. 

  * TCP or UDP connections, and persistence.


lvs (used only by LVS)

  The lvs daemon runs on the primary and backup nodes. This process controls
  Piranha and supports communication among its components.


fos (used only by FOS)

  The fos daemon runs on the primary and backup nodes. This process controls
  Piranha and supports communication among its components.


lvs.cf

  This is the configuration file that pulse, lvs, and fos reads on startup.
  Although it is called lvs.cf, it contains information for fos as well.
  It normally resides in /etc/sysconfig/ha/


pulse

  The pulse daemon runs on the primary and backup nodes. Through this process,
  the backup cluster node determines whether the primary node is alive. 


/etc/rc.d/init.d/pulse

  This is the pulse startup script.


nanny

  The nanny daemon runs on cluster nodes, and is used to monitor active
  services for failure. Through this process, the active cluster node
  continues to grant or forward service requests, or to treat the
  service as failed.


ipvsadm (used only by LVS)

  The command line tool for setting up and administering an LVS cluster.
  It works with the IPVS kernel changes,


send_arp

  Utility program used to notifiy network ARP tables that a virtual
  TCP/IP address has changed a MAC address.



See the sample.cf and HOWTO files, and/or the lvs.cf(5), pulse(8), nanny(8),
lvs (8), send_arp (8), and fos(8) man pages for more information.



PIRANHA GUI SOFTWARE COMPONENTS
-------------------------------

/etc/rc.d/init.d/piranha-gui

  This is the startup script for the gui httpd daemon.


/etc/sysconfig/ha/web

  The various web pages for the GUI


piranha-passwd

  Program to set the password for the piranha account needed by the GUI.





GUI HTML INTERFACE
------------------
The decision to use a web based front end for the configuration
was taken because the web interface provides a globally accepted stateless
environment which better fits the design of HA clustering. It also
means that you can configure a cluster remotely and with any OS that sports
a web browser. Also; if you are using the virtual ip address of your
servers, then your configuration tool will failover if/when the
servers failover.

INSTALLATION NOTES:

    1) Apache and PHP must be installed on the system in order to
       install/upgrade piranha. The piranha GUI runs in its own
       httpd daemon and settings, so it will operate independently
       of the systems main http service. Other http server software
       can even be used on the system, as long as the piranha GUI
       uses apache.

    2) The GUI has it's own startup script (piranha-gui). Once running,
       the piranha GUI will use it's own tcp/ip port, usually 3636
       (ex: http://yourhost.com:3636/)
.
       Attempts to access piranha's configuration web pages without
       going through the piranha GUI daemon will return permission
       errors. 

    3) Use the piranha-password script to set the password to
       the piranha account before using the GUI for the first time.

    4) Connect your web browser to the primary IP on your active/master
       node as in http://test.host.name:3636/piranha


FAILOVER SERVICE NOTES:

In failover services, the failure  of a single service causes the entire
cluster node to perform a failover. When you monitor multiple services
(such as web AND ftp), if one of the services fails the node will failover.
If on the now active node one of those services does not come up, then
you will end up with a "ping-ponging" cluster.

Because of the nature of failover services as it is written, it is also easy
to create a non-functional "ping ponging" setup if you are not careful
with your configuration files. Almost all causes of a disfunctional system
are because of configuration errors.

Here are some steps to ensure a functional setup:

1. Make sure the lvs.cf files are exactly the same on both systems! The
   most common problem is mismatched config files.

2. If you are monitoring http, make sure that the port number specified
   in lvs.cf is also being used by httpd. In other words, if you are
   using apache for example, make sure your httpd configuration is
   using port 1010 instead of 80 if you specified 1010 in lvs.cf

3. Before you start piranha fos for the first time in a new configuration,
   start the service (ftp, http, etc.) manually and use telnet on the
   local and partner systems to see if that service REALLY is reachable.
   IF telnet CANNOT REACH A SERVICE, THEN IT IS LIKELY THAT PIRANHA CAN'T.

   For example (if you are monitoring http on port 1010); start httpd, then
   do a "telnet localhost 1010" and see if you get refused or not. If
   not, then try the same telnet from your partner computer to make sure that
   it can reach that service also. Make sure this works before starting
   piranha.

4. Piranha must control the starting and stopping of the services. This
   means that a failover takes time, depending on the service and computer
   speeds. Avoid using too small a heartbeat interval. If you are
   experiencing problems where failovers are interrupted or bounse back and
   forth, try using a larger heartbeat interval. Values between 5 and 10
   seconds are usually sufficient unless the compuers are very slow.

5. If you are monitoring a service controlled by inetd (such as ftp),
   remember that this means inetd will be started and stopped by piranha.
   This means, for example, that rsh will not be usable and you may have
   to use ssh instead (for file copies, etc.).
