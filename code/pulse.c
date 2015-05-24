/*
** pulse.c -- detect lvs or fos failure
**
** Red Hat Clustering Tools 
** Copyright 1999 Red Hat, Inc.
**
** Author:  Erik Troan <ewt@redhat.com>
**
**
** You may freely redistributed or modified this file under the
** terms of the GNU General Public License; version 2 or (at your
** option), any later version.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**
**
** MODIFICATION HISTORY:
**
** 09/xx/1999   1.0 -   Erik Troan <ewt@redhat.com>
**              1.9     Original release & repairs
**
** 12/19/1999   1.10    Phil "Bryce" Copeland <copeland@redhat.com>
**                      Minor typecasts and other changes for 64bit.
**
** 01/08/2000   1.11    Phil "Bryce" Copeland <copeland@redhat.com>
**                      Changed log() to piranha_log()
** 
** 01/28/2000   1.12 -  Keith Barrett <kbarrett@redhat.com>
**              1.14    Added header & history. Minor formatting.
**
** 02/07/2000   1.15 -  Keith Barrett <kbarrett@redhat.com>
**              1.18    send_arp is in /usr/sbin
**                      ignore tunl device when fetching hardware addrs
**
** 02/15/2000   1.19 -  Keith Barrett <kbarrett@redhat.com>
**              1.21    Lots of formatting and comments.
**
** 2/23/2000    1.22 -  Keith Barrett <kbarrett@redhat.com>
**              1.24    Added -v for debug output
**                      Generic service option passing to nanny
**
** 2/25/2000    1.25 -  Keith Barrett <kbarrett@redhat.com>
**              1.30    Added call to lvsRelocateFS routine
**                      If backupActive and no backup defined, clear it
**                      Don't arp nat device if no nat device to use
**
** 3/1/2000     1.31    Keith Barrett <kbarrett@redhat.com>
**                      Added failover service logic
**
** 3/2/2000     1.32    Phil "Bryce" Copeland <copeland@redhat.com>
**                      Added processing of USR signals
**                      Added config file copy
**
** 3/4/2000     1.33 -  Keith Barrett <kbarrett@redhat.com>
**              1.39    Fixed child signal problem with heartbeat
**                      Added ability for fos to "takover" from master
**                      if not the active node. Proclaimed stable.
**                      2nd pulse daemon logic commented out in this file.
**
** 3/6/2000     1.40 -  Keith Barrett <kbarrett@redhat.com>
**              1.43    Replaced processing for USR signals, but disabled
**                      the logic for this release because it's incomplete.
**                      Don't run if all services are flagged as inactive.
**
** 3/7/2000     1.44    Keith Barrett <kbarrett@redhat.com>
**                      Clean up special take control heartbeat
**                      Fixed lvs child term logic
**                      Moved signal blocking to dup the old lvs logic
**
** 6/13/2000    1.45    Keith Barrett <kbarrett@redhat.com>
**                      Fixed flags getting clobbered and screwing up
**                      the take control heartbeat.
**
** 6/14/2000    1.46    Keith Barrett <kbarrett@redhat.com>
**                      Changed "take control" heartbeat logic
**
** 6/18/2000    1.47    Keith Barrett <kbarrett@redhat.com>
**                      Added debugging to heartbeat logic
**
** 7/20/2000    1.48 -  Keith Barrett <kbarrett@redhat.com>
**              1.49    Changed debug mode & statements. Added --version.
**                      Added Bryce's CFGFILE logic.
**
** 8/17/2000    1.50    Lars Kellogg-Stedman <lars@larsshack.org>
**                      Fixed uninitialized size variable in heartbeat
**
** 9/11/2000	1.51	Philip Copeland <copeland@redhat.com>
**			            Fixed least connections to be lc not pcc
**
** 10/17/2000	1.52	Philip Copeland <bryce@redhat.com>
**                      Added extra --version info
**                      Fixed ifconfig up (should be down) for disabling
**                      interfaces
**
** 5/14/2001    1.53    Keith Barrett <kbarrett@redhat.com>
**                      Minor cleanup, added test-start.
**                      Suppress some errors on startup
**
** 5/30/2001    1.54    Keith Barrett <kbarrett@redhat.com>
**                      Corrected netmask impact on ifconfig and send arps.
**                      If starting FOS and no backup defined, force active.
**                      Renamed lvsProcess variable HA_process.
*/


#define _PROGRAM_VERSION "pulse 1.54"


#include <stdlib.h>                             /* definition of exit() */
#include <ctype.h>
#include <errno.h>
#include <fcntl.h>
#include <net/if.h>
#include <netinet/in.h>
#include <popt.h>
#include <sys/ioctl.h>
#include <sys/signal.h>
#include <sys/time.h>
#include <sys/un.h>
#include <sys/wait.h>
#include <unistd.h>
#include <pwd.h>
#include <setjmp.h>

#include "lvsconfig.h"
#include "util.h"

#define _(a) (a)
#define N_(a) (a)

#define PULSE_FLAG_ASDAEMON         (1 << 0)
#define PULSE_FLAG_AMMASTER         (1 << 1)
#define PULSE_FLAG_AMACTIVE         (1 << 2)
#define PULSE_FLAG_NORUN            (1 << 3)
#define PULSE_FLAG_FAILPARTNER      (1 << 4)
#define PULSE_FLAG_RESTART_PARTNER  (1 << 5)
#define PULSE_FLAG_ONHOLD           (1 << 6)
#define PULSE_FLAG_SUPPRESS_GI_MSG  (1 << 7)
#define PULSE_FLAG_SUPPRESS_TC_MSG  (1 << 8)
#define PULSE_FLAG_SUPPRESS_ERRORS  (1 << 9)

#define FOS_SERVICES_UP             (1 << 0)
#define FOS_MONITORS_UP             (1 << 1)

#define HEARTBEAT_RUNNING_MAGIC     0xbdaddbda
#define HEARTBEAT_STOPPED_MAGIC     0xadbddabd
#define HEARTBEAT_HOLD_MAGIC        0xDeafBabe
#define HEARTBEAT_HELD_MAGIC        0xDeafDaDa
#define FOS_HEARTBEAT_RUNNING_MAGIC 0xBeefBabe
#define FOS_HEARTBEAT_STOPPED_MAGIC 0xDeadBeef
#define FOS_HEARTBEAT_STOPME_MAGIC  0xDeadDaDa

