#!/usr/bin/perl

# i-MSCP - internet Multi Server Control Panel
# Copyright (C) 2010-2018 by Laurent Declercq <l.declercq@nuxwin.com>
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

use strict;
use warnings;
use FindBin;
use lib "$FindBin::Bin/../../../../PerlLib", "$FindBin::Bin/../../../../PerlVendor";
use iMSCP::Boolean;
use iMSCP::Bootstrapper;
use iMSCP::Debug qw/ debug newDebug /;
use iMSCP::Execute qw/ execute /;
use iMSCP::File;
use iMSCP::ProgramFinder;

newDebug( 'imscp-rkhunter-package.log' );

iMSCP::Bootstrapper->getInstance()->boot( {
    nolock          => TRUE,
    nokeys          => TRUE,
    nodatabase      => TRUE,
    config_readonly => TRUE
} );

exit 0 unless iMSCP::ProgramFinder::find( 'rkhunter' );

my $logFile = $::{'RKHUNTER_LOG'} || '/var/log/rkhunter.log';

# Error handling is specific with rkhunter. Therefore, we do not handle the
# exit code, but we write the output into the imscp-rkhunter-package.log file.
# This is calqued on the cron task as provided by the Rkhunter Debian  package
# except that instead of sending an email on error or warning, we write in log
# file.
execute( "rkhunter --cronjob --logfile $logFile", \my $stdout, \my $stderr );
debug( $stdout ) if $stdout;
debug( $stderr ) if $stderr;
exit 0 unless -f $logFile;

my $file = iMSCP::File->new( filename => $logFile );
my $rs = $file->owner( $::imscpConfig{'ROOT_USER'}, $::imscpConfig{'IMSCP_GROUP'} );
$rs ||= $file->mode( 0640 );
exit $rs;