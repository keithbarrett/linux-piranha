/* LVS - Linux Virtual Server
**
** Red Hat Clustering Tools 
** Copyright 1999 Red Hat, Inc.
**
** Author: Erik Troan <ewt@redhat.com> 
**
**
** This software may be freely redistributed under the terms of the GNU
** public license.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**
**
** MODIFICATION HISTORY
**
** 9/xx/1999    1.0 -   Erik Troan <ewt@redhat.com>
**              1.21    Initial release and repairs
**
** 11/4/1999    1.22    Mike Wangsmo <wanger@redhat.com>
**                      Added persistent code
**
** 12/19/1999   1.23    Phil "Bryce" Copeland <copeland@redhat.com>
**                      64 bit and typecast changes
**
** 1/8/2000     1.24    Phil "Bryce" Copeland <copeland@redhat.com>
**                      Added Wensong's patches
**                      changed log() to piranha_log()
**
** 2/16/2000    1.25    Keith Barrett <kbarrett@redhat.com>
**                      Added send/expect arguments to nanny
**
** 2/18/2000    1.26 -  Keith Barrett <kbarrett@redhat.com>
**              1.27    Added persistent netmask
**
** 2/25/2000    1.28    Keith Barrett <kbarrett@redhat.com>
**                      Correctly pass on routing method switches
**                      Increased size of argv[]
**                      Correctly pass on protocol value
**
** 2/25/2000    1.29    Phil "Bryce" Copeland <copeland@redhat.com>
**                      (const) on argv
**
** 2/29/2000    1.30 -  Keith Barrett <kbarrett@redhat.com>
**              1.36    Resolved diffs in cvs
**                      Added call to lvsRelocate FS()
**                      Added typecasts to make strict compiles happier
**                      Fixed passing of switches to ipvsadm
**
** 3/1/2000     1.27    Keith Barrett <kbarrett@redhat.com>
**                      Moved FLAGS to util.h
**
** 7/13/2000    1.28    Keith Barrett <kbarrett@redhat.com>
**                      Changed "i" to "err" in startServices, according
**                      to patch provided by Flavio Pescuma. Also
**                      fixed missing arg in piranha_log() call.
**
** 7/20/2000	1.29	Keith Barrett <kbarrett@redhat.com>
**		        1.30	Pass udp switch to nanny. Added --version.
**			            Added Bryce's CFGFILE logic.
**
** 8/1/2000	    1.31	Keith Barrett <kbarrett@redhat.com>
**			            Added -v.
**
** 8/16/2000	1.32	Keith Moore <keith.moore@renp.com>
**			            Fix memory usage of persistence timeout parameter
**
** 9/11/2000	1.33	Philip Copeland <copeland@redhat.com>
**			            made least connections change from pcc -> lc
**
** 10/17/2000	1.34	Philip Copeland <bryce@redhat.com>
**			            Added some extra info to the --version option
*/

/*
**  NOTE:   This program will break fail if it encounters a failover service
**          structure in the virtual servers list because it does not check.
**          This condition should not be possible under normal use because
**          relocateFailoverServices() is called.
*/


#define _PROGRAM_VERSION "lvs 1.33"



#include <errno.h>
#include <fcntl.h>
#include <netinet/in.h>
#include <popt.h>
#include <stdlib.h>
#include <string.h>
#include <sys/signal.h>
#include <sys/wait.h>
#include <unistd.h>

#include "lvsconfig.h"
#include "util.h"

#define _(a) (a)
#define N_(a) (a)

/* Globals icky :-( */
static int termSignal = 0;
static int rereadFiles = 0;
static int debug_mode = 0;

struct clientInfo {
    struct in_addr address;
    int port, protocol;
    pid_t clientMonitor;        /* -1 if died */
};




static void handleChildDeath(int signal) {
    /* We don't need to do anything here as we always check for dead
       children when we get a signal. */
}




static void handleTermSignal(int signal) {
    termSignal = signal;
}




static void handleHupSignal(int signal) {
    rereadFiles = 1;
}




int execArgv(int flags, char ** argv) {
    pid_t child;
    int status;

    logArgv(flags, argv);

    if (!(child = fork())) {
        if (flags & LVS_FLAG_TESTSTARTS)
            exit(0);
        execv(argv[0], argv);
        exit(1);
    }

    waitpid(child, &status, 0);

    if (!WIFEXITED(status) || WEXITSTATUS(status)) {
        return 1;
    }

    return 0;
}




/* returns -1 on no match */

