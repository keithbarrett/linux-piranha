/*
 *      ipvsadm - IP Virtual Server ADMinistration program
 *                for IPVS NetFilter Module in kernel 2.4
 *
 *      Version: $Id: ipvsadm.c,v 1.1.4.1 2001/05/29 20:16:07 kbarrett Exp $
 *
 *      Authors: Wensong Zhang <wensong@linuxvirtualserver.org>
 *               Peter Kese <peter.kese@ijs.si>
 *
 *      This program is based on ippfvsadm.
 *
 *      Changes:
 *        Wensong Zhang       :   added the editting service & destination support
 *        Wensong Zhang       :   added the feature to specify persistent port
 *        Jacob Rief          :   found the bug that masquerading dest of
 *                                different vport and dport cannot be deleted.
 *        Wensong Zhang       :   fixed it and changed some cosmetic things
 *        Wensong Zhang       :   added the timeout setting for persistent service
 *        Wensong Zhang       :   added specifying the dest weight zero
 *        Wensong Zhang       :   fixed the -E and -e options
 *        Wensong Zhang       :   added the long options
 *        Wensong Zhang       :   added the hostname and portname input
 *        Wensong Zhang       :   added the hostname and portname output
 *	  Lars Marowsky-Br�e  :   added persistence granularity support
 *        Julian Anastasov    :   fixed the (null) print for unknown services
 *        Wensong Zhang       :   added the port_to_anyname function
 *        Horms               :   added option to read commands from stdin
 *        Horms               :   modified usage function so it prints to 
 *                            :   stdout if an exit value of 0 is used and 
 *                            :   stdout otherwise. Program is then terminated
 *                            :   with the supplied exit value.
 *        Horms               :   updated manpage and usage funtion so
 *                            :   the reflect the options available
 *        Wensong Zhang       :   added option to write rules to stdout
 *        Horms               :   added ability to specify a fwmark
 *                            :   instead of a server and port for
 *                            :   a virtual service
 *        Horms               :   tightened up checking of services
 *                            :   in parse_service
 *        Horms               :   ensure that a -r is passed when needed
 *        Wensong Zhang       :   fixed the output of fwmark rules
 *        Horms               :   added kernel version verification
 *        Horms               :   Specifying command and option options
 *                                (e.g. -Ln or -At) in one short option
 *                                with popt problem fixed.
 *        Wensong Zhang       :   split the process_options and make
 *                                two versions of parse_options.
 *        Horms               :   attempting to save or restore when
 *        		      :   compiled against getopt_long now results
 *        		          in an informative error message rather
 *        		          than the usage information
 *        Horms               :   added -v option
 *
 *
 *      ippfvsadm - Port Fowarding & Virtual Server ADMinistration program
 *
 *      Copyright (c) 1998 Wensong Zhang
 *      All rights reserved.
 *
 *      Author: Wensong Zhang <wensong@iinchina.net>
 *
 *      This ippfvsadm is derived from Steven Clarke's ipportfw program.
 *
 *      portfw - Port Forwarding Table Editing v1.1
 *
 *      Copyright (c) 1997 Steven Clarke
 *      All rights reserved.
 *
 *      Author: Steven Clarke <steven@monmouth.demon.co.uk>
 *
 *      This program is free software; you can redistribute it and/or modify
 *      it under the terms of the GNU General Public License as published by
 *      the Free Software Foundation; either version 2 of the License, or
 *      (at your option) any later version.
 *
 *      This program is distributed in the hope that it will be useful,
 *      but WITHOUT ANY WARRANTY; without even the implied warranty of
 *      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *      GNU General Public License for more details.
 *
 *      You should have received a copy of the GNU General Public License
 *      along with this program; if not, write to the Free Software
 *      Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 */

#undef __KERNEL__	/* Makefile lazyness ;) */
#include <stdio.h>
#include <stdlib.h>
#include <getopt.h>
#include <netdb.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <ctype.h>
#include <netinet/in.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <sys/param.h>
#include <sys/wait.h>           /* For waitpid */
#include <arpa/inet.h>

#include <asm/types.h>          /* For __uXX types */
#include <net/if.h>
#include <netinet/ip_icmp.h>
#include <netinet/udp.h>
#include <netinet/tcp.h>

#include <net/ip_vs.h>

#ifdef HAVE_POPT
#include "popt.h"
#include "config_stream.h"

#define IPVS_OPTION_PROCESSING          "popt"
#else
#define IPVS_OPTION_PROCESSING          "getopt_long"
#endif

#define IPVSADM_VERSION_NO              "v" VERSION
#define IPVSADM_VERSION_DATE            "2001/03/18"
#define IPVSADM_VERSION         IPVSADM_VERSION_NO " " IPVSADM_VERSION_DATE

#define MINIMUM_IPVS_VERSION_MAJOR      0
#define MINIMUM_IPVS_VERSION_MINOR      0
#define MINIMUM_IPVS_VERSION_PATCH      5

#ifndef IPVS_VERSION
#define IPVS_VERSION(x,y,z) (((x)<<16)+((y)<<8)+(z))
#endif

/* default scheduler */
#define DEF_SCHED	"wlc"

#define OPT_NONE	0x00000
#define OPT_NUMERIC	0x00001
#define OPT_CONNECTION	0x00002

/* printing format flags */
#define FMT_NONE	0x0000
#define FMT_NUMERIC	0x0001
#define FMT_RULE	0x0002

#define SERVICE_NONE    0x0
#define SERVICE_ADDR    0x1
#define SERVICE_PORT    0x2

#define IPVS_PROC_FILE    "/proc/net/ip_vs"
#define CONN_PROC_FILE  "/proc/net/ip_vs_conn"

int string_to_number(const char *s, int min, int max);
int host_to_addr(const char *name, struct in_addr *addr);
char * addr_to_host(struct in_addr *addr);
char * addr_to_anyname(struct in_addr *addr);
int service_to_port(const char *name, unsigned short proto);
char * port_to_service(int port, unsigned short proto);
char * port_to_anyname(int port, unsigned short proto);
char * addrport_to_anyname(struct in_addr *addr, int port,
                           unsigned short proto, unsigned int format);

int parse_service(char *buf, u_int16_t proto,
                  u_int32_t *addr, u_int16_t *port);
int parse_fwmark(char *buf, u_int32_t *fwmark);
int parse_netmask(char *buf, u_int32_t *addr);
int parse_timeout(char *buf, unsigned *timeout);