/* Globals icky :-( */
pid_t    piranha;
pid_t    pid;
struct   sigaction sig;
int      gflag = 0;

static   char *configFile  = (char *) CFGFILE;    /* Supplied by Makefile */
static   char *lvsBinary   = (char *) "/usr/sbin/lvs";
static   char *fosBinary   = (char *) "/usr/sbin/fos";
static   int  pulse_debug  = 0;

volatile int   termSignal  = 0;
volatile int   childSignal = 0;
volatile int   User1Signal = 0;
volatile pid_t HA_Process  = 0;

int      heartbeat_running_magic = (int) HEARTBEAT_RUNNING_MAGIC;
int      heartbeat_stopped_magic = (int) HEARTBEAT_STOPPED_MAGIC;


/* prototypes */
void relay(int s);
void house_keeping(int s);
void initsetproctitle(int argc, char **argv, char **envp);
void setproctitle(const char *fmt, ...);



static void handleTermSignal(int signal) {
    termSignal = signal;
}

static void handleChildDeath(int signal) {
    childSignal = signal;
    /* This is only possible with fos. */
}

#if 0
/************** ### Disable incomplete USR1 logic ### ************
static void handleUser1Signal(int signal) {
    User1Signal = signal;
}
****************************************************************/
#endif


/*
**  getInterfaceAddress() -- Get address of a network device
*/

static int getInterfaceAddress( int s,
                                char * device,
                                struct in_addr * addr, 
                                int flags) {
    struct ifreq req;
    struct sockaddr_in *addrp;
  
    strcpy(req.ifr_name, device);
  
    if (ioctl(s, SIOCGIFADDR, &req)) {
        if (strncmp("tunl", device, 4))    /* log only if not tunl dev */
            piranha_log(flags,
                (char *) "SIOCGIFADDR failed: %s", strerror(errno));
        /*
        ** Note: suppress errors about tunl because it's in /proc even
        ** if it is not being used, so it will appear here.
        */

        return 1;
    }

    addrp = (struct sockaddr_in *) &req.ifr_addr;
    memcpy(addr, &addrp->sin_addr, sizeof(*addr));
    return 0;
}    





/*
**  getInterfaceInfo() -- get mac address for validated device
*/

static void getInterfaceInfo(   int s,
                                char * device,
                                struct in_addr * addr,
                                char * hwaddr,
                                int flags) {
  
    struct ifreq req;
    struct sockaddr_in *addrp;
  
    strcpy(req.ifr_name, device);
  
    if (ioctl(s, SIOCGIFBRDADDR, &req)) {
        piranha_log(flags,
            (char *) "SIOCGIFBRDADDR failed: %s", strerror(errno));
        return;
    }
  
    addrp = (struct sockaddr_in *) &req.ifr_addr;
    memcpy(addr, &addrp->sin_addr, sizeof(*addr));
    
    if (ioctl(s, SIOCGIFHWADDR, &req)) {
      piranha_log(flags,
		  (char *) "SIOCGIFHWADDR failed: %s", strerror(errno));
      return;
    }
    
    memcpy(hwaddr, req.ifr_hwaddr.sa_data, IFHWADDRLEN);
}





/*
**  disableInterface() -- Disable ('ifconfig xxx down') an interface
*/

static void disableInterface(char * device, int flags) {

    pid_t child;
    char *ifconfigArgs[5];
    int i=0;

    ifconfigArgs[i++] = (char *) "/sbin/ifconfig";

    ifconfigArgs[i++] = device;
    ifconfigArgs[i++] = (char *) "down";
    ifconfigArgs[i++] = NULL;
    ifconfigArgs[4]   = NULL;
    
    if (pulse_debug)
        piranha_log(flags,
            (char *)
            "DEBUG -- Executing '/sbin/ifconfig %s down'", device);
  
    if (!(child = fork())) {
        logArgv(flags, ifconfigArgs);
        execv(ifconfigArgs[0], ifconfigArgs);
        exit(-1);
    }
  
    waitpid(child, NULL, 0);
}





/*
**  sendArps() -- send arps for a device, mac address, and ip address combo
*/

static void sendArps(   char * device,
                        struct in_addr * address,
                        struct in_addr * vip_nmask,
                        int num, 
                        int flags) {
    int i;
    int s;
    char    *floatAddr  = strdup(inet_ntoa(*address));
    char    *netmask    = strdup (inet_ntoa (*vip_nmask));

    struct in_addr bcast;
  
    char    *broadcast = NULL;
    struct  in_addr addr;
    char    hwaddr[IFHWADDRLEN];
    char    hwname[20];
    pid_t   child;
    char    *ifconfigArgs[10];
    char    *ifconfigArgsFull[10];
    char    *sendArpArgv[10];
    int     netmask_specified = 0;

        
    sendArpArgv[0] = (char *) "/usr/sbin/send_arp";
    sendArpArgv[1] = (char *) "-i";
    sendArpArgv[2] = device;
    sendArpArgv[3] = floatAddr;
    sendArpArgv[4] = hwname;
    sendArpArgv[5] = broadcast;
    sendArpArgv[6] = (char *) "ffffffffffff";
    sendArpArgv[7] = NULL;
  
    ifconfigArgs[0] = (char *) "/sbin/ifconfig";
    ifconfigArgs[1] = device;
    ifconfigArgs[2] = floatAddr;
    ifconfigArgs[3] = (char *) "up";
    ifconfigArgs[4] = NULL;

    if (vip_nmask) {
        if (vip_nmask->s_addr)
            netmask_specified = -1;
        /* if network mask ptr is OK, AND it points to a non-zero value */
    }

  
    if (netmask_specified) {
        /* A small calculation for to get the broadcast */
        bcast.s_addr =
            (address->s_addr & vip_nmask->s_addr) | ~vip_nmask->s_addr;
  
        ifconfigArgsFull[0] = (char *) "/sbin/ifconfig";
        ifconfigArgsFull[1] = device;
        ifconfigArgsFull[2] = floatAddr;
        ifconfigArgsFull[3] = (char *) "netmask";
        ifconfigArgsFull[4] = netmask;
        ifconfigArgsFull[5] = (char *) "broadcast";
        ifconfigArgsFull[6] = (char *) inet_ntoa(bcast);
        ifconfigArgsFull[7] = (char *) "up";
        ifconfigArgsFull[8] = NULL;
    }


    if (!(child = fork())) {
        if (netmask_specified) {
            logArgv(flags, ifconfigArgsFull);

            if (pulse_debug)
                piranha_log(flags, (char *)
                    "DEBUG -- Executing '/sbin/ifconfig %s %s netmask %s up'",
                    device, floatAddr, netmask);
            execv(ifconfigArgs[0], ifconfigArgsFull);

        } else {
            logArgv(flags, ifconfigArgs);

            if (pulse_debug)
                piranha_log(flags, (char *)
                    "DEBUG -- Executing '/sbin/ifconfig %s %s up'",
                    device, floatAddr);
            execv(ifconfigArgs[0], ifconfigArgs);
        }
        exit(-1);
    }
  
    waitpid(child, NULL, 0);
    device = strdup(device);
  
    if (strchr(device, ':'))
        *strchr(device, ':') = '\0';
  
    sendArpArgv[2] = device;
  
    s = socket(AF_INET, SOCK_DGRAM, 0);
    getInterfaceInfo(s, device, &addr, hwaddr, flags);
    close(s);
  
    for (i = 0; i < 6; i++) {
        sprintf(hwname + (i * 2), "%02X", ((unsigned int) hwaddr[i]) & 0xFF);
    }
  
    broadcast = inet_ntoa(addr);
  
    sendArpArgv[4] = hwname;
    sendArpArgv[5] = broadcast;
  
    logArgv(flags, sendArpArgv);
  
    if (pulse_debug)
        piranha_log(flags,
            (char *) "DEBUG -- Executing '/usr/sbin/send_arp'");
  
    for (i = 0; i < num; i++) {
        if (!(child = fork())) {
            execv(sendArpArgv[0], sendArpArgv);
            exit(1);
        }
    
        waitpid(child, NULL, 0);
        sleep(1);
    }
}





/*
**  deactivateFos() --  stop fos process and shut down all VIP interfaces
*/

static void deactivateFos(struct lvsConfig * config, int flags, int cleanup) {
  
    int i;
    int j;
  
    if (flags & PULSE_FLAG_NORUN)
        return;
  
    if (HA_Process > 0) {
        kill(HA_Process, SIGTERM);
        waitpid(HA_Process, NULL, 0);
        HA_Process = 0;
    }
  
  
    if (cleanup) {
        /* deactivate the interfaces */
        for (i = 0; i < config->numFailoverServices; i++) {
            if (!config->failoverServices[i].isActive)
                continue;
      
            for (j = 0; j < i; j++) {
                if (!memcmp(&config->failoverServices[i].virtualAddress,
                    &config->failoverServices[j].virtualAddress,
                    sizeof(config->failoverServices[i].virtualAddress)))
                break;
            }
      
            if (j == i)
                disableInterface(config->failoverServices[i].virtualDevice,
                    flags);
        }
    }
}






/*
**  activateFosServices() -- stop old fos & monitors, start new fos & IP
**                           services. set up devices and arps.
**
*/

static pid_t activateFosServices(struct lvsConfig * config, int flags) {
  
    pid_t child = 0;
    pid_t fake1;
    pid_t * fake = alloca(sizeof(*fake) * (config->numVirtServers + 1));
    int i;
    int j;
    int numFakes;
    char *argv[7];

    argv[0] = fosBinary;
    argv[1] = (char *) "--active";
    argv[2] = (char *) "-c";
    argv[3] = configFile;
    argv[4] = (char *) "--nodaemon";
    argv[5] = NULL;
    argv[6] = NULL;
                

    if (HA_Process)
        deactivateFos(config, flags, 0);
        /* Shutdown old monitor programs if necessary */
  
    /* Run fake for 5 seconds on each interface */
    if (!(fake1 = fork())) {
        if (fork())  {
            exit(0);
        }
    
        /* we're init's problem now */
    
        numFakes = 0;
    
        for (i = 0; i < config->numFailoverServices; i++) {
            if (!config->failoverServices[i].isActive)
                continue;
      
            for (j = 0; j < i; j++) {
                if (!memcmp(&config->failoverServices[i].virtualAddress,
                        &config->failoverServices[j].virtualAddress,
                        sizeof(config->failoverServices[i].virtualAddress)))
                    break;
            }
      
            if (j == i) {
                if (!(fake[numFakes++] = fork())) {
                    sendArps(config->failoverServices[i].virtualDevice, 
                        &config->failoverServices[i].virtualAddress,
			            &config->failoverServices[i].vip_nmask,
                        5,
			flags);
                    exit(1);
                }
            }
        }
    
        for (i = 0; i < numFakes; i++)
            waitpid(fake[i], NULL, 0);
        piranha_log(flags, (char *) "gratuitous fos arps finished");
        exit(0);
    }
  
    waitpid(fake1, NULL, 0);
  
    if (flags & PULSE_FLAG_ASDAEMON)
        argv[4] = (char *) "--nofork";          /* Overwrites --nodaemon */

    if (pulse_debug)
       argv[5] = (char *) "-v";                 /* Pass along debug mode */
  
    logArgv(flags, argv);
  
    if (pulse_debug)
        piranha_log(flags, (char *) "DEBUG -- Executing %s --active",
        fosBinary);
  
    if (!(flags & PULSE_FLAG_NORUN)) {
        if (!(child = fork())) {
            execv(fosBinary, argv);
            exit(-1);
        }
    }
  
    return child;
}






/*
**  activateFosMonitors() -- stop old fos & services, start new fos & monitors
**
*/

static pid_t activateFosMonitors(   struct lvsConfig * config, int flags) {
  
    pid_t child = 0;
    int i,j;
    pid_t * fake    = alloca(sizeof(*fake) * (config->numVirtServers + 1));

    char *argv[7];

    argv[0] = fosBinary;
    argv[1] = (char *) "--monitor";
    argv[2] = (char *) "-c";
    argv[3] = configFile;
    argv[4] = (char *) "--nodaemon";
    argv[5] = NULL;
    argv[6] = NULL;
                
    if (HA_Process)
        (void) deactivateFos(config, flags, 0);
        /* Shut down old services if necessary */
  
    /* deactivate the interfaces */
    for (i = 0; i < config->numFailoverServices; i++) {
        if (!config->failoverServices[i].isActive)
            continue;
    
        for (j = 0; j < i; j++) {
            if (!memcmp(&config->failoverServices[i].virtualAddress,
                    &config->failoverServices[j].virtualAddress,
                    sizeof(config->failoverServices[i].virtualAddress)))
                break;
        }
    
        if (j == i)
            disableInterface(config->failoverServices[i].virtualDevice,
                flags);
    }
  
  
    if (flags & PULSE_FLAG_ASDAEMON)
        argv[4] = (char *) "--nofork";          /* Overwrites --nodaemon */

    if (pulse_debug)
       argv[5] = (char *) "-v";                 /* Pass along debug mode */
   
      logArgv(flags, argv);
  
    if (pulse_debug)
        piranha_log(flags, (char *) "DEBUG -- Executing %s --monitor",
            fosBinary);
  
    if (!(flags & PULSE_FLAG_NORUN)) {
        if (!(child = fork())) {
            execv(fosBinary, argv);
            exit(-1);
        }
    }

    return child;
}