int findClientInList(struct clientInfo * clients, int numClients,
                 struct clientInfo * needle) {

    int i;

    for (i = 0; i < numClients; i++) {
        if (memcmp(&clients[i].address, &needle->address,
           sizeof(needle->address))) continue;
        if (clients[i].port != needle->port) continue;
        if (clients[i].protocol != needle->protocol) continue;

        return i;
    }

    return -1;
}




int findClientConfig(struct lvsConfig * config, int * vserver, int * service,
             struct clientInfo * needle) {

    int vsn, sn;

    for (vsn = 0; vsn < config->numVirtServers; vsn++) {

        for (sn = 0; sn < config->virtServers[vsn].numServers; sn++) {

            if (memcmp(&config->virtServers[vsn].servers[sn].address, 
                    &needle->address,
                    sizeof(needle->address)))
                continue;

            if (config->virtServers[vsn].servers[sn].port != needle->port)
                continue;

            if (config->virtServers[vsn].protocol != needle->protocol)
                continue;

            *vserver = vsn;
            *service = sn;
            return 0;
        }
    }

    return -1;
}



char *strip_quotes(char *in_string) {
    char *tmp_ptr;
    char *out_string = NULL;

    if (in_string) {
        out_string = in_string;

        if (*in_string == '\"') {
            ++out_string;
        }

        if (strlen(out_string)) {
            tmp_ptr = strlen(out_string) + out_string - 1;
            if (*tmp_ptr == '\"')
                *tmp_ptr = 0;
        }
    }

    return out_string;
}





/* This assumes there is enough room for all of the servers we need to
   start! It doesn't start up clients which are already running */

int startClientMonitor( struct lvsConfig * config, 
                        struct lvsVirtualServer * vserver,
                        struct lvsService * service, 
                        struct clientInfo * clients,
                        int * numClients, 
                        int flags) {

    struct clientInfo clientInfo;
    int rc;
    char * realAddress;
    char * virtAddress;
    char * argv[42];
    char ** arg = argv;
    char portNum[20], timeoutNum[20], weightNum[20], reentryNum[20];
    pid_t child;

    if (!service->isActive)
        return 0;

    clientInfo.address  = service->address;
    clientInfo.protocol = vserver->protocol;
    clientInfo.port     = service->port;

    rc = findClientInList(clients, *numClients, &clientInfo);

    if (rc != -1)
        return 0;

    virtAddress = strdup(inet_ntoa(vserver->virtualAddress));
    realAddress = strdup(inet_ntoa(service->address));

    sprintf(portNum, "%d", service->port);
    sprintf(timeoutNum, "%d", vserver->timeout);
    sprintf(weightNum, "%d", service->weight);
    sprintf(reentryNum, "%d", vserver->reentryTime);

    *arg++ = vserver->clientMonitor;
    *arg++ = (char *) "-c";
    *arg++ = (char *) "-h";
    *arg++ = realAddress;
    *arg++ = (char *) "-p";
    *arg++ = portNum;

    if (vserver->protocol == IPPROTO_UDP)
        *arg++ = (char *) "-u";
    /* UDP */

    if (vserver->send_str) {
        *arg++ = (char *) "-s";
        *arg++ = strip_quotes(vserver->send_str);
    }

    if (vserver->expect_str) {
        *arg++ = (char *) "-x";
        *arg++ = strip_quotes(vserver->expect_str);
    }

    if (debug_mode)
        *arg++ = (char *) "-v";                 /* Pass along debug mode */

    *arg++ = (char *) "-a";
    *arg++ = reentryNum;
    *arg++ = (char *) "-I";
    *arg++ = config->vsadm;
    *arg++ = (char *) "-t";
    *arg++ = timeoutNum;
    *arg++ = (char *) "-w";
    *arg++ = weightNum;
    *arg++ = (char *) "-V";
    *arg++ = virtAddress;
    *arg++ = (char *) "-M";

    switch (config->redirectType) {

        case LVS_REDIRECT_NAT:
            *arg++ = (char *) "m";
            break;

        case LVS_REDIRECT_DIRECT:
            *arg++ = (char *) "g";
            break;

        case LVS_REDIRECT_TUNNEL:
            *arg++ = (char *) "i";
            break;

        case LVS_REDIRECT_UNKNOWN:
            abort();
    }


    *arg++ = (char *) "-U";

    if (vserver->loadMonitor && strcmp(vserver->loadMonitor, "uptime"))
        *arg++ = vserver->loadMonitor;
    else
        *arg++ = config->rshCommand;

    if (!(flags & LVS_FLAG_ASDAEMON)) {
        *arg++ = (char *) "--nodaemon";         /* Pass along --nodaemon */
    }


    *arg++ = NULL;

    logArgv(flags, argv);

    if (!(flags & LVS_FLAG_TESTSTARTS)) {
        child = fork();

        if (!child) {
            execv(argv[0], argv);
            exit(1);
        }

        piranha_log(flags,
                (char *) "create_monitor for %s/%s running as pid %d",
            vserver->name, service->name, child);

    } else {
        child = -1;
    }

    free(virtAddress);
    free(realAddress);

    clientInfo.clientMonitor = child;
    clients[*numClients] = clientInfo;
    (*numClients)++;

    return 0;
}




