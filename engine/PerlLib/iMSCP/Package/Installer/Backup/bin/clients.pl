#!/usr/bin/perl

=head1 NAME

 imscp-backup-all backup i-MSCP client data.

=head1 SYNOPSIS

 imscp-backup-all [options]...

=cut

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
use File::Basename;
use File::Spec;
use FindBin;
use lib "$FindBin::Bin/../../../../../../PerlLib", "$FindBin::Bin/../../../../../../PerlVendor";
use iMSCP::Boolean;
use iMSCP::Bootstrapper;
use iMSCP::Debug qw/ debug error getMessageByType newDebug warning /;
use iMSCP::Dir;
use iMSCP::Execute 'execute';
use iMSCP::Ext2Attributes qw/ isImmutable setImmutable clearImmutable /;
use iMSCP::Getopt;
use iMSCP::Mail;
use iMSCP::Package::Installer::Backup;
use Servers::mta;
use iMSCP::Package::Installer::Backup;
use POSIX qw/ strftime locale_h /;

my $CMDS = {
    bzip2  => {
        extension => 'bz2',
        command   => 'bzip2'
    },
    pbzip2 => {
        extension => 'bz2',
        command   => 'pbzip2'
    },
    gzip   => {
        extension => 'gz',
        command   => 'gzip'
    },
    pigz   => {
        extension => 'gz',
        command   => 'pigz'
    },
    lzma   => {
        extension => 'lzma',
        command   => 'lzma'
    },
    xz     => {
        extension => 'xz',
        command   => 'xz'
    }
};

=head1 DESCRIPTION

 Backup customer data

=head1 PUBLIC METHODS

=over 4

=item backupHomedir( $mainDmnName, $homeDir, $bkpDir, $cmpAlgo, $cmpLevel, $bkpExt, $user, $group )

 Backup a customer home directory

 Param string $mainDmnName Customer main domain name
 Param string $homeDir Customer home directory path
 Param string $bkpDir Customer backup directory path
 Param string $cmpAlgo Compression algorithm
 Param string $cmpLevel Compression level
 Param string $bkpExt Backup archive extension
 Param string $user Customer Web unix user
 Param string $group Customer Web unix group
 Return void

=cut

sub backupHomedir
{
    my ( $mainDmnName, $homeDir, $bkpDir, $cmpAlgo, $cmpLevel, $bkpExt, $user, $group ) = @_;

    my $date = strftime "%Y.%m.%d-%H-%M", localtime;
    my $archName = "web-backup-$mainDmnName-$date.tar$bkpExt";
    my @cmd = (
        "tar -c -C $homeDir --exclude=./logs --exclude=./phptmp --exclude=./backups .",
        ( $cmpAlgo->{'config'}->{'BACKUP_COMPRESS_ALGORITHM'} eq 'none'
            ? "-f $bkpDir/$archName"
            : "| $CMDS->{$cmpAlgo->{'config'}->{'BACKUP_COMPRESS_ALGORITHM'}}->{'command'} -$cmpLevel > $bkpDir/$archName"
        )
    );

    my $rs = execute( "@cmd", \my $stdout, \my $stderr );
    debug( $stdout ) if $stdout;
    debug( $stdout ) if $stdout;

    # tar exit with status 1 only if some files were changed while being read. We want ignore this.
    if ( $rs > 1 ) {
        error( $stderr || 'Unknown error' ) if $stderr;
        return;
    }

    my $file = iMSCP::File->new( filename => "$bkpDir/$archName" );
    $rs ||= $file->owner( $user, $group );
    $rs ||= $file->mode( 0640 );
}

=item backupDatabases( $mainDmnId, $bkpDir, $cmpAlgo, $cmpLevel, $bkpExt, $user, $group )

 Backup customer databases

 Param string $mainDmnId Customer main domain identifier
 Param string $bkpDir Customer backup directory path
 Param string $cmpAlgo Compression algorithm
 Param string $cmpLevel Compression level
 Param string $bkpExt Backup archive extension
 Param string $user Customer Web unix user
 Param string $group Customer Web unix group
 Return void

=cut