void usage_exit(const char *program, const int exit_status);
void version_exit(int exit_status);
void version(FILE *stream);
void fail(int err, char *text);
void check_ipvs_version(void);
void list_vs(unsigned int format);
void list_conn(unsigned int format);
void print_vsinfo(char *buf, unsigned int format);
int process_options(int argc, char **argv, int reading_stdin);
int str_is_digit(const char *str);


int main(int argc, char **argv)
{
	/*
	 *      Warn the user if the IPVS version is out of date
	 */
	check_ipvs_version();

        /*
         *      If no other arguement, list IPVS_PROC_FILE
         */
        if (argc == 1){
                list_vs(FMT_NONE);
		exit(0);
	}
        
        /*
         *	Process command line arguments
         */
	return process_options(argc, argv, 0);
}


#ifdef HAVE_POPT

int parse_options(int argc, char **argv, int reading_stdin,
                  struct ip_vs_rule_user *ur, int *op,
                  unsigned int *options, unsigned int *format)
{
        int result=0;
	int forward_set=0;
	int destination_set=0;
        int c, cmd, parse;
        int read_stdin=0;
        int write_stdout=0;
	dynamic_array_t *a;
	poptContext context;
	char *optarg=NULL;
	
        struct poptOption add_service_option =
        {"add-service", 'A', POPT_ARG_NONE, NULL, 'A'};
        struct poptOption edit_service_option =
        {"edit-service", 'E', POPT_ARG_NONE, NULL, 'E'};
        struct poptOption delete_service_option =
        {"delete-service", 'D', POPT_ARG_NONE, NULL, 'D'};
        struct poptOption clear_option =
        {"clear", 'C', POPT_ARG_NONE, NULL, 'C'};
        struct poptOption list_option =
        {"list", 'L', POPT_ARG_NONE, NULL, 'L'};
        struct poptOption list2_option =
        {"list", 'l', POPT_ARG_NONE, NULL, 'l'};
        struct poptOption add_server_option =
        {"add-server", 'a', POPT_ARG_NONE, NULL, 'a'};
        struct poptOption edit_server_option =
        {"edit-server", 'e', POPT_ARG_NONE, NULL, 'e'};
        struct poptOption delete_server_option =
        {"delete-server", 'd', POPT_ARG_NONE, NULL, 'd'};
        struct poptOption set_option =
        {"set", 's', POPT_ARG_NONE, NULL, 's'};
        struct poptOption help_option =
        {"help", 'h', POPT_ARG_NONE, NULL, 'h'};
        struct poptOption version_option =
        {"version", 'v', POPT_ARG_NONE, NULL, 'v'};
        struct poptOption read_stdin_option =
        {"restore", 'R', POPT_ARG_NONE, NULL, 'R'};
        struct poptOption write_stdout_option =
        {"save", 'S', POPT_ARG_NONE, NULL, 'S'};
        struct poptOption tcp_service_option =
        {"tcp-service", 't', POPT_ARG_STRING, &optarg, 't'};
        struct poptOption udp_service_option =
        {"udp-service", 'u', POPT_ARG_STRING, &optarg, 'u'};
        struct poptOption fwmark_service_option =
        {"fwmark-service", 'f', POPT_ARG_STRING, &optarg, 'f'};
        struct poptOption scheduler_option =
        {"scheduler", 's', POPT_ARG_STRING, &optarg, 's'};
        struct poptOption persistent_option =
        {"persistent", 'p', POPT_ARG_NONE, NULL, 'p'};
        struct poptOption netmask_option =
        {"netmask", 'M', POPT_ARG_STRING, &optarg, 'M'};
        struct poptOption real_server_option =
        {"real-server", 'r', POPT_ARG_STRING, &optarg, 'r'};
        struct poptOption real_server2_option =
        {"real-server", 'R', POPT_ARG_STRING, &optarg, 'R'};
        struct poptOption masquerading_option =
        {"masquerading", 'm', POPT_ARG_NONE, NULL, 'm'};
        struct poptOption ipip_option =
        {"ipip", 'i', POPT_ARG_NONE, NULL, 'i'};
        struct poptOption gatewaying_option =
        {"gatewaying", 'g', POPT_ARG_NONE, NULL, 'g'};
        struct poptOption weight_option =
        {"weight", 'w', POPT_ARG_STRING, &optarg, 'w'};
        struct poptOption numeric_option =
        {"numeric", 'n', POPT_ARG_NONE, NULL, 'n'};
        struct poptOption connection_option =
        {"connection", 'c', POPT_ARG_NONE, NULL, 'c'};
        struct poptOption NULL_option =
        {NULL, 0, 0, NULL, 0};

	struct poptOption *options_sub=NULL;

	struct poptOption options_main[] =
	{
		add_service_option,
		edit_service_option,
		delete_service_option,
		clear_option,
		list_option,
		list2_option,
		add_server_option,
		edit_server_option,
		delete_server_option,
                set_option,
		help_option,
		version_option,
		read_stdin_option,
		write_stdout_option,
		NULL_option
	};
		
	struct poptOption options_delete_service[] =
	{
		tcp_service_option,
		udp_service_option,
		fwmark_service_option,
		NULL_option
	};

	struct poptOption options_service[] =
	{
		tcp_service_option,
		udp_service_option,
		fwmark_service_option,
		scheduler_option,
		persistent_option,
		netmask_option,
		NULL_option
	};

	struct poptOption options_delete_server[] =
	{
		tcp_service_option,
		udp_service_option,
		fwmark_service_option,
		weight_option,
		real_server_option,
		real_server2_option,
		NULL_option
	};

	struct poptOption options_server[] =
	{
		tcp_service_option,
		udp_service_option,
		fwmark_service_option,
		weight_option,
		real_server_option,
		real_server2_option,
		gatewaying_option,
		masquerading_option,
		ipip_option,
		NULL_option
	};

	struct poptOption options_list[] =
	{
        	numeric_option,
                connection_option,
		NULL_option
	};

	struct poptOption options_NULL[] =
	{
		NULL_option
	};

	context = poptGetContext("ipvsadm", argc, (const char **)argv,
                                 options_main, 0);

        if ((cmd = poptGetNextOpt(context)) < 0)
                usage_exit(argv[0], -1);

        switch (cmd) {
        case 'A':	
                *op = IP_VS_SO_SET_ADD;
		options_sub = options_service;
                break;
        case 'E':	
                *op = IP_VS_SO_SET_EDIT;
		options_sub = options_service;
                break;
        case 'D':
                *op = IP_VS_SO_SET_DEL;
		options_sub = options_delete_service;
                break;
        case 'a':
                *op = IP_VS_SO_SET_ADDDEST;
		options_sub = options_server;
                break;
        case 'e':
                *op = IP_VS_SO_SET_EDITDEST;
		options_sub = options_server;
                break;
        case 'd':
                *op = IP_VS_SO_SET_DELDEST;
		options_sub=options_delete_server;
                break;
        case 'C':
                *op = IP_VS_SO_SET_FLUSH;
		options_sub=options_NULL;
                break;
        case 'L':
        case 'l':
                *op = IP_VS_SO_SET_LIST;
		options_sub=options_list;
                break;
        case 's':
                *op = IP_VS_SO_SET_TIMEOUTS;
		options_sub=options_NULL;
		break;
	case 'R':
		read_stdin=1;
		options_sub=options_NULL;
		break;
	case 'S':
		write_stdout=1;
		options_sub=options_list;
		break;
	case 'h':
                usage_exit(argv[0], 0);
		break;
	case 'v':
                version_exit(0);
		break;
        default:
                usage_exit(argv[0], -1);
        }

	poptFreeContext(context);
	context = poptGetContext("ipvsadm", argc, (const char **)argv,
                                 options_sub, 0);
	/* 
	 * Mangle the first argument
	 * The first option from this argument has been read,
	 * but there may be others
	 */
	c = strlen(argv[1]);
	if (c > 2) {
                if(argv[1][1] != '-'){
                        /* Suffle first option out of argument */
                        memmove(argv[1]+1, argv[1]+2, c-2);   
                        argv[1][c-1]='\0';
                }
        } else {
                /* Skip argument */
                poptGetNextOpt(context);
        }

        while ((c=poptGetNextOpt(context)) >= 0){
                switch (c) {
                case 't':
                case 'u':
                        if (ur->vfwmark != 0)
                                fail(2, "fwmark already specified");
                        if (ur->protocol != 0)
                                fail(2, "protocol already specified");
                        ur->protocol =
                                (c=='t' ? IPPROTO_TCP : IPPROTO_UDP);
                        parse = parse_service(optarg,
				              ur->protocol,
				              &ur->vaddr, 
				              &ur->vport);
                        if (!(parse & SERVICE_ADDR )) 
				fail(2, "illegal virtual server "
                                     "address[:port] specified");
                        break;
                case 'f':
                        if (ur->vfwmark != 0)
                                fail(2, "fwmark already specified");
                        if (ur->protocol != 0)
                                fail(2, "protocol already specified");
                        /* 
                         * Set prtocol to a sane values, even
                         * though it is not used 
                         */
                        ur->protocol = IPPROTO_TCP;
                        /* 
                         * Get the fwmark 
                         */
                        parse = parse_fwmark(optarg, &ur->vfwmark);
                        if (parse == 0 || ur->vfwmark == 0 ) 
				fail(2, "illegal virtual server "
                                     "fwmark specified");
                        break;
                case 's':
                        if (strlen(ur->sched_name) != 0)
                                fail(2, "multiple scheduling modules specified");
                        strncpy(ur->sched_name, optarg, IP_VS_SCHEDNAME_MAXLEN);
                        break;
                case 'p':
                        ur->vs_flags = IP_VS_SVC_F_PERSISTENT;
                        break;
                case 'M':
                        parse = parse_netmask(optarg,
                                              &ur->netmask);
                        if (parse != 1)
                                fail(2, "illegal virtual server "
                                     "persistent mask specified");
                        break;
                case 'r':
                case 'R':
                        if (destination_set)
                                fail(2, "Destination already set");
                        destination_set = 1;
                        parse = parse_service(optarg,
                                              ur->protocol,
                                              &ur->daddr, 
                                              &ur->dport);
                        if (!(parse & SERVICE_ADDR)) 
				fail(2, "illegal real server "
                                     "address[:port] specified");
                        /* copy vport to dport if none specified */
                        if (parse == 1)
                                ur->dport = ur->vport;
                        break;
                case 'i':
			if(forward_set)
                        	fail(2, "multiple forward mechanims set");
			forward_set = 1;
                        ur->conn_flags = IP_VS_CONN_F_TUNNEL;
                        break;
                case 'g':
			if(forward_set)
                        	fail(2, "multiple forward mechanims set");
			forward_set = 1;
                        ur->conn_flags = IP_VS_CONN_F_DROUTE;
                        break;
                case 'm':
			if(forward_set)
                        	fail(2, "multiple forward mechanims set");
			forward_set = 1;
                        ur->conn_flags = 0;
                        break;
                case 'w':
                        if (ur->weight != -1)
                                fail(2, "multiple server weights specified");
                        if ((ur->weight=
                             string_to_number(optarg,0,65535)) == -1)
                                fail(2, "illegal weight specified");
                        break;
                case 'c':
                        *options |= OPT_CONNECTION;
                        break;
                case 'n':
                        *options |= OPT_NUMERIC;
                        *format |= FMT_NUMERIC;
                        break;
                default:
                        fail(2, "invalid option");
                }
        }

	if (c < -1) {
		/* an error occurred during option processing */
		fprintf(stderr, "%s: %s\n",
			poptBadOption(context, POPT_BADOPTION_NOALIAS),
			poptStrerror(c));
		poptFreeContext(context);
		return -1;
	}

	if (read_stdin) {
                /* avoid infinite loop */
		if (reading_stdin != 0)
                	usage_exit(argv[0], -1);

		while ((a = config_stream_read(stdin, argv[0])) != NULL) {
			int i;
			if ((i = (int)dynamic_array_get_count(a)) > 1)
				result = process_options
                                        (i,
                                         (char **)dynamic_array_get_vector(a),
                                         1);
			dynamic_array_destroy(a, DESTROY_STR);
		}
		poptFreeContext(context);
		exit(result);
	}

        if (write_stdout) {
                *format |= FMT_RULE;
                list_vs(*format);
		poptFreeContext(context);
                exit(0);
        }
        
	/* 
         * If popt is used then optional arguments (persistent timeout)
	 * has to be handled last. This has the interesting
	 * side effect that the first non-option argument will
	 * be used as the timeout, regardless of its position
	 * in the argument list
	 */
	if (ur->vs_flags == IP_VS_SVC_F_PERSISTENT){
		optarg = (char *)poptGetArg(context);
       		parse = parse_timeout(optarg, &ur->timeout);
       		if (parse == 0)
                        fail(2, "illegal timeout for persistent service");
	}

        if (*op == IP_VS_SO_SET_TIMEOUTS) {
                char *optarg1, *optarg2;
                        
                if ((optarg=(char *)poptGetArg(context))
                    && (optarg1=(char *)poptGetArg(context))
                    && (optarg2=(char *)poptGetArg(context))) {
                        parse = parse_timeout(optarg, &ur->tcp_timeout)
                                && parse_timeout(optarg1, &ur->tcp_fin_timeout)
                                && parse_timeout(optarg2, &ur->udp_timeout);
                        if (parse == 0)
                                fail(2, "illegal timeout values");
                } else
                        fail(2, "-s option requires 3 timeout values");
        }
        
        if (poptGetArg(context))
                fail(2, "unexpected arguments");
    
        poptFreeContext(context);
        return 0;
}
        