int shutdownClientMonitor(  struct lvsConfig * config, 
                            struct lvsVirtualServer * vserver,
                            struct lvsService * service, 
                            struct clientInfo * clients,
                            int * numClientsPtr, 
                            int flags) {

    int which;
    struct clientInfo clientInfo;

    clientInfo.address  = service->address;
    clientInfo.protocol = vserver->protocol;
    clientInfo.port     = service->port;

    which = findClientInList(clients, *numClientsPtr, &clientInfo);

    if (which == -1)
        return 0;


    if (clients->clientMonitor != -1) {
        kill(clients[which].clientMonitor, SIGTERM);
        waitpid(clients[which].clientMonitor, NULL, 0);
    }

    if (which != (*numClientsPtr - 1)) 
        clients[which] = clients[*numClientsPtr - 1];

    (*numClientsPtr)--;
    return 0;
}




int shutdownVirtualServer(  struct lvsConfig * config, 
                            struct lvsVirtualServer * vserver,
                            int flags,
                            struct clientInfo * clients,
                            int * numClientsPtr) {

    char * argv[42];
    char ** arg = argv;
    char virtAddress[50];
    int i;

    if (!vserver->isActive)
        return 0;
    
    piranha_log(flags,
        (char *) "shutting down virtual service %s", vserver->name);

    sprintf(virtAddress, "%s:%d", inet_ntoa(vserver->virtualAddress),
        vserver->port);

    *arg++ = config->vsadm;
    *arg++ = (char *) "-D";

    switch (vserver->protocol) {
        case IPPROTO_UDP:
            *arg++ = (char *) "-u";
            break;

        case IPPROTO_TCP:
        default:
            *arg++ = (char *) "-t";
            break;
    }

    *arg++ = virtAddress;
    *arg++ = NULL;

    for (i = 0; i < vserver->numServers; i++)
        shutdownClientMonitor(config, vserver, vserver->servers + i,
            clients, numClientsPtr, flags);

    if (execArgv(flags, argv)) {
        piranha_log(flags, (char *) "ipvsadm failed for virtual server %s!",
            vserver->name);
        return 1;
    }

    return 0;
}





/* This assumes there is enough room for all of the servers we need to
   start! */

int startVirtualServer( struct lvsConfig * config, 
                        struct lvsVirtualServer * vserver,
                        int flags,
                        struct clientInfo * clients,
                        int * numClientsPtr) {
    int i;
    char * argv[40];
    char wrkBuf[10];
    char ** arg = argv;
    char virtAddress[50];
    int oldNumClients;
    char *pmask = NULL;

    if (!vserver->isActive)
        return 0;

    piranha_log(flags, (char *) "starting virtual service %s active: %d",
        vserver->name, vserver->port);

    sprintf(virtAddress, "%s:%d", inet_ntoa(vserver->virtualAddress),
        vserver->port);

    *arg++ = config->vsadm;
    *arg++ = (char *) "-A";

    switch (vserver->protocol) {
        case IPPROTO_UDP:
            *arg++ = (char *) "-u";
            break;

        case IPPROTO_TCP:
        default:
            *arg++ = (char *) "-t";
            break;
    }

    *arg++ = virtAddress;

    *arg++ = (char *) "-s";

    switch (vserver->scheduler) {
        case LVS_SCHED_LC:
            *arg++ = (char *) "lc";
            break;

        case LVS_SCHED_WLC:
            *arg++ = (char *) "wlc";
            break;

        case LVS_SCHED_RR:
            *arg++ = (char *) "rr";
            break;

        case LVS_SCHED_WRR:
            *arg++ = (char *) "wrr";
            break;

        default:
            abort();
    }


    if (vserver->persistent > 0 ) {       
        *arg++ = (char *) "-p";
        (void) sprintf(wrkBuf, "%d", vserver->persistent);
	    *arg++ = wrkBuf;

        if (vserver->pmask.s_addr) {
            pmask = inet_ntoa(vserver->pmask);

            if (pmask) {
                *arg++ = (char *) "-M";
                *arg++ = pmask;
            }
        }
    }

    *arg++ = NULL;

    if (execArgv(flags, argv)) {
        piranha_log(flags, (char *) "ipvsadm failed for virtual server %s!",
            vserver->name);
        return 1;
    }

    oldNumClients = *numClientsPtr;

    for (i = 0; i < vserver->numServers; i++) {
        startClientMonitor(config, vserver, vserver->servers + i,
            clients, numClientsPtr, flags);
    }

    return 0;
}






int restartVirtualServer(   struct lvsConfig * config, 
                            struct lvsVirtualServer * oldVserver,
                            struct lvsVirtualServer * vserver,
                            int flags,
                            struct clientInfo * clients,
                            int * numClientsPtr) {

    int old, new;
    enum { WHAT_UNKNOWN, WHAT_START, WHAT_STOP, WHAT_SKIP } what;


    /* look for old servers which no longer exist */

    for (old = 0; old < oldVserver->numServers; old++) {

        for (new = 0; new < vserver->numServers; new++) {

            if (memcmp(&oldVserver->servers[old].address, 
               &vserver->servers[new].address,
               sizeof(vserver->servers[new].address))) continue;

            if (oldVserver->servers[old].port != 
               vserver->servers[new].port) continue;

            break;
        }

        if (new == vserver->numServers) {   /* This service is gone. */
            shutdownVirtualServer(config, oldVserver, flags, clients, 
                  numClientsPtr);
        }
    }


    /* Start/stop client monitors as appropriate */

    for (new = 0; new < vserver->numServers; new++) {

        for (old = 0; old < oldVserver->numServers; old++) {

            if (memcmp(&oldVserver->servers[old].address, 
                    &vserver->servers[new].address,
                    sizeof(vserver->servers[new].address)))
                continue;

            if (oldVserver->servers[old].port != 
                    vserver->servers[new].port)
                continue;

            break;
        }

        what = WHAT_UNKNOWN;

        if (old == oldVserver->numServers) {    /* new service */
            what = WHAT_START;

        } else if (oldVserver->servers[old].isActive &&
                vserver->servers[new].isActive) {
            /* old active, new active */
            what = WHAT_SKIP;

        } else if (!oldVserver->servers[old].isActive &&
                !vserver->servers[new].isActive) {
            /* old not active, new not active */
            what = WHAT_STOP;

        } else if (oldVserver->servers[old].isActive &&
                !vserver->servers[new].isActive) {
            /* old active, new not active */
            what = WHAT_STOP;

        } else if (!oldVserver->servers[old].isActive &&
                vserver->servers[new].isActive) {
            /* old not active, new active */
            what = WHAT_START;
        }

        if (what == WHAT_START)
            startClientMonitor(config, vserver, vserver->servers + new,
                   clients, numClientsPtr, flags);
    
        else if (what == WHAT_STOP)
            shutdownClientMonitor(config, vserver, vserver->servers + new,
                      clients, numClientsPtr, flags);
    }

    return 0;
}





