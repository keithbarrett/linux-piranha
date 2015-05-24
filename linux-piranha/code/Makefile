#
#	Makefile for the Red Hat Linux Vitrual Server package 
#	(C) Red Hat, Inc. 1999 All rights reserved.
#
#	This program is free software; you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by
#	the Free Software Foundation; either version 2 of the License, or
#	(at your option) any later version.
#
#	This program is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU General Public License for more details.
#
#	You should have received a copy of the GNU General Public License
#	along with this program; if not, write to the Free Software
#	Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
#

NAME	= piranha
VERSION = 0.5.5
RELEASE = 6
ARCH	= $(shell /bin/arch)
DATE	= $(shell date +%d/%b/%Y)

CFGFILE	= "/etc/sysconfig/ha/lvs.cf"
# Note: There are also hard-coded values for this in the pulse init script,
#       piranha-gui init, and in the piranha spec file. We should tie these
#       all together.


#	I have to admit, I have a weakness for using the Compaq C compiler
#	on the Alpha because it can pick out 32/64 bit problems much more 
#	easily than gcc can. Least this way if CC is defined in the 
#	enviroment, it'll should override any of this hackery - Phil Copeland

CC := $(shell if [ "$$CC" != "" ]; then echo $$CC; \
        else if [ -x /usr/bin/ccc ]; then \
		echo "ccc"; \
	else \
		echo "gcc"; \
	fi ; fi)

COPTS   = 

ifeq ($(CC),ccc)
	CFLAGS	= -Wall -g -DVERSION=\"$(VERSION)\" -DRELEASE=\"$(RELEASE)\" -DDATE=\"$(DATE)\" -DCFGFILE=\"$(CFGFILE)\"  $(COPTS)
else
	CFLAGS	= -Wall -g -DVERSION=\"$(VERSION)\" -DRELEASE=\"$(RELEASE)\" -DDATE=\"$(DATE)\" -DCFGFILE=\"$(CFGFILE)\"  $(RPM_OPT_FLAGS) $(COPTS)
endif

LDFLAGS   = -ggdb
LOADLIBES = -lpopt -lm
INCLUDE   = -I/usr/src/linux/include

SBIN	  = $(RPM_BUILD_ROOT)/usr/sbin
MANDIR	  = $(RPM_BUILD_ROOT)/usr/man
SYSDIR	  = $(RPM_BUILD_ROOT)/etc/rc.d/init.d
GNOMEDIR  = $(RPM_BUILD_ROOT)/usr/share/gnome/apps/System
KDEDIR    = $(RPM_BUILD_ROOT)/etc/X11/applnk/System
PIXMAPS   = $(RPM_BUILD_ROOT)/usr/share/pixmaps
HADIR     = $(RPM_BUILD_ROOT)/etc/sysconfig/ha

INSTALL   = install

all:	 explain lvs fos pulse nanny send_arp #ipvsadm

piranha: all lvsconfig


explain:
ifeq ($(CC),ccc)
	@echo "--------------------------------------------------------------------------"
	@echo "Compaq C found, using it by preference (use \"export CC=gcc\" to override)"
	@echo "--------------------------------------------------------------------------"
endif
ifeq ($(CC),gcc)
	@echo "--------------------------------------------------------------------------"
	@echo "Assuming build with GNU C compiler"
	@echo "--------------------------------------------------------------------------"
endif


#ipvsadm: ipvsadm/ipvsadm
#	@cd ipvsadm && VERSION=$(VERSION) make
#	@echo "--------------------------------------------------------------------------"
#	@echo "Building ipvsadm utility"
#	@echo "--------------------------------------------------------------------------"
#	$(CC) $(INCLUDE) $(CFLAGS) -o ipvsadm ipvsadm.c



lvs:    lvs.o lvsconfig.o util.o

fos:    fos.o lvsconfig.o util.o

nanny:    nanny.o util.o