#else /* HAVE_POPT */

int parse_options(int argc, char **argv, int reading_stdin,
                  struct ip_vs_rule_user *ur, int *op,
                  unsigned int *options, unsigned int *format)
{
	int forward_set=0;
	int destination_set=0;
        int c, cmd, parse;
        char *optstr = "";

	struct option long_options[] =
	{
        	{"add-service", 0, 0, 'A'},
        	{"edit-service", 0, 0, 'E'},
        	{"delete-service", 0, 0, 'D'},
        	{"clear", 0, 0, 'C'},
        	{"list", 0, 0, 'L'},
        	{"add-server", 0, 0, 'a'},
        	{"edit-server", 0, 0, 'e'},
        	{"delete-server", 0, 0, 'd'},
        	{"help", 0, 0, 'h'},
        	{"version", 0, 0, 'v'},
        	{"save", 0, 0, 'S'},
        	{"restore", 0, 0, 'R'},
        	{"set", 1, 0, 's'},
        	{"tcp-service", 1, 0, 't'},
        	{"udp-service", 1, 0, 'u'},
        	{"fwmark-service", 1, 0, 'f'},
        	{"scheduler", 1, 0, 's'},
        	{"persistent", 2, 0, 'p'},
        	{"real-server", 1, 0, 'r'},
        	{"masquerading", 0, 0, 'm'},
        	{"netmask", 1, 0, 'M'},
        	{"ipip", 0, 0, 'i'},
        	{"gatewaying", 0, 0, 'g'},
        	{"weight", 1, 0, 'w'},
        	{"numeric", 0, 0, 'n'},
        	{"connection", 0, 0, 'c'},
        	{"help", 0, 0, 'h'},
        	{0, 0, 0, 0}
	};

	extern char *optarg;
	extern int optind;
	extern int opterr;
	extern int optopt;

	/* Re-process the arguments each time options is called */
	optind = 1;

	if ((cmd = getopt_long(argc, argv, "AEDCSRaedlLhvs:",
                               long_options, NULL)) == EOF)
		usage_exit(argv[0], -1);

        switch (cmd) {
        case 'A':	
                *op = IP_VS_SO_SET_ADD;
                optstr = "t:u:f:s:M:p::";
                break;
        case 'E':	
                *op = IP_VS_SO_SET_EDIT;
                optstr = "t:u:f:s:M:p::";
                break;
        case 'D':
                *op = IP_VS_SO_SET_DEL;
                optstr = "t:u:f:";
                break;
        case 'a':
                *op = IP_VS_SO_SET_ADDDEST;
                optstr = "t:u:f:w:r:R:gmi";
                break;
        case 'e':
                *op = IP_VS_SO_SET_EDITDEST;
                optstr = "t:u:f:w:r:R:gmi";
                break;
        case 'd':
                *op = IP_VS_SO_SET_DELDEST;
                optstr = "t:u:f:w:r:R:";
                break;
        case 'C':
                *op = IP_VS_SO_SET_FLUSH;
                optstr = "";
                break;
        case 'L':
        case 'l':
                *op = IP_VS_SO_SET_LIST;
                optstr = "cn";
                break;
        case 's':
                *op = IP_VS_SO_SET_TIMEOUTS;
                optstr = "";
                if (optind + 1 < argc
                    && argv[optind][0] != '!'
                    && argv[optind][0] != '-'
                    && argv[optind+1][0] != '!'
                    && argv[optind+1][0] != '-') {
                        parse = parse_timeout(optarg, &ur->tcp_timeout)
                                && parse_timeout(argv[optind++],
                                                 &ur->tcp_fin_timeout)
                                && parse_timeout(argv[optind++],
                                                 &ur->udp_timeout);
                        if (parse == 0)
                                fail(2, "illegal timeout values");
                } else
                        fail(2, "-s option requires 3 timeout values");
		break;
	case 'h':
                usage_exit(argv[0], 0);
	case 'v':
                version_exit(0);
		break;
        case 'S': 
		fprintf(stderr, 
			"ipvsadm: Invalid option: -S or --save\n"
			"  Saving of ipvsadm rules is only supported when\n"
			"  ipvsadm is compiled against libpopt.\n");
		exit(-1);
		break;
	case 'R':
		fprintf(stderr, 
			"ipvsadm: Invalid option: -R or --restore\n"
			"  Restoring ipvsadm rules is only supported when \n"
			"  ipvsadm is compiled against libpopt.\n");
		exit(-1);
		break;
        default:
                usage_exit(argv[0], -1);
        }

	while ((c=getopt_long(argc, argv, optstr,
                              long_options, NULL)) != EOF) {
                switch (c) {
                case 't':
                case 'u':
                        if (ur->vfwmark != 0)
                                fail(2, "fwmark already specified");
                        if (ur->protocol != 0)
                                fail(2, "protocol already specified");
                        ur->protocol =
                                (c=='t' ? IPPROTO_TCP : IPPROTO_UDP);
                        parse = parse_service(optarg,
				              ur->protocol,
				              &ur->vaddr, 
				              &ur->vport);
                        if (!(parse & SERVICE_ADDR )) 
				fail(2, "illegal virtual server "
                                     "address[:port] specified");
                        break;
                case 'f':
                        if (ur->vfwmark != 0)
                                fail(2, "fwmark already specified");
                        if (ur->protocol != 0)
                                fail(2, "protocol already specified");
                        /* 
                         * Set prtocol to a sane values, even
                         * though it is not used 
                         */
                        ur->protocol = IPPROTO_TCP;
                        /* 
                         * Get the fwmark 
                         */
                        parse = parse_fwmark(optarg, &ur->vfwmark);
                        if (parse == 0 || ur->vfwmark == 0 ) 
				fail(2, "illegal virtual server "
                                     "fwmark specified");
                        break;
                case 's':
                        if (strlen(ur->sched_name) != 0)
                                fail(2, "multiple scheduling modules specified");
                        strncpy(ur->sched_name, optarg, IP_VS_SCHEDNAME_MAXLEN);
                        break;
                case 'p':
                        ur->vs_flags = IP_VS_SVC_F_PERSISTENT;
                        if (!optarg && optind < argc && argv[optind][0] != '-'
                            && argv[optind][0] != '!')
                                optarg = argv[optind++];
                        parse = parse_timeout(optarg,
                                              &ur->timeout);
                        if (parse == 0)
                                fail(2, "illegal timeout "
                                     "for persistent service");
                        break;
                case 'M':
                        parse = parse_netmask(optarg,
                                              &ur->netmask);
                        if (parse != 1)
                                fail(2, "illegal virtual server "
                                     "persistent mask specified");
                        break;
                case 'r':
                case 'R':
                        if (destination_set)
                                fail(2, "Destination already set");
                        destination_set = 1;
                        parse = parse_service(optarg,
                                              ur->protocol,
                                              &ur->daddr, 
                                              &ur->dport);
                        if (!(parse&SERVICE_ADDR)) 
				fail(2, "illegal real server "
                                     "address[:port] specified");
                        /* copy vport to dport if none specified */
                        if (parse == 1)
                                ur->dport = ur->vport;
                        break;
                case 'i':
			if(forward_set)
                        	fail(2, "multiple forward mechanims set");
			forward_set = 1;
                        ur->conn_flags = IP_VS_CONN_F_TUNNEL;
                        break;
                case 'g':
			if(forward_set)
                        	fail(2, "multiple forward mechanims set");
			forward_set = 1;
                        ur->conn_flags = IP_VS_CONN_F_DROUTE;
                        break;
                case 'm':
			if(forward_set)
                        	fail(2, "multiple forward mechanims set");
			forward_set = 1;
                        ur->conn_flags = 0;
                        break;
                case 'w':
                        if (ur->weight != -1)
                                fail(2, "multiple server weights specified");
                        if ((ur->weight =
                             string_to_number(optarg,0,65535)) == -1)
                                fail(2, "illegal weight specified");
                        break;
                case 'c':
                        *options |= OPT_CONNECTION;
                        break;
                case 'n':
                        *options |= OPT_NUMERIC;
                        *format |= FMT_NUMERIC;
                        break;
                default:
                        fail(2, "invalid option");
                }
        }

        if (optind < argc)
                fail(2, "unexpected arguments");
        return 0;
}