sub backupDatabases
{
    my ( $mainDmnId, $bkpDir, $cmpAlgo, $cmpLevel, $user, $group ) = @_;

    my $db = iMSCP::Database->factory();
    my $rows = eval {
        my $rdbh = $db->getRawDb();
        local $rdbh->{'RaiseError'} = TRUE;
        $rdbh->selectall_hashref( 'SELECT sqld_id, sqld_name FROM sql_database WHERE domain_id = ?', 'sqld_name', undef, $mainDmnId );
    };
    if ( $@ ) {
        error( $@ );
        return;
    }

    for my $dbName ( keys %{ $rows } ) {
        eval { $db->dumpdb( $dbName, $bkpDir ); };
        warning( $@ ) if $@;
        next if $@;

        # Encode slashes as SOLIDUS unicode character
        # Encode dots as Full stop unicode character
        ( my $encodedDbName = $dbName ) =~ s%([./])%{ '/', '@002f', '.', '@002e' }->{$1}%ge;
        my $dbDumpFilePath = File::Spec->catfile( $bkpDir, $encodedDbName . '.sql' );
        my $file = iMSCP::File->new( filename => $dbDumpFilePath );
        my $rs = $file->owner( $user, $group );
        $rs ||= $file->mode( 0640 );

        next if $rs || $cmpAlgo eq 'no';

        $rs = execute( [ ${ $CMDS }->{$cmpAlgo}->{'command'}, "-$cmpLevel", '--force', $dbDumpFilePath ], \my $stdout, \my $stderr );
        debug( $stdout ) if $stdout;
        error( sprintf( "Couldn't compress %s database dump: %s", $dbName, $stderr || 'Unknown error' )) if $rs;
    }
}

=item backupMaildirs( $dmnId, $bkpDir, $cmpAlgo, $cmpLevel, $bkpExt, $user, $group )

 Backup customer' mail directories

 Param string $dmnId Customer main domain ID
 Param string $bkpDir Customer backup directory path
 Param string $cmpAlgo Compression algorithm
 Param string $cmpLevel Compression level
 Param string $bkpExt Backup archive extension
 Param string $user Customer Web unix user
 Param string $group Customer Web unix group
 Return void

=cut

sub backupMaildirs
{
    my ( $dmnId, $bkpDir, $cmpAlgo, $cmpLevel, $bkpExt, $user, $group ) = @_;

    my $rows = eval {
        my $rdbh = iMSCP::Database->factory()->getRawDb();
        local $rdbh->{'RaiseError'} = TRUE;

        $rdbh->selectall_hashref(
            "
                SELECT domain_name
                FROM domain
                WHERE domain_id = ?
                AND domain_status <> 'todelete'
                UNION ALL
                SELECT CONCAT(subdomain_name, '.', domain_name)
                FROM subdomain
                JOIN domain USING(domain_id)
                WHERE domain_id = ?
                AND subdomain_status <> 'todelete'
                UNION ALL
                SELECT alias_name
                FROM domain_aliasses
                WHERE domain_id = ?
                AND alias_status <> 'todelete'
                UNION ALL
                SELECT CONCAT(subdomain_alias_name, '.', alias_name)
                FROM subdomain_alias
                JOIN domain_aliasses USING(alias_id)
                WHERE domain_id = ?
                AND subdomain_alias_status <> 'todelete'
            ",
            'domain_name', undef, $dmnId, $dmnId, $dmnId, $dmnId
        );
    };
    if ( $@ ) {
        error( $@ );
        return;
    }

    my $virtualMailDir = Servers::mta->factory()->{'config'}->{'MTA_VIRTUAL_MAIL_DIR'};

    for ( keys %{ $rows } ) {
        my $mailDirPath = "$virtualMailDir/$_";
        next unless -d $mailDirPath;

        my $bkpDate = strftime "%Y.%m.%d-%H-%M", localtime();
        my $bkpArchName = "mail-backup-$_-$bkpDate.tar$bkpExt";
        my @cmd = (
            "tar -c -C $mailDirPath .",
            ( $cmpAlgo eq 'no' ? "-f $bkpDir/$bkpArchName" : "| $CMDS->{$cmpAlgo}->{'command'} -$cmpLevel > $bkpDir/$bkpArchName" )
        );

        my $rs = execute( "@cmd", \my $stdout, \my $stderr );
        debug( $stdout ) if $stdout;
        error( $stderr || 'Unknown error' ) if $rs > 1;

        my $file = iMSCP::File->new( filename => "$bkpDir/$bkpArchName" );
        $rs ||= $file->owner( $user, $group );
        $rs = $file->mode( 0640 );
    }
}