/*
**  activateLvs() -- Start LVS program, then send arps for all VIPs
*/

static pid_t activateLvs(struct lvsConfig * config, int flags) {
  
    pid_t child = 0;
    pid_t fake1;
    pid_t * fake = alloca(sizeof(*fake) * (config->numVirtServers + 1));
    int i;
    int j;
    int numFakes;

    char *argv[5];
 
    argv[0] = lvsBinary;
    argv[1] = (char *) "--nodaemon";
    argv[2] = (char *) "-c";
    argv[3] = configFile;
    argv[4] = NULL;
            

    if (flags & PULSE_FLAG_ASDAEMON)
        argv[1] = (char *) "--nofork";
  
    if (pulse_debug)
      logArgv(flags, argv);
  
    if (!(flags & PULSE_FLAG_NORUN)) {
        if (!(child = fork())) {
            execv(lvsBinary, argv);
            exit(-1);
        }
    }
  
  
    /* Run fake for 5 seconds on each interface */
    if (!(fake1 = fork())) {
        if (fork())  {
            exit(0);
        }
    
       /* we're init's problem now */
    
        numFakes = 0;
    
        if (!(fake[numFakes++] = fork())) {
            if ((config->redirectType == LVS_REDIRECT_NAT) &&
                    (config->natRouterDevice != NULL))
                sendArps(config->natRouterDevice,
			    &config->natRouter,
			    &config->nat_nmask,
                5,
			    flags);
                /* send arps to nat router (if any) */
            exit(1);
        }
    
    
        for (i = 0; i < config->numVirtServers; i++) {
            if (!config->virtServers[i].isActive)
                continue;
      
            if (config->virtServers[i].failover_service) {
                piranha_log(flags,
                    (char *) "Warning; skipping failover service");
    	        continue;       /* This should not be possible anymore */
            }
      
            for (j = 0; j < i; j++) {
                if (!memcmp(&config->virtServers[i].virtualAddress,
                        &config->virtServers[j].virtualAddress,
                        sizeof(config->virtServers[i].virtualAddress)))
                    break;
            }
      
            if (j == i) {
                if (!(fake[numFakes++] = fork())) {
                    sendArps(config->virtServers[i].virtualDevice, 
                        &config->virtServers[i].virtualAddress,
			            &config->virtServers[i].vip_nmask,
		                5,
			            flags);
                    exit(1);
                }
            }
        }
    
        for (i = 0; i < numFakes; i++)
            waitpid(fake[i], NULL, 0);

        piranha_log(flags, (char *) "gratuitous lvs arps finished");
        exit(0);
    }
  
    waitpid(fake1, NULL, 0);
    return child;
}





/*
**  deactiveLVS() --  stop LVS process and shut down all VIP interfaces
*/

static void deactivateLvs(struct lvsConfig * config, int flags) {
  
    int i, j;
  

    if ((flags & PULSE_FLAG_NORUN) || !(flags & PULSE_FLAG_AMACTIVE))
        return;
  
    kill(HA_Process, SIGTERM);
  
    /* XXX need a timeout here, which means an alarm, which is gross */
    waitpid(HA_Process, NULL, 0);

    HA_Process = 0;
  
    /* deactivate the interfaces */
    for (i = 0; i < config->numVirtServers; i++) {
        if (!config->virtServers[i].isActive)
            continue;
    
        if (config->virtServers[i].failover_service) {
            piranha_log(flags, (char *) "Warning; skipping failover service");
            continue;       /* This should not be possbile anymore */
        }
    
        for (j = 0; j < i; j++) {
            if (!memcmp(&config->virtServers[i].virtualAddress,
		            &config->virtServers[j].virtualAddress,
		            sizeof(config->virtServers[i].virtualAddress)))
	            break;
        }
    
        if (j == i)
            disableInterface(config->virtServers[i].virtualDevice, flags);
    }
  
    if ((config->redirectType == LVS_REDIRECT_NAT) &&
            (config->natRouterDevice != NULL))
        disableInterface(config->natRouterDevice, flags);
}





/*
**  createSocket() -- Create heartbeat socket
*/

static int createSocket(struct in_addr * addr, int port, int flags) {
  
    int sock;
    struct sockaddr_in address;
  

    if ((sock = socket(PF_INET, SOCK_DGRAM, 0)) < 0) {
        piranha_log(flags, (char *) "failed to create UDP socket: %s",
        strerror(errno));
        return 1;
    }
  
    address.sin_family = AF_INET;
    address.sin_port = htons(port);
    memcpy(&address.sin_addr, addr, sizeof(*addr));
  
    if (bind(sock, (struct sockaddr *) &address, sizeof(address))) {
        piranha_log(flags, (char *) "failed to bind to heartbeat address: %s",
            strerror(errno));
        return -1;
    }
  
    return sock;
}






void processChildTerm(struct lvsConfig *config, int *flags) {
  
    pid_t child = 0;


    while ((child = waitpid(-1, NULL, WNOHANG)) > 0) {
        if (child == HA_Process) {
            HA_Process = 0;
      
            if (*flags & PULSE_FLAG_AMACTIVE) {
	            piranha_log(*flags,
		            (char *) "fos process exited -- restarting it");
                } else {
	                piranha_log(*flags,
		            (char *)
		            "fos process exited -- performing service failover");
	        }

	        *flags |= PULSE_FLAG_AMACTIVE;

		    if ((*flags & PULSE_FLAG_AMMASTER) == 0)
		        *flags |= PULSE_FLAG_FAILPARTNER;
		    /*
		    ** In a tie, master wins. But in this failure case,
		    ** we want to win the tie this time!
		    */

	        HA_Process = activateFosServices(config, *flags);

        } else
            piranha_log(*flags,
		        (char*) "Skipping death of unknown child %d", child);
    }
}






/*
**  run() -- main login routine
*/

static int run(struct lvsConfig * config, int sock, int flags) {
  
    struct timeval now;
    struct timeval sendHeartBeat;
    struct timeval needHeartBeat;
    struct timeval timeout;
    struct timeval * smaller;
    fd_set fdset;
    int done = 0;
    int rc = 0;
    unsigned int magic; /* beware of sign change during assignment */
    socklen_t size; /* See page 27 of R.Stevens UNIX Net. prog. vol1 ed 2 */
    struct sockaddr_in partner;
    struct sockaddr_in sender;
    int use_fos = 0;
    sigset_t sigs;
  

    if (config->lvsServiceType == LVS_SERVICE_TYPE_FOS)
        use_fos = -1;

    sigemptyset(&sigs);
    sigaddset(&sigs, SIGINT);
    sigaddset(&sigs, SIGTERM);
    sigaddset(&sigs, SIGHUP);
    sigaddset(&sigs, SIGCHLD);

/***** ### disable incomplete processing of USR 1 & 2 ###
    sigaddset(&sigs, SIGUSR1);
    sigaddset(&sigs, SIGUSR2);
********************************************************/

    sigprocmask(SIG_BLOCK, &sigs, NULL);

    signal(SIGINT,  handleTermSignal);
    signal(SIGTERM, handleTermSignal);

/***** ### disable incomplete processing of USR 1 & 2 ###
    signal(SIGUSR1, handleUser1Signal);
********************************************************/


    if (config->lvsServiceType == LVS_SERVICE_TYPE_FOS) {
        use_fos = -1;
        signal(SIGCHLD, handleChildDeath);
        heartbeat_running_magic = (int) FOS_HEARTBEAT_RUNNING_MAGIC;
        heartbeat_stopped_magic = (int) FOS_HEARTBEAT_STOPPED_MAGIC;
        /* incompatible heartbeats will ensure the user doesn't mix systems */
    }
  
    if (use_fos == 0)
        sigprocmask(SIG_UNBLOCK, &sigs, NULL);

    flags |= PULSE_FLAG_SUPPRESS_ERRORS;    /* Suppress useless start errs */
  
    if (flags & PULSE_FLAG_AMACTIVE) {
        if (use_fos) {
            piranha_log(flags,
		        (char *) "Forced active on startup: activating fos");
            HA_Process = activateFosServices(config, flags);
        } else {
            piranha_log(flags,
		        (char *) "Forced active on startup: activating lvs");
            HA_Process = activateLvs(config, flags);
        }
    
    } else {
        if (use_fos) {
            piranha_log(flags, (char *) "Starting Failover Service Monitors");
            sigprocmask(SIG_BLOCK, &sigs, NULL);
            HA_Process = activateFosMonitors(config, flags);
            sigprocmask(SIG_UNBLOCK, &sigs, NULL);
        }
    }
  
    flags &= (~PULSE_FLAG_SUPPRESS_ERRORS);


    partner.sin_family  = AF_INET;
    partner.sin_port    = htons(config->heartbeatPort);
  
    if (flags & PULSE_FLAG_AMMASTER)
        memcpy(&partner.sin_addr, &config->backupServer, 
           sizeof(struct in_addr));
    else
        memcpy(&partner.sin_addr, &config->primaryServer, 
           sizeof(struct in_addr));
  
    gettimeofday(&now, NULL);
    needHeartBeat            =  sendHeartBeat = now;
    sendHeartBeat.tv_sec    +=  config->keepAliveTime;
    needHeartBeat.tv_sec    +=  config->deadTime;
  


    if (use_fos == 0) {
      sigemptyset(&sigs);
      sigaddset(&sigs, SIGCHLD);
    } else
        sigprocmask(SIG_UNBLOCK, &sigs, NULL);


    /*
    **  The BIG loop!
    */

    while (!done) {

        if ((childSignal != 0) && (use_fos != 0)) {
            sigprocmask(SIG_BLOCK, &sigs, NULL);
            signal(SIGCHLD, handleChildDeath);
            processChildTerm(config, &flags);
            childSignal = 0;
            sigprocmask(SIG_UNBLOCK, &sigs, NULL);
        }


        if (termSignal) {
            piranha_log(flags,
                (char *) "Terminating due to signal %d", termSignal);
            if (use_fos)
                deactivateFos(config, flags, -1);
            else
                deactivateLvs(config, flags);

            return 0;
        }
      
      
/*** ### Start of Block - disable incomplete processing of USR 1 & 2  ### */
if (0) {
        if (User1Signal) {
            if (flags & PULSE_FLAG_AMACTIVE) {
                piranha_log(flags,
                    (char *)
                    "Terminating and restarting CLUSTER due to signal USR1");
                if (use_fos)
                    deactivateFos(config, flags, -1);
                else
                    deactivateLvs(config, flags);
                return 2;
            } else {
                piranha_log(flags,
                    (char *)
                    "Restarting JUST THIS NODE due to signal USR1 (and we are not the active node");
                if (use_fos)
                    deactivateFos(config, flags, -1);
                else
                    deactivateLvs(config, flags);
                return 2;
            }
        }
} 
/* ### **** End of Block ********************************************* ### */
      

        if (needHeartBeat.tv_sec < sendHeartBeat.tv_sec)
            smaller = &needHeartBeat;
        else if (needHeartBeat.tv_sec > sendHeartBeat.tv_sec)
            smaller = &sendHeartBeat;
        else if (needHeartBeat.tv_usec < sendHeartBeat.tv_usec)
            smaller = &needHeartBeat;
        else
            smaller = &sendHeartBeat;

        if (pulse_debug) {
            if (smaller == &needHeartBeat)
                piranha_log(flags, "DEBUG -- setting NEED_heartbeat timer");
            else
                piranha_log(flags, "DEBUG -- setting SEND_heartbeat timer");
        }

        gettimeofday(&now, NULL);
  
        timeout.tv_sec = -1;
    
        if (smaller->tv_sec >= now.tv_sec)
            timeout.tv_sec = smaller->tv_sec - now.tv_sec;
    
        if (smaller->tv_usec >= now.tv_usec)
            timeout.tv_usec = smaller->tv_usec - now.tv_usec;
        else {
            timeout.tv_usec = (smaller->tv_usec + 1000000) - now.tv_usec;
            timeout.tv_sec--;
        }
    
    
        if (timeout.tv_sec >= 0) {
      
            FD_ZERO(&fdset);
            FD_SET(sock, &fdset);
      
            /*  There's a small race between this check and the select(), but
	            I don't know how to avoid it? */
      
             do   
             	rc = select(sock + 1, &fdset, NULL, NULL, &timeout);
             while (rc == -1 && errno == EINTR);
      
            if (rc < 0) {
	            piranha_log(flags,
                    (char *) "select() failed: %s", strerror(errno));
	            return -1;
	
            } else if (rc) {
	            /* ADDED 16-Aug-2000 Lars Kellogg-Stedman <lars@larsshack.org>
		     * "size" was not being initialized properly.
		     */
	            size = sizeof(struct sockaddr);
	            rc = recvfrom(sock, &magic, sizeof(magic), 0,
                    (struct sockaddr *)&sender, &size);
	
                if (rc < 0) {
	                if (errno != ECONNREFUSED)
	                    piranha_log(flags, (char *) "recvfrom() failed: %s",
                            strerror(errno));
	  
                } else if (size != sizeof(sender)) {
	                piranha_log(flags,
		                (char *) "Bad remote address size from recvfrom");
	  
                } else if (memcmp(&partner.sin_addr, &sender.sin_addr, 
			            sizeof(partner.sin_addr))) {
                    if (pulse_debug)
	                    piranha_log(flags,
                            (char *) "Unexpected heartbeat from %s",
		                    inet_ntoa(sender.sin_addr));
	  
	            } else if (rc != sizeof(magic)) {
	                piranha_log(flags, (char *) "bad heartbeat packet");
	  
	            } else if ( (magic != heartbeat_running_magic)      &&
		                    (magic != heartbeat_stopped_magic)      &&
		                    (magic != FOS_HEARTBEAT_STOPME_MAGIC)   &&
                            (magic != HEARTBEAT_HOLD_MAGIC)         &&
                            (magic != HEARTBEAT_HELD_MAGIC)         ) {
	                piranha_log(flags,
                        (char *)
                        "Incompatible heartbeat received -- other system not using identical services");
	  

	            } else {
	            /*
                    **  At this point, we got our heartbeat and it will
                    **  tell us the state of the other machine.
                    **
                    **  If both are running, backup stops and master rearps. 
                    **  If neither is running, master starts. Otherwise,
                    **  keep the status quo.
                    */

                    if (pulse_debug)
                        piranha_log(flags, (char *)
                            "DEBUG -- Received Heartbeat from partner");

                    if (magic == FOS_HEARTBEAT_STOPME_MAGIC) {

                        /*
                        **  Special case: remote fos has told me that although
                        **  I am the master, it wants to win the argument
                        */

                        if (    ((flags & PULSE_FLAG_AMACTIVE) != 0) &&
                                ((flags & PULSE_FLAG_AMMASTER) != 0)  ) {
                            /* Only applies if we are master and active */
                            piranha_log(flags,
                                (char *) "PARTNER HAS TOLD US TO GO INACTIVE!");
                            HA_Process = activateFosMonitors(config, flags);
                            flags &= (~PULSE_FLAG_AMACTIVE);
                            /* go inactive if not already */
                        }
                            /* else ignore -- we are already inactive */
                        
	                    magic = heartbeat_running_magic;
                        /* Other side was running */
                    }


/*** ### Start of Block - "hold" and "held" heartbeat logic incomplete ### */
if (0) {  

                    if (magic == HEARTBEAT_HOLD_MAGIC) {

                        /*
                        **  Special case: Remote active node has told us to
                        **  freeze until we see active heartbeats again.
                        */

                        if (!(flags & PULSE_FLAG_AMACTIVE)) {
                            piranha_log(flags,
                                (char *)
                                "PARTNER HAS TOLD US TO GO INACTIVE AND WAIT!");
                            deactivateFos(config, flags, -1);
                            flags |= PULSE_FLAG_ONHOLD;
                        }
                            /* else ignore -- we are already inactive */
                        
	                    magic = heartbeat_running_magic;
                        /* Other side was running */
                    }



                    if (magic == HEARTBEAT_HELD_MAGIC) {

                        /*
                        **  Special case: Remote node has told us they are
                        **  on hold until they see ACTIVE heartbeats from
                        **  us again.
                        */

                        if (!(flags & PULSE_FLAG_AMACTIVE)) {
                            piranha_log(flags,
                                (char *)
                                "PARTNER HAS TOLD US TO GO INACTIVE AND WAIT!");
                            deactivateFos(config, flags, -1);
                            flags |= PULSE_FLAG_ONHOLD;
                        }
                            /* else ignore -- we are already inactive */
                        
	                magic = heartbeat_running_magic;
                        /* Other side was running */
                    }


}
/* ### **** End of Block ********************************************** ### */


                    /*
                    **  We got a "running" (active) status from partner.
                    **  Compare with our states and process...
                    */

	                if (magic == heartbeat_running_magic) {

	                    if ( ((flags & PULSE_FLAG_AMACTIVE) != 0) &&
		                     ((flags & PULSE_FLAG_AMMASTER) == 0) ) {
	      
	                        /* Both are running and we are backup */

	                        if (use_fos) {
		                        /* Failover Services */
                                if (flags & PULSE_FLAG_FAILPARTNER) {
		                            piranha_log(flags, (char *)
			                            "partner active: we want control!");

                                    /******* ###
                                    if ( (flags &
                                          PULSE_FLAG_SUPPRESS_TC_MSG) == 0) {
                                        piranha_log(flags,
			                                (char *)
			                                "Notifying partner WE are taking control!");
                                        flags |= PULSE_FLAG_SUPPRESS_TC_MSG;
                                    }
                                    ******/

                                } else {
		                            piranha_log(flags,
			                            (char *)
			                            "partner active: deactivating services");
                                    HA_Process = activateFosMonitors(config,
                                        flags);
                                    flags &= (~PULSE_FLAG_AMACTIVE);
                                }
                                    /* else ignore it and tell him to go */
	                        } else {
		                        /* Virtual Services */
		                        piranha_log(flags,
			                        (char *)
                                    "partner active: deactivating lvs");
		                        deactivateLvs(config, flags);
                                flags &= (~PULSE_FLAG_AMACTIVE);
	                        }
	      


	                    } else if ( (flags & PULSE_FLAG_AMACTIVE) &&
		                            (flags & PULSE_FLAG_AMMASTER)) {
	      
	                        /* Both are running & we are master */

	                        if ((config->redirectType == LVS_REDIRECT_NAT) &&
		                            (config->natRouterDevice != NULL) &&
		                            (use_fos == 0)) {
		                        piranha_log(flags,
			                        (char *)
                                    "partner active: resending arps");
		                        sendArps(config->natRouterDevice,
			                        &config->natRouter,
						            &config->nat_nmask,
						            5,
						            flags);
	                        } else
		                        piranha_log(flags, (char *) "partner active");
	                    }




                    /*
                    **  We got a "stopped" (inactive) status from partner.
                    **  Contrast & compare...
                    */
	    
	                } else if (magic == heartbeat_stopped_magic) {

                        flags &= ( ~(PULSE_FLAG_FAILPARTNER |
                            PULSE_FLAG_SUPPRESS_TC_MSG) );
                        /* Don't tell partner to go inactive if he has */

	                    if ( ((flags & PULSE_FLAG_AMACTIVE) == 0) &&
		                        ((flags & PULSE_FLAG_AMMASTER) != 0) ) {

	                        /* inactive master became active */

	                        if (use_fos) {
		                        piranha_log(flags,
			                        (char *)
                                    "partner inactive: activating fos");
    	                        flags |= PULSE_FLAG_AMACTIVE;
		                        HA_Process = activateFosServices(config,
                                    flags);
	                        } else {
		                        piranha_log(flags,
			                        (char *)
                                    "partner inactive: activating lvs");
		                        HA_Process = activateLvs(config, flags);
    	                        flags |= PULSE_FLAG_AMACTIVE;
	                        }

	                    }
	                }

	                gettimeofday(&needHeartBeat, NULL);
	                needHeartBeat.tv_sec += config->deadTime;
	            }

            } else {
	            /* rc = 0. timed out, we must have something to do */
                    timeout.tv_sec = -1;
            }
        }
    


        /*
        ** Send Heartbeat
        */
    
        if (timeout.tv_sec < 0) {
            if (smaller == &sendHeartBeat) {

    	        if (flags & PULSE_FLAG_AMACTIVE)
    	            magic = heartbeat_running_magic;
    	        else
    	            magic = heartbeat_stopped_magic;

                if (flags & PULSE_FLAG_FAILPARTNER) {
                    if ( (flags & PULSE_FLAG_SUPPRESS_TC_MSG) == 0) {
                        piranha_log(flags, (char *)
                            "Notifying partner WE are taking control!");
                        flags |= PULSE_FLAG_SUPPRESS_TC_MSG;
                    }

    	            magic = FOS_HEARTBEAT_STOPME_MAGIC;
                    /* Special case where fos forces partner to go inactive */
		        }

    	        if (config->backupActive) {
                    if (pulse_debug)
                        piranha_log(flags, (char *)
                           "DEBUG -- Sending heartbeat...");
    	            rc = sendto(sock, &magic, sizeof(magic), (int) 0,
    		            (struct sockaddr *)&partner,
                        (socklen_t) sizeof(partner));
                    if ((rc == -1) && (pulse_debug != 0)) {
                        piranha_log(flags, (char *)
                             "Warning -- failed to send heartbeat: %s",
                             strerror(errno));
                    }
                }

                gettimeofday(&sendHeartBeat, NULL);
    	        sendHeartBeat.tv_sec += config->keepAliveTime;
	
            } else {
    	        if (!(flags & PULSE_FLAG_AMACTIVE)) {

    	            if (use_fos) {
    	                piranha_log(flags,
    			            (char *)
                            "partner dead: activating failover services");
    	                flags |= PULSE_FLAG_AMACTIVE;
    	                HA_Process = activateFosServices(config, flags);
                        flags &= ( ~(PULSE_FLAG_SUPPRESS_TC_MSG |
                                     PULSE_FLAG_FAILPARTNER) );
    	            } else {
    	                piranha_log(flags,
    			            (char *) "partner dead: activating lvs");
                        HA_Process = activateLvs(config, flags);
    	                flags |= PULSE_FLAG_AMACTIVE;
                    }
                } else {
                    /* Partner is dead, and we are already active */
                    flags &= ( ~(PULSE_FLAG_SUPPRESS_TC_MSG |
                                PULSE_FLAG_FAILPARTNER) );
                }
	
                gettimeofday(&needHeartBeat, NULL);
                needHeartBeat.tv_sec += config->deadTime;
            }
        }
    }
  
    return 0;
}