#endif /* HAVE_POPT */


int process_options(int argc, char **argv, int reading_stdin)
{
        struct ip_vs_rule_user urule;
        int op=IP_VS_SO_SET_NONE;
        unsigned int options=OPT_NONE;
        unsigned int format=FMT_NONE;
        int sockfd, result;

        memset(&urule, 0, sizeof(struct ip_vs_rule_user));
        /* weight=0 is allowed, which means that server is quiesced */
        urule.weight = -1;
        /* Set direct routing as default forwarding method */
        urule.conn_flags = IP_VS_CONN_F_DROUTE;
        /* Set the default persistent granularity to /32 masking */
        urule.netmask	= ((u_int32_t) 0xffffffff);

        if (parse_options(argc, argv, reading_stdin,
                          &urule, &op, &options, &format))
                return -1;
        
        if (op == IP_VS_SO_SET_LIST) {
                if (options & OPT_CONNECTION)
                        list_conn(format);
                else
                        list_vs(format);
		return 0;
	}

        if (op == IP_VS_SO_SET_ADD || op == IP_VS_SO_SET_EDIT) {
                /*
                 * Make sure that port zero service is persistent
                 */
                if (!urule.vfwmark && !urule.vport &&
                    (urule.vs_flags != IP_VS_SVC_F_PERSISTENT))
                        fail(2, "Zero port specified "
                             "for non-persistent service");

                /*
                 * Set the default scheduling algorithm if not specified
                 */
                if (strlen(urule.sched_name) == 0)
                        strcpy(urule.sched_name,DEF_SCHED);
        }

        /* 
         * Make sure that a destination is specified as required
         * i.e. make sure that a -r accompanies a -[t|u|f]
         */
        if ((op == IP_VS_SO_SET_ADDDEST
             || op == IP_VS_SO_SET_EDITDEST
             || op == IP_VS_SO_SET_DELDEST)
            && !urule.daddr) {
                fail(2, "No destination specified");
        }

        if (op == IP_VS_SO_SET_ADDDEST || op == IP_VS_SO_SET_EDITDEST) {
                /*
                 * Set the default weight 1 if not specified
                 */
                if (urule.weight == -1)
                        urule.weight = 1;

                /*
                 * The destination port must be equal to the service port
                 * if the IP_VS_CONN_F_TUNNEL or IP_VS_CONN_F_DROUTE is set.
                 * Don't worry about this if fwmark is used.
                 */
                if (!urule.vfwmark &&
                    (urule.conn_flags == IP_VS_CONN_F_TUNNEL
                     || urule.conn_flags == IP_VS_CONN_F_DROUTE))
                        urule.dport = urule.vport;
        }

        sockfd = socket(AF_INET, SOCK_RAW, IPPROTO_RAW);
        if (sockfd==-1) {
                perror("socket creation failed");
                exit(1);
        }

        result = setsockopt(sockfd, IPPROTO_IP, op,
                            (char *)&urule, sizeof(urule));
        if (result) {
                perror("setsockopt failed");

                /*
                 *    Print most common error messages
                 */
                switch (op) {
                case IP_VS_SO_SET_ADD:	
                        if (errno == EEXIST)
                                printf("Service already exists\n");
                        else if (errno == ENOENT)
                                printf("Scheduler not found: ip_vs_%s.o\n",
                                       urule.sched_name);
                        break;
                case IP_VS_SO_SET_EDIT:
                        if (errno==ESRCH)
                                printf("No such service\n");
                        else if (errno == ENOENT)
                                printf("Scheduler not found: ip_vs_%s.o\n",
                                       urule.sched_name);
                        break;
                case IP_VS_SO_SET_DEL:
                        if (errno==ESRCH)
                                printf("No such service\n");
                        break;
                case IP_VS_SO_SET_ADDDEST:
                        if (errno == ESRCH)
                                printf("Service not defined\n");
                        else if (errno == EEXIST)
                                printf("Destination already exists\n");
                        break;
                case IP_VS_SO_SET_EDITDEST:
                case IP_VS_SO_SET_DELDEST:
                        if (errno==ESRCH)
                                printf("Service not defined\n");
                        else if (errno == ENOENT)
                                printf("No such destination\n");
                        break;
                }
        }

        close(sockfd);
        return result;	
}