int rereadConfigFiles(  struct lvsConfig * oldConfig, 
                        struct clientInfo ** clientsPtr,
                        int * numClientsPtr,
                        int * numClientsAllocedPtr,
                        const char * configFile,
                        int flags) {

    struct lvsConfig newConfig;
    int fd;
    int i, j;
    int size;
    int rc;
    int line;

    enum { WHAT_UNKNOWN, WHAT_START, WHAT_STOP, WHAT_RESTART,
       WHAT_SKIP } what;


    if ((fd = open(configFile, O_RDONLY)) < 0) {
        piranha_log(flags, (char *) "lvs: failed to open %s", configFile);
        return 1;
    }

    if ((rc = lvsParseConfig(fd, &newConfig, &line))) {
        piranha_log(flags, (char *) "lvs: error parsing %s on line %d.\n",
            configFile, line);
        return 1;
    }


    lvsRelocateFS(&newConfig);
    /* Move failover services to own list and clean up virtual server list */


    for (i = 0; i < oldConfig->numVirtServers; i++) {

        for (j = 0; j < newConfig.numVirtServers; j++) {

            if (!memcmp(&oldConfig->virtServers[i].virtualAddress,
                    &newConfig.virtServers[j].virtualAddress,
                    sizeof(newConfig.virtServers[j].virtualAddress)) &&
                    (oldConfig->virtServers[i].protocol ==
                    newConfig.virtServers[j].protocol) &&
                    (oldConfig->virtServers[i].port ==
                    newConfig.virtServers[j].port))
                break;
        }

        if (j == newConfig.numVirtServers) {
            /* This server no longer exists -- kill it */
            shutdownVirtualServer(oldConfig, oldConfig->virtServers + i,
                flags, *clientsPtr, numClientsPtr);
        }
    }


    /* Now restart all of the virtual servers which still exist (or are new).
       Note that servers whose active states have changed need to be handled
       here as well. */

    for (i = 0; i < newConfig.numVirtServers; i++) {

        for (j = 0; j < oldConfig->numVirtServers; j++) {

            if (!memcmp(&oldConfig->virtServers[j].virtualAddress,
                    &newConfig.virtServers[i].virtualAddress,
                    sizeof(newConfig.virtServers[i].virtualAddress)) &&
                    (oldConfig->virtServers[j].protocol ==
                    newConfig.virtServers[i].protocol) &&
                    (oldConfig->virtServers[j].port ==
                    newConfig.virtServers[i].port))
                break;
        }

        what = WHAT_UNKNOWN;

        if (j == newConfig.numVirtServers) 
            /* New server! That's easy. */
            what = WHAT_START;

        else if (newConfig.virtServers[i].isActive && 
                oldConfig->virtServers[j].isActive)
            /* New active, old active */
            what = WHAT_RESTART;

        else if (!newConfig.virtServers[i].isActive && 
                !oldConfig->virtServers[j].isActive)
            /* New not active, old not active */
            what = WHAT_SKIP;
 
        else if (newConfig.virtServers[i].isActive && 
                !oldConfig->virtServers[j].isActive)
            /* New active, old not active */
            what = WHAT_START;

        else if (!newConfig.virtServers[i].isActive && 
                oldConfig->virtServers[j].isActive)
            /* New not active, old active */
            what = WHAT_STOP;


        if (what == WHAT_STOP) {
            shutdownVirtualServer(oldConfig, oldConfig->virtServers + j, 
                  flags, *clientsPtr, numClientsPtr);
        } else {
            size = *numClientsPtr + newConfig.virtServers[i].numServers;
            *clientsPtr = realloc(*clientsPtr, sizeof(**clientsPtr) * size);

            if (what == WHAT_START)
                startVirtualServer(&newConfig, newConfig.virtServers + i, 
                   flags, *clientsPtr, numClientsPtr);
            else if (what == WHAT_RESTART)
                restartVirtualServer(&newConfig, oldConfig->virtServers + j, 
                    newConfig.virtServers + j, flags, 
                    *clientsPtr, numClientsPtr);
        }
    }

    *oldConfig = newConfig;
    return 0;
}





int startServices(  struct lvsConfig * config,
                    int flags,
                    char * configFile) {

    int i, err;
    struct clientInfo * clients = NULL;
    int numClients = 0, numClientsAlloced = 0;
    sigset_t sigs;
    pid_t pid;
    int status;
    int doShutdown = 0;
    int vsn, sn;

    if (flags & LVS_FLAG_ASDAEMON) {
        flags |= LVS_FLAG_SYSLOG;

        if (daemonize(flags))
            return 0;
    }

    sigemptyset(&sigs);
    sigaddset(&sigs, SIGINT);
    sigaddset(&sigs, SIGCHLD);
    sigaddset(&sigs, SIGTERM);
    sigaddset(&sigs, SIGHUP);
    sigprocmask(SIG_BLOCK, &sigs, NULL);

    signal(SIGCHLD, handleChildDeath);
    signal(SIGINT,  handleTermSignal);
    signal(SIGTERM, handleTermSignal);
    signal(SIGHUP,  handleHupSignal);

    for (i = 0; i < config->numVirtServers; i++) {
        if ((numClients + config->virtServers[i].numServers) >=
                numClientsAlloced) {
            numClientsAlloced = numClients + 
            config->virtServers[i].numServers;
            clients = realloc(clients, sizeof(*clients) * numClientsAlloced);
        }

        startVirtualServer(config, config->virtServers + i, flags, clients, 
            &numClients);
    }

    sigfillset(&sigs);
    sigdelset(&sigs, SIGINT);
    sigdelset(&sigs, SIGCHLD);
    sigdelset(&sigs, SIGTERM);
    sigdelset(&sigs, SIGHUP);


    while (!doShutdown) {
        sigsuspend(&sigs);

        while ((pid = waitpid(-1, &status, WNOHANG)) > 0) {
            for (i = 0; i < numClients; i++) {
                if (clients[i].clientMonitor == pid)
                    break;
            }

            if (i < numClients) {
                err = findClientConfig(config, &vsn, &sn, clients + i);

                piranha_log(flags,
                    (char *) "nanny for child %s/%s died! shutting down lvs",
                    config->virtServers[vsn].name,
                    config->virtServers[vsn].servers[sn].name);

                doShutdown = 1;
                clients[i].clientMonitor = -1;
            }
        }

        if (termSignal) {
            piranha_log(flags,
                (char *) "shutting down due to signal %d", termSignal);
            doShutdown = 1;
        }

        if (rereadFiles) {
            piranha_log(flags, (char *) "rereading configuration file");
            rereadConfigFiles(config, &clients, &numClients,
                  &numClientsAlloced, configFile, flags);
            rereadFiles = 0;
        }
    }


    for (i = 0; i < config->numVirtServers; i++) {
        shutdownVirtualServer(config, config->virtServers + i, flags,
            clients, &numClients);
    }

    if (flags & LVS_FLAG_ASDAEMON)
        unlink("/var/run/lvs.pid");

    return 0;
}





