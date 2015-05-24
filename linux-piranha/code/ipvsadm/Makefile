#
#      ipvsadm - IP Virtual Server ADMinistration program
#                for IPVS NetFilter Module in kernel 2.4
#
#      Version: $Id: Makefile,v 1.2.4.1 2001/05/29 20:16:07 kbarrett Exp $
#
#      Authors: Wensong Zhang <wensong@iinchina.net>
#               Peter Kese <peter.kese@ijs.si>
#
#      This file:
#
#      ChangeLog
#
#      Wensong        :   Modified the Makefile and the spec files so
#                     :   that rpms can be created with ipvsadm alone
#      P.Copeland     :   Modified the Makefile and the spec files so
#                     :   that it is possible to create rpms on the fly
#                     :   using 'make rpms'
#                     :   Also added NAME, VERSION and RELEASE numbers to
#                     :   the Makefile
#      Horms          :   Updated to add config_stream.c dynamic_array.c
#                     :   Added autodetection of libpot
#                     :   Added BUILD_ROOT support
#      Wensong        :   Changed the OBJS according to detection
#

NAME	= ipvsadm
VERSION	= $(shell cat VERSION)
RELEASE	= 1
SCHEDULERS = "$(shell cat SCHEDULERS)"

CC	= gcc
CFLAGS	= -Wall -Wunused -Wstrict-prototypes -g -O2
SBIN    = $(BUILD_ROOT)/sbin
MAN     = $(BUILD_ROOT)/usr/man/man8
INIT    = $(BUILD_ROOT)/etc/rc.d/init.d
MKDIR   = mkdir
INSTALL = install
INCLUDE = -I/usr/src/linux/include -I..
LIB_SEARCH = /lib /usr/lib /usr/local/lib

#####################################
# No servicable parts below this line

POPT_LIB = $(shell for i in $(LIB_SEARCH); do \
  if [ -f $$i/libpopt.a ]; then \
    if nm $$i/libpopt.a | fgrep -q poptGetContext; then \
        echo "-L$$i -lpopt"; \
    fi; \
  fi; \
done)

ifneq (,$(POPT_LIB))
POPT_DEFINE = -DHAVE_POPT
OBJS = config_stream.o dynamic_array.o ipvsadm.o
else
OBJS = ipvsadm.o
endif

LIBS = $(POPT_LIB)
DEFINES = -DVERSION=\"$(VERSION)\" -DSCHEDULERS=\"$(SCHEDULERS)\" \
	  $(POPT_DEFINE)

.PHONY = all clean install dist distclean rpm rpms

all:            ipvsadm

ipvsadm:	$(OBJS)
		$(CC) $(CFLAGS) -o ipvsadm $(OBJS) $(LIBS)

install:        ipvsadm
		strip ipvsadm
		if [ ! -d $(SBIN) ]; then $(MKDIR) -p $(SBIN); fi
		$(INSTALL) -m 0755 ipvsadm $(SBIN)
		$(INSTALL) -m 0755 ipvsadm-save $(SBIN)
		$(INSTALL) -m 0755 ipvsadm-restore $(SBIN)
		if [ ! -d $(MAN) ]; then $(MKDIR) -p $(MAN); fi
		$(INSTALL) -m 0644 ipvsadm.8 $(MAN)
		$(INSTALL) -m 0644 ipvsadm-save.8 $(MAN)
		$(INSTALL) -m 0644 ipvsadm-restore.8 $(MAN)
		if [ -d $(INIT) ]; then \
		  $(INSTALL) -m 0755 ipvsadm.sh $(INIT)/ipvsadm; \
		fi

clean:
		rm -f ipvsadm *.o core *~ $(NAME).spec \
			$(NAME)-$(VERSION).tar.gz
		rm -rf debian/tmp

distclean:	dist

dist:		clean
		sed -e "s/@@VERSION@@/$(VERSION)/g" \
                    -e "s/@@RELEASE@@/$(RELEASE)/g" \
                    < ipvsadm.spec.in > ipvsadm.spec
		( cd .. ; tar czvf $(NAME)-$(VERSION).tar.gz \
			--exclude CVS \
			--exclude $(NAME)-$(VERSION).tar.gz \
			ipvsadm ; \
			mv $(NAME)-$(VERSION).tar.gz ipvsadm )

rpm:		rpms

rpms:		dist
		cp $(NAME)-$(VERSION).tar.gz /usr/src/redhat/SOURCES/
		cp $(NAME).spec /usr/src/redhat/SPECS/
		(cd /usr/src/redhat/SPECS/ ; rpm -ba $(NAME).spec)

deb:		debs

debs:		
		dpkg-buildpackage

%.o:	%.c
		$(CC) $(CFLAGS) $(INCLUDE) $(DEFINES) -c $<