int string_to_number(const char *s, int min, int max)
{
	int number;
	char *end;

	number = (int)strtol(s, &end, 10);
	if (*end == '\0' && end != s) {
		/* 
                 * We parsed a number, let's see if we want this.
                 * If max <= min then ignore ranges
                 */
		if (max <= min || ( min <= number && number <= max))
			return number;
		else
			return -1;
	} else
		return -1;
}


/*
 * Get netmask.
 * Return 0 if failed,
 * 	  1 if addr read
 */
int parse_netmask(char *buf, u_int32_t *addr)
{
        struct in_addr inaddr;

	if(buf == NULL)
		return 0;
        
        if (inet_aton(buf, &inaddr) != 0)
                *addr = inaddr.s_addr;
        else if (host_to_addr(buf, &inaddr) != -1)
                *addr = inaddr.s_addr;
        else
                return 0;
        
        return 1;
}

/*
 * Get IP fwmark from the argument. 
 * Result is 
 * 1 on success
 * 0 on error
 */
int parse_fwmark(char *buf, u_int32_t *fwmark)
{
        long tmpl;

        if ((tmpl=string_to_number(buf, 0, 0)) == -1)
                return 0;
        *fwmark = tmpl;

        return 1;
}


/*
 * Get IP address and port from the argument. 
 * Result is a logical or of
 * SERVICE_NONE:   no service elements set/error
 * SERVICE_ADDR:   addr set
 * SERVICE_PORT:   port set
 */
int parse_service(char *buf, u_int16_t proto, u_int32_t *addr, u_int16_t *port)
{
        char *portp;
        long portn;
        int result=SERVICE_NONE;
        struct in_addr inaddr;

        if(buf==NULL || str_is_digit(buf))
                return SERVICE_NONE;

        portp = strchr(buf, ':');
        if (portp != NULL) 
                *portp = '\0';

        if (inet_aton(buf, &inaddr) != 0)
                *addr = inaddr.s_addr;
        else if (host_to_addr(buf, &inaddr) != -1)
                *addr = inaddr.s_addr;
        else                
                return SERVICE_NONE;

        result |= SERVICE_ADDR;
        
        if (portp != NULL){
                result |= SERVICE_PORT;
        
                if ((portn=string_to_number(portp+1, 0, 65535)) != -1)
                        *port = htons(portn);
                else if ((portn=service_to_port(portp+1, proto)) != -1)
                        *port = htons(portn);
                else
                        return SERVICE_NONE;
        }

        return result;
}


/*
 * Get the timeout of persistent service.
 * Return 0 if failed(the timeout value is less or equal than zero).
 * 	  1 if succeed.
 */
int parse_timeout(char *buf, unsigned *timeout)
{
        int i;

        if (buf == NULL) {
                *timeout = IP_VS_TEMPLATE_TIMEOUT;
                return 1;
        }
        
        if ((i=string_to_number(buf, 1, 86400*31)) == -1)
                return 0;

        *timeout = i * HZ;
        return 1;
}


void usage_exit(const char *program, const int exit_status)
{
        FILE *stream;

        if (exit_status != 0)
                stream = stderr;
        else
                stream = stdout;

	version(stream);
        fprintf(stream,
                "Usage:\n"
                "  %s -A|E -t|u|f service-address [-s scheduler] [-p [timeout]] [-M netmask]\n"
                "  %s -D -t|u|f service-address\n"
                "  %s -C\n"
#ifdef HAVE_POPT
                "  %s -R\n"
                "  %s -S [-n]\n"
#endif
                "  %s -a|e -t|u|f service-address -r|R server-address [-g|i|m] [-w weight]\n"
                "  %s -d -t|u|f service-address -r|R server-address\n"
                "  %s -L|l [-c] [-n]\n"
                "  %s -s tcp tcpfin udp\n"
                "  %s -h\n\n",
#ifdef HAVE_POPT
                program, program,
#endif
                program, program, program,
                program, program, program, program, program);
        
        fprintf(stream,
                "Commands:\n"
                "Either long or short options are allowed.\n"
                "  --add-service     -A        add virtual service with options\n"
                "  --edit-service    -E        edit virtual service with options\n"
                "  --delete-service  -D        delete virtual service\n"
                "  --clear           -C        clear the whole table\n"
#ifdef HAVE_POPT
                "  --restore         -R        restore rules from stdin\n"
                "  --save            -S        save rules to stdout\n"
#endif
                "  --add-server      -a        add real server with options\n"
                "  --edit-server     -e        edit real server with options\n"
                "  --delete-server   -d        delete real server\n"
                "  --list            -L|-l     list the table\n"
                "  --set|-s tcp tcpfin udp     set connection timeout values\n"
                "  --help            -h        display this help message\n\n"
                );
        
        fprintf(stream, 
                "Options:\n"
                "  --tcp-service  -t service-address   service-address is host[:port]\n"
                "  --udp-service  -u service-address   service-address is host[:port]\n"
                "  --fwmark-service  -f fwmark         fwmark is an integer greater than zero\n"
                "  --scheduler    -s scheduler         one of " SCHEDULERS ",\n"
                "                                      the default scheduler is %s.\n"
                "  --persistent   -p [timeout]         persistent service\n"
                "  --netmask      -M netmask           persistent granularity mask\n"
                "  --real-server  -r|R server-address  server-address is host (and port)\n"
                "  --gatewaying   -g                   gatewaying (direct routing) (default)\n"
                "  --ipip         -i                   ipip encapsulation (tunneling)\n"
                "  --masquerading -m                   masquerading (NAT)\n"
                "  --weight       -w weight            capacity of real server\n"
                "  --connection   -c                   output of current IPVS connections\n"
                "  --numeric      -n                   numeric output of addresses and ports\n",
                DEF_SCHED);
        
        exit(exit_status);
}


void version_exit(const int exit_status)
{
        FILE *stream;

        if (exit_status != 0)
                stream = stderr;
        else
                stream = stdout;

        version(stream);
        
        exit(exit_status);
}


void version(FILE *stream)
{
        fprintf(stream,
                "ipvsadm " IPVSADM_VERSION " (compiled with "
                IPVS_OPTION_PROCESSING " and IPVS v%d.%d.%d)\n",
		NVERSION(IP_VS_VERSION_CODE));
}


void fail(int err, char *text)
{
        printf("%s\n",text);
        exit(err);
}


int modprobe_ipvs(void)
{
    char *argv[] = { "/sbin/modprobe", "-s", "-k", "--", "ip_vs", NULL };
    int child;
    int status;
    int rc;

    if (!(child = fork())) {
	    execv(argv[0], argv);
	    exit(1);
    }

    rc = waitpid(child, &status, 0);

    if (!WIFEXITED(status) || WEXITSTATUS(status)) {
	    return 1;
    }

    return 0;
}