int clearVcsTable(struct lvsConfig * config, int flags) {
    char * argv[40];
    char ** arg = argv;

    *arg++ = config->vsadm;
    *arg++ = (char *) "-C";
    *arg++ = NULL;

    execArgv(flags, argv);
    return 0;
}





int main(int argc, const char ** argv) {

    char * configFile = (char *) CFGFILE;  /* Supplied by Makefile */
    int testStart = 0, noDaemon = 0;
    poptContext optCon;
    int rc, flags = 0, fd, line;
    int noFork = 0;
    struct lvsConfig config;
    int display_ver = 0;

    struct poptOption options[] = {
        { "configfile", 'c', POPT_ARG_STRING, &configFile, 0, 
        N_("Configuration file"), N_("configfile") },

        { "verbose", 'v', 0, &debug_mode, 0,
        N_("Verbose (debug) output") },

        { "nodaemon", 'n', 0, &noDaemon, 0,
        N_("Don't run as a daemon") },

        { "test-start", 't', 0, &testStart, 0, 
        N_("Display what commands would be run on startup, but "
           "don't actually run anything") },

        { "nofork", '\0', 0, &noFork, 0,
        N_("Don't fork, but do disassociate") },

        { "version", '\0', 0, &display_ver, 0,
        N_("Display program version") },

        POPT_AUTOHELP
        { 0, 0, 0, 0, 0 }
    };

    optCon = poptGetContext("lvs", argc, argv, options,0);
    poptReadDefaultConfig(optCon, 1);

    if ((rc = poptGetNextOpt(optCon)) < -1) {
        fprintf(stderr, _("lvs: bad argument %s: %s\n"), 
            poptBadOption(optCon, POPT_BADOPTION_NOALIAS), 
            poptStrerror(rc));
        return 1;
    }

    if (poptGetArg(optCon)) {
        fprintf(stderr, _("lvs: unexpected arguments\n"));
        return 1;
    }

    poptFreeContext(optCon);
    logName((char *) "lvs");
    flags = 0;

    if (display_ver) {
        printf("Program Version:\t%s\n", _PROGRAM_VERSION);
	printf("Built:\t\t\t%s\n", DATE); /* DATE pulled from Makefile */
	printf("A component of:\t\tpiranha-%s-%s\n", VERSION, RELEASE);
        return 0;
    }

    if (testStart)
        flags |= LVS_FLAG_TESTSTARTS;

    if (!noDaemon)
        flags |= LVS_FLAG_ASDAEMON | LVS_FLAG_SYSLOG;
    else
        flags |= LVS_FLAG_PRINTF;

    if (noFork) {
        flags |= LVS_FLAG_NOFORK;
    }

    if ((fd = open(configFile, O_RDONLY)) < 0) {
        fprintf(stderr, "lvs: failed to open %s\n", configFile);
        return 1;
    }

    if ((rc = lvsParseConfig(fd, &config, &line))) {
        fprintf(stderr, "lvs: error parsing %s on line %d.\n", configFile, line);
        return 1;
    }

    lvsRelocateFS(&config);
    /* Move failover services to own list and clean up virtual server list */

    clearVcsTable(&config, flags);
    return startServices(&config, flags, configFile);
}