/*
**  amMaster() --   Determine if we are primary or backup node.
*/

static int amMaster(struct lvsConfig * config, int flags) {
  
    int fd;
    int i;
    char buf[1024];
    char * start, * end;
    int s = socket(AF_INET, SOCK_DGRAM, 0);
    struct in_addr addr;
  
    if ((fd = open("/proc/net/dev", O_RDONLY)) < 0) {
        piranha_log(flags, (char *) "failed to open /proc/net/dev!\n");
        return 1;
    }
  
    i = read(fd, buf, sizeof(buf));
    close(fd);
    buf[i] = '\0';
  
  
    /* skip the first two lines */
  
    start = strchr(buf, '\n');
    if (!start)
        return 0;
  
    start = strchr(start + 1, '\n');
    if (!start)
        return 0;
  
    start++;
  
    while (start && *start) {
        while (isspace(*start)) start++;
            end = strchr(start, ':');
        if (!end)
            return 0;
        *end = '\0';
    
        if (strcmp(start, "lo")) {
            if (!getInterfaceAddress(s, start, &addr, flags) &&
                    !memcmp(&addr, &config->primaryServer, sizeof(addr))) {
                close(s);
                return 1;
            }
        }

        start = strchr(end + 1, '\n');
    
        if (start)
            start++;
    }
  
    close(s);
    return 0;
}



int main(int argc, const char *argv[], char *env[]) {
  
    int			        noDaemon    = 0;
    int			        flags       = 0;
    int			        rc          = 0;
    int			        fd;
    int			        line;
    struct lvsConfig    config;
    int 		        master      = 0;
    int 		        noRun       = 0;
    int 		        teststart   = 0;
    int 		        forceActive = 0;
    poptContext		    optCon;
    char                *fargv[40];
    char                **fargp     = fargv;
    pid_t               child;
    int                 i;
    int                 service_count = 0;
    int                 display_ver   = 0;
  
  
    struct poptOption options[] = {

        { "configfile", 'c', POPT_ARG_STRING, &configFile, 0, 
        N_("Configuration file"), N_("configfile") },

        { "nodaemon", 'n', 0, &noDaemon, 0,
        N_("Don't run as a daemon") },

        { "test-start", 't', 0, &teststart, 0,
        N_("Don't actually run lvs or fos") },

        { "verbose", 'v', 0, &pulse_debug, 0,
        N_("Display debugging information") },

        { "forceactive", '\0', 0, &forceActive, 0,
        N_("Force into the active state") },

        { "lvs", '\0', POPT_ARG_STRING, &lvsBinary, 0,
        N_("Location of lvs binary (defaults /usr/sbin/lvs)") },

        { "fos", '\0', POPT_ARG_STRING, &fosBinary, 0,
        N_("Location of fos binary (defaults /usr/sbin/fos)") },

        { "norun", '\0', 0, &noRun, 0,
        N_("Same as 'test-start'") },

        { "version", '\0', 0, &display_ver, 0,
        N_("Display program version") },

        POPT_AUTOHELP
        { 0, 0, 0, 0, 0 }
    };
  
    
    optCon = poptGetContext("pulse", argc, argv, options, 0);
    poptReadDefaultConfig(optCon, 1);
    
    if ((rc = poptGetNextOpt(optCon)) < -1) {
        fprintf(stderr, _("pulse: bad argument %s: %s\n"), 
        poptBadOption(optCon, POPT_BADOPTION_NOALIAS), 
            poptStrerror(rc));
        return 1;
    }
    
    if (poptGetArg(optCon)) {
        fprintf(stderr, _("pulse: unexpected arguments\n"));
        return 1;
    }
    
    poptFreeContext(optCon);
    logName((char *) "pulse");

    if (display_ver) {
        printf("Program Version:\t%s\n", _PROGRAM_VERSION);
	    printf("Built:\t\t\t%s\n", DATE); /* DATE epulled from Makefile */
	    printf("A component of:\t\tpiranha-%s-%s\n", VERSION, RELEASE);
        return 0;
    }


    if (noDaemon)
        flags |= LVS_FLAG_PRINTF;
    else
        flags |= PULSE_FLAG_ASDAEMON | LVS_FLAG_SYSLOG;
    
    if (forceActive)
        flags |= PULSE_FLAG_AMACTIVE;
    
    if (noRun | teststart)
        flags |= PULSE_FLAG_NORUN;
    
    if ((fd = open(configFile, O_RDONLY)) < 0) {
        fprintf(stderr, "pulse: failed to open %s\n", configFile);
        return 1;
    }
    
    if ((rc = lvsParseConfig(fd, &config, &line))) {
        fprintf(stderr, "pulse: error parsing %s on line %d.\n",
            configFile, line);
        return 1;
    } else
        lvsRelocateFS(&config);
    /* Move failover services and Clean up virtual server list */
    
    close(fd);
    
    master = amMaster(&config, flags);
    flags |= master ? PULSE_FLAG_AMMASTER : 0;
    
    if (config.backupActive) {
        if (config.backupServer.s_addr == 0) {
            config.backupActive = 0;
            piranha_log(flags,
                (char *)
                "Undefined backup node marked as active? -- clearing that!");
        }
    }


    if (!rc) {
        if (config.lvsServiceType == LVS_SERVICE_TYPE_FOS) {
            for (i = 0; i < config.numFailoverServices; ++i) {
                if (config.failoverServices[i].isActive)
                    ++service_count;
            }

            if (!service_count) {
                fprintf(stderr,
                    "pulse: no active fos services defined in %s\n",
                    configFile);
                return 1;
            }
        } else {
            for (i = 0; i < config.numVirtServers; ++i) {
                if (config.virtServers[i].isActive)
                    ++service_count;
            }
            if (!service_count) {
                fprintf(stderr,
                    "pulse: no active lvs services defined in %s\n",
                    configFile);
                return 1;
            }
        }
    }


    if ((fd = createSocket(master ? &config.primaryServer : 
            &config.backupServer, config.heartbeatPort, flags)) < 0) {
        fprintf(stderr,
            "pulse: cannot create heartbeat socket. running as root?\n");
        return 1;
    }
    
    if (flags & PULSE_FLAG_ASDAEMON) {
        if (daemonize(flags)) 
            exit(0);
    }
    
    
    if (master) {
        piranha_log(flags, (char *) "STARTING PULSE AS MASTER");

        if (!config.backupActive) {
            if ( (config.lvsServiceType == LVS_SERVICE_TYPE_FOS) &&
                    (forceActive == 0) )  {
                piranha_log(flags,
                    (char *)
                    "Warning: FOS backup node not defined -- skipping checks");
                    flags |= PULSE_FLAG_AMACTIVE;
            }
        }

    } else if (!config.backupActive) {
        piranha_log(flags,
            (char *)
            "We are backup node and backup is marked inactive -- exiting pulse");
        rc = -1;
    } else
        piranha_log(flags, (char *) "STARTING PULSE AS BACKUP");
        

    if (!rc)
        rc =  run(&config, fd, flags);
        

    if (flags & PULSE_FLAG_ASDAEMON)
        unlink("/var/run/pulse.pid");


if (rc == 2) rc = 0;
/* ### Turn off incomplete USR1 processing (Disables next block) ### */

    if (rc == 2) {
        piranha_log(flags, (char *) "Starting a new copy of pulse...");
        /* a 2 means we are to restart ourselves */

        close(fd);
        rc = 0;

        *fargp++ = (char *) "/usr/sbin/pulse";
        *fargp++ = (char *) "-c";
        *fargp++ = configFile;

        if (config.lvsServiceType == LVS_SERVICE_TYPE_FOS) {
            *fargp++ = (char *) "--fos";
            *fargp++ = fosBinary;
        } else {
            *fargp++ = (char *) "--lvs";
            *fargp++ = lvsBinary;
        }

        if (pulse_debug)
            *fargp++ = (char *) "-v";

        if (flags & PULSE_FLAG_NORUN)
            *fargp++ = (char *) "--norun";

        if (flags & PULSE_FLAG_ASDAEMON)
            *fargp++ = (char *) "--nodaemon";

        *fargp = NULL;

        logArgv(flags, fargv);

        if (!(child = fork())) {
            execv(fargv[0], fargv);
            exit(-1);
        }
    }

    return rc;
}