void check_ipvs_version(void)
{
        static char buffer[128];
	int major;
	int minor;
	int patch;
        FILE *handle;

        handle = fopen(IPVS_PROC_FILE, "r");
        if (!handle) {
                /* try to request the ip_vs module */
                if (modprobe_ipvs()
                    || !(handle = fopen(IPVS_PROC_FILE, "r"))) {
                        fprintf(stderr, "Could not open the %s file\n"
                                "Are you sure that IP Virtual Server is "
                                "built in the kernel or as module?\n",
                                IPVS_PROC_FILE);
                        exit(1);
                }
        }

        /*
         * Read the first line and verify the IPVS version
         */
        if (!feof(handle) && fgets(buffer, sizeof(buffer), handle)) {
	    	sscanf(buffer, "%*128[a-z A-Z] %d.%d.%d", 
                       &major, &minor, &patch);
                if (IPVS_VERSION(major,minor,patch) <
                    IPVS_VERSION(MINIMUM_IPVS_VERSION_MAJOR,
                                 MINIMUM_IPVS_VERSION_MINOR,
                                 MINIMUM_IPVS_VERSION_PATCH)) {
                        fprintf(stderr, 
                                "Warning: IPVS version missmatch: \n"
                                "  Kernel compiled with IPVS version %d.%d.%d\n"
                                "  ipvsadm " IPVSADM_VERSION_NO
                                " requires minimum IPVS version %d.%d.%d\n\n", 
                                major, minor, patch, 
                                MINIMUM_IPVS_VERSION_MAJOR,
                                MINIMUM_IPVS_VERSION_MINOR,
                                MINIMUM_IPVS_VERSION_PATCH);
		}
		buffer[strlen(buffer)-1] = '\0';
	}
        fclose(handle);
}


void print_conn(char *buf, unsigned int format)
{
        char            protocol[8];
        unsigned short  proto;
        struct in_addr  caddr;
        unsigned short  cport;
        struct in_addr  vaddr;
        unsigned short  vport;
        struct in_addr  daddr;
        unsigned short  dport;
        char            state[16];
        unsigned int    expires;

        int n;
        unsigned long temp1, temp2, temp3;
        char *cname, *vname, *dname;
	unsigned long minutes, seconds, sec100s;
	
        if ((n = sscanf(buf, " %s %lX %hX %lX %hX %lX %hX %s %d",
                        protocol, &temp1, &cport, &temp2, &vport,
                        &temp3, &dport, state, &expires)) == -1)
                exit(1);

        if (strcmp(protocol, "TCP") == 0)
                proto = IPPROTO_TCP;
        else if (strcmp(protocol, "UDP") == 0)
                proto = IPPROTO_UDP;
        else
                proto = 0;

        caddr.s_addr = (__u32) htonl(temp1);
        vaddr.s_addr = (__u32) htonl(temp2);
        daddr.s_addr = (__u32) htonl(temp3);

        if (!(cname=addrport_to_anyname(&caddr, cport, proto, format)))
                exit(1);
        if (!(vname=addrport_to_anyname(&vaddr, vport, proto, format)))
                exit(1);
        if (!(dname=addrport_to_anyname(&daddr, dport, proto, format)))
                exit(1);

	sec100s = ((expires % HZ) * 100) / HZ;
	seconds = (expires / HZ) % 60;
	minutes = expires / (60 * HZ);

        printf("%-3s %02ld:%02ld.%02ld %-11s %-17s %-17s %-17s\n",
               protocol, minutes, seconds, sec100s, state,
               cname, vname, dname);

        free(cname);
        free(vname);
        free(dname);
}

                
void list_conn(unsigned int format)
{
        static char buffer[256];
        FILE *handle;

        handle = fopen(CONN_PROC_FILE, "r");
        if (!handle) {
                fprintf(stderr, "cannot open file %s\n", CONN_PROC_FILE);
                exit(1);
        }

        /* read the first line */
        if (fgets(buffer, sizeof(buffer), handle) == NULL) {
                fprintf(stderr, "unexpected input from %s\n",
                        CONN_PROC_FILE);
                exit(1);
        }
        printf("IPVS connection entries\n");
        printf("pro expire   %-11s %-17s %-17s %-17s\n",
               "state", "source", "virtual", "destination");
        
        /*
         * Print the VS information according to the format
         */
        while (!feof(handle)) {
                if (fgets(buffer, sizeof(buffer), handle))
                        print_conn(buffer, format);
        }

        fclose(handle);
}


void list_vs(unsigned int format)
{
        static char buffer[256];
        FILE *handle;
        int i;

        handle = fopen(IPVS_PROC_FILE, "r");
        if (!handle) {
                fprintf(stderr, "Could not open the %s file\n"
                        "Are you sure that IP Virtual Server is supported "
                        "by the kernel?\n", IPVS_PROC_FILE);
                exit(1);
        }

        /*
         * Read and print the first three head lines
         */
        for (i=0; i<2 && !feof(handle); i++) {
                if (fgets(buffer, sizeof(buffer), handle)
                    && !(format & FMT_RULE))
                        printf("%s", buffer);
        }
        if (fgets(buffer, sizeof(buffer), handle) && !(format & FMT_RULE))
                printf("  -> RemoteAddress:Port             "
                       "Forward Weight ActiveConn InActConn\n");
        
        /*
         * Print the VS information according to the format
         */
        while (!feof(handle)) {
                if (fgets(buffer, sizeof(buffer), handle))
                        print_vsinfo(buffer, format);
        }

        fclose(handle);
}


char *get_fwd_switch(char *fwd) 
{
        char *swt;
        
        switch (fwd[0]) {
        case 'M':
                swt = "-m"; break;
        case 'T':
                swt = "-i"; break;
        default:
                swt = "-g"; break;
        }
        return swt;
}
        