send_arp: send_arp.c
	$(CC) $(CFLAGS) -o send_arp send_arp.c

pulse:    pulse.o lvsconfig.o util.o

lvsconfig: lvsconfig.o main.o 
# not normally built by default

pulse.o: pulse.c lvsconfig.h util.h

lvs.o:  lvs.c lvsconfig.h util.h

fos.o:  fos.c lvsconfig.h util.h

nanny.o: nanny.c nanny.h

lvsconfig.o:  lvsconfig.c lvsconfig.h

util.o:	util.c

main.o:  main.c lvsconfig.h

install: all
	mkdir -p $(SBIN) $(MANDIR)/man5 $(MANDIR)/man8 $(SYSDIR) $(GNOMEDIR) \
		$(KDEDIR) $(PIXMAPS) $(HADIR)
#	$(INSTALL) -m 0755 -o root -g root ipvsadm/ipvsadm $(SBIN)/
	$(INSTALL) -m 0700 -o root -g root piranha-passwd.sh \
		$(HADIR)/piranha-passwd
	$(INSTALL) -m 0755 -o root -g root lvs $(SBIN)/
	$(INSTALL) -m 0755 -o root -g root fos $(SBIN)/
	$(INSTALL) -m 0755 -o root -g root pulse $(SBIN)/
	$(INSTALL) -m 0755 -o root -g root nanny $(SBIN)/
	$(INSTALL) -m 0755 -o root -g root send_arp $(SBIN)/
	$(INSTALL) -m 0644 -o root -g root lvs.8 $(MANDIR)/man8/
	$(INSTALL) -m 0644 -o root -g root fos.8 $(MANDIR)/man8/
	$(INSTALL) -m 0644 -o root -g root lvs.cf.5 $(MANDIR)/man5/
#	$(INSTALL) -m 0644 -o root -g root ipvsadm/ipvsadm.8 $(MANDIR)/man8/
	$(INSTALL) -m 0644 -o root -g root pulse.8 $(MANDIR)/man8/
	$(INSTALL) -m 0644 -o root -g root nanny.8 $(MANDIR)/man8/
	$(INSTALL) -m 0644 -o root -g root send_arp.8 $(MANDIR)/man8/
	$(INSTALL) -m 0755 -o root -g root pulse.init $(SYSDIR)/pulse
	$(INSTALL) -m 0755 -o root -g root piranha-gui.init $(SYSDIR)/piranha-gui
	$(INSTALL) -m 0600 -o root -g root piranha.desktop $(GNOMEDIR)
	$(INSTALL) -m 0600 -o root -g root piranha.desktop $(KDEDIR)
	$(INSTALL) -m 0644 -o root -g root pics/piranha-icon.png $(PIXMAPS)

clean:
	rm -f nanny lvs lvsconfig fos pulse piranha.spec *.o *.bak \
		piranha-*.tar.gz ipvsadm.tar.gz send_arp cf
	rm -f -r *~ core
#	cd ipvsadm && make clean

dist:	clean
	sed -e "s/@@VERSION@@/$(VERSION)/g" <piranha.spec.in >piranha.spec.tmp
	sed -e "s/@@RELEASE@@/$(RELEASE)/g" <piranha.spec.tmp >piranha.spec
	rm -f piranha.spec.tmp
	( cd .. ; tar czvf piranha-$(VERSION).tar.gz \
		--exclude CVS \
		--exclude gui \
		--exclude misc \
                --exclude ipvsadm \
		piranha ; \
		mv $(NAME)-$(VERSION).tar.gz piranha ; cd piranha)
#	( tar czvf ipvsadm.tar.gz  ipvsadm )

rpms: dist
	cp $(NAME)-$(VERSION).tar.gz /usr/src/redhat/SOURCES/
	cp $(NAME).spec /usr/src/redhat/SPECS/
	(cd /usr/src/redhat/SPECS/ ; rpm -ba $(NAME).spec)