=item backupAll( )

 Backup customer data

 Return void

=cut

sub backupAll
{
    my $bkp = iMSCP::Package::Installer::Backup->getInstance();
    my $algo = $bkp->{'config'}->{'BACKUP_COMPRESS_ALGORITHM'} || 'none';
    my $level = $bkp->{'config'}->{'BACKUP_COMPRESS_LEVEL'};
    my $ext = $bkp->{'config'}->{'BACKUP_COMPRESS_ALGORITHM'} ne 'none' ? '.' . $CMDS->{$algo}->{'extension'} : '';

    my $rdbh = iMSCP::Database->factory()->getRawDb();
    local $rdbh->{'RaiseError'} = TRUE;
    my $rows = $rdbh->selectall_hashref(
        "
            SELECT domain_id, domain_name, domain_admin_id, allowbackup, admin_sys_name, admin_sys_gname
            FROM domain
            JOIN admin ON (admin_id = domain_admin_id)
            WHERE domain_status NOT IN ('disabled', 'todelete')
            AND allowbackup <> ''
        ",
        'domain_id'
    );

    while ( my ( $dmnId, $dmnData ) = each( %{ $rows } ) ) {
        next unless $dmnData->{'allowbackup'} && $dmnData->{'allowbackup'} =~ /\b(?:dmn|sql|mail)\b/;
        my $homeDir = "$::imscpConfig{'USER_WEB_DIR'}/$dmnData->{'domain_name'}";
        next unless -d $homeDir;

        my $bkpDir = "$homeDir/backups";
        my $user = $dmnData->{'admin_sys_name'};
        my $group = $dmnData->{'admin_sys_gname'};

        eval {
            unless ( -d $bkpDir ) {
                my $isProtectedHomedir = FALSE;

                if ( isImmutable( $homeDir ) ) {
                    $isProtectedHomedir = TRUE;
                    clearImmutable( $homeDir );
                }

                iMSCP::Dir->new( dirname => $bkpDir )->make( {
                    user  => $user,
                    group => $group,
                    mode  => 0750
                } );

                setImmutable( $homeDir ) if $isProtectedHomedir;
            } else {
                iMSCP::Dir->new( dirname => $bkpDir )->clear( undef, qr/.*/ );
            }
        };
        if ( $@ ) {
            error( $@ );
            next;
        }

        if ( $dmnData->{'allowbackup'} =~ /\bdmn\b/ ) {
            backupHomedir( $dmnData->{'domain_name'}, $homeDir, $bkpDir, $algo, $level, $ext, $user, $group )
        }

        if ( $dmnData->{'allowbackup'} =~ /\bsql\b/ ) {
            backupDatabases( $dmnId, $bkpDir, $algo, $level, $ext, $user, $group );
        }

        if ( $dmnData->{'allowbackup'} =~ /\bmail\b/ ) {
            backupMaildirs( $dmnId, $bkpDir, $algo, $level, $ext, $user, $group );
        }
    }
}

newDebug( 'imscp-backup-all.log' );

iMSCP::Getopt->parse( sprintf( "Usage: perl %s [OPTION]...", basename( $0 )) . qq{

Script that backup i-MSCP customer's data.

OPTIONS:
 -v,    --verbose       Enable verbose mode.},
    'debug|d'   => \&iMSCP::Getopt::debug,
    'verbose|v' => \&iMSCP::Getopt::verbose
);

my $bootstrapper = iMSCP::Bootstrapper->getInstance();
exit unless $bootstrapper->lock( '/var/lock/imscp-backup-all.lock', 'nowait' );

$bootstrapper->boot( {
    config_readonly => TRUE,
    nolock          => TRUE
} );

backupAll();

if ( my @errors = getMessageByType( 'error' ) ) {
    iMSCP::Mail->new()->errmsg( "@errors" );
}

=back

=head1 AUTHOR

 Laurent Declercq <l.declercq@nuxwin.com>

=cut

1;
__END__