void print_vsinfo(char *buf, unsigned int format)
{
        /* virtual service variables */
        char protocol[10];
        static unsigned short  proto = 0;
        static struct in_addr  vaddr;
        static unsigned short  vport;
        static u_int32_t fwmark;
        char scheduler[10];
        char flags[40];
        unsigned int timeout;
        struct in_addr  vmask;

        /* destination variables */
        static char arrow[10];
        struct in_addr	daddr;
        unsigned short	dport;
        static char fwd[10];
        int weight;
        int activeconns;
        int inactconns;
        
        int n;
        unsigned long temp;
        unsigned long temp2;
        char *vname;
        char *dname;
	
        if (buf[0] == ' ') {
                /* destination entry */
                if ((n = sscanf(buf, " %s %lX:%hX %s %d %d %d",
                                arrow, &temp, &dport, fwd, &weight,
                                &activeconns, &inactconns)) == -1)
                        exit(1);
                if (n != 7)
                        fail(2, "unexpected input data");
                
                daddr.s_addr = (__u32) htonl(temp);

                if (!(dname=addrport_to_anyname(&daddr, dport, proto, format)))
                        exit(1);
                if (format & FMT_RULE) {
                        if (fwmark == 0) {
                                if (!(vname=addrport_to_anyname
                                      (&vaddr, vport, proto, format)))
                                        exit(1);

                                printf("-a %s %s -r %s %s -w %d\n",
                                       proto==IPPROTO_TCP?"-t":"-u",
                                       vname, dname, get_fwd_switch(fwd), weight);
                                free(vname);
                        } else {
                                printf("-a -f %d -r %s %s -w %d\n", fwmark,
                                       dname, get_fwd_switch(fwd), weight);
                        }
                } else {
                        printf("  -> %-30s %-7s %-6d %-10d %-10d\n",
                               dname , fwd, weight, activeconns, inactconns);
                }
                free(dname);
        } else if (buf[0] == 'F') {
                /* fwmark virtual service entry */
                if ((n = sscanf(buf, "%s %X %s %s %u %lX", protocol, 
                                &fwmark, scheduler, flags, 
                                &timeout, &temp2)) == -1)
                        exit(1);
                if (n!=6 && n!=3)
                        fail(2, "unexpected input data");

                vmask.s_addr = (__u32) htonl(temp2);

                if (format & FMT_RULE)
                        printf("-A -f %d -s %s", fwmark, scheduler);
                else
                        printf("%s  %d %s", protocol, fwmark, scheduler);
                
                if (n == 3)
                        printf("\n");
                else {

                        printf(" %s %d",
                               format&FMT_RULE?"-p":flags, timeout/HZ);

                        if (vmask.s_addr == (unsigned long int) 0xffffffff)
                                printf("\n");
                        else
                                printf(" %s %s\n",
                                       format&FMT_RULE?"-M":"mask",
                                       inet_ntoa(vmask));

                }
        } else {
                /* TCP/UDP virtual service entry  */
                fwmark=0;  /* Reset firewall mark to unused */
                
                if ((n = sscanf(buf, "%s %lX:%hX %s %s %u %lX",
                                protocol, &temp, &vport, scheduler,
                                flags, &timeout, &temp2)) == -1)
                        exit(1);
                if (n!=7 && n!=4)
                        fail(2, "unexpected input data");

                vaddr.s_addr = (__u32) htonl(temp);
                vmask.s_addr = (__u32) htonl(temp2);
                if (strcmp(protocol, "TCP") == 0) proto = IPPROTO_TCP;
                else if (strcmp(protocol, "UDP") == 0) proto = IPPROTO_UDP;
                else proto = 0;


                if (!(vname=addrport_to_anyname(&vaddr, vport, proto, format)))
                        exit(1);

                if (format & FMT_RULE)
                        printf("-A %s %s -s %s", proto==IPPROTO_TCP?"-t":"-u",
                               vname, scheduler);
                else
                        printf("%s  %s %s", protocol, vname, scheduler);
                
                if (n == 4)
                        printf("\n");
                else {
                        printf(" %s %d",
                               format&FMT_RULE?"-p":flags, timeout/HZ);

                        if (vmask.s_addr == (unsigned long int) 0xffffffff)
                                printf("\n");
                        else
                                printf(" %s %s\n",
                                       format&FMT_RULE?"-M":"mask",
                                       inet_ntoa(vmask));
                }
                free(vname);
        }
}


int host_to_addr(const char *name, struct in_addr *addr)
{
        struct hostent *host;

        if ((host = gethostbyname(name)) != NULL) {
                if (host->h_addrtype != AF_INET ||
                    host->h_length != sizeof(struct in_addr))
                        return -1;
                /* warning: we just handle h_addr_list[0] here */
                memcpy(addr, host->h_addr_list[0], sizeof(struct in_addr));
                return 0;
        }
        return -1;
}


char * addr_to_host(struct in_addr *addr)
{
        struct hostent *host;

        if ((host = gethostbyaddr((char *) addr,
                                  sizeof(struct in_addr), AF_INET)) != NULL)
                return (char *) host->h_name;
        else
                return (char *) NULL;
}


char * addr_to_anyname(struct in_addr *addr)
{
        char *name;

        if ((name = addr_to_host(addr)) != NULL)
                return name;
        else
                return inet_ntoa(*addr);
}


int service_to_port(const char *name, unsigned short proto)
{
        struct servent *service;

        if (proto == IPPROTO_TCP
            && (service = getservbyname(name, "tcp")) != NULL)
                return ntohs((unsigned short) service->s_port);
        else if (proto == IPPROTO_UDP
                 && (service = getservbyname(name, "udp")) != NULL)
                return ntohs((unsigned short) service->s_port);
        else
                return -1;
}


char * port_to_service(int port, unsigned short proto)
{
        struct servent *service;

        if (proto == IPPROTO_TCP &&
            (service = getservbyport(htons(port), "tcp")) != NULL)
                return service->s_name;
        else if (proto == IPPROTO_UDP &&
                 (service = getservbyport(htons(port), "udp")) != NULL)
                return service->s_name;
        else
                return (char *) NULL;
}


char * port_to_anyname(int port, unsigned short proto)
{
        char *name;
        static char buf[10];

        if ((name = port_to_service(port, proto)) != NULL)
                return name;
        else {
                sprintf(buf, "%d", port);
                return buf;
        }
}


char * addrport_to_anyname(struct in_addr *addr, int port, 
                           unsigned short proto, unsigned int format)
{
        char *buf;

        if (!(buf=malloc(60)))
                return NULL;

        if (format & FMT_NUMERIC) {
                snprintf(buf, 60, "%s:%u",
                         inet_ntoa(*addr), port);
        } else {
                snprintf(buf, 60, "%s:%s", addr_to_anyname(addr),
                         port_to_anyname(port, proto));
        }

        return buf;
}


int str_is_digit(const char *str)
{
        size_t offset;
        size_t top;

        top = strlen(str);
        for (offset=0; offset<top; offset++) {
                if (!isdigit((int)*(str+offset))) {
                        break;
                }
        }

        return((offset<top)?0:1);
}
