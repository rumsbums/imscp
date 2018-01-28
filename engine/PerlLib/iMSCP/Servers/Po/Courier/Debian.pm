=head1 NAME

 iMSCP::Servers::Po::Courier::Debian - i-MSCP (Debian) Courier IMAP/POP3 server implementation

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

package iMSCP::Servers::Po::Courier::Debian;

use strict;
use warnings;
use Carp qw/ croak /;
use iMSCP::Debug qw/ debug error /;
use iMSCP::Execute qw/ execute /;
use iMSCP::Getopt;
use iMSCP::Mount qw/ umount /;
use iMSCP::TemplateParser qw/ replaceBlocByRef /;
use Class::Autouse qw/ :nostat File::Spec iMSCP::Dir iMSCP::File iMSCP::SystemUser /;
use iMSCP::Service;
use version;
use parent 'iMSCP::Servers::Po::Courier::Abstract';

our $VERSION = '2.0.0';

=head1 DESCRIPTION

 i-MSCP (Debian) Courier IMAP/POP3 server implementation.

=head1 PUBLIC METHODS

=over 4

=item install( )

 iMSCP::Servers::Po::Courier::Abstract()

=cut

sub install
{
    my ($self) = @_;

    my $rs = $self->SUPER::install();
    $rs ||= $self->_cleanup();
}

=item postinstall( )

 See iMSCP::Servers::Abstract::postinstall()

=cut

sub postinstall
{
    my ($self) = @_;

    eval {
        my @toEnableServices = ( 'courier-authdaemon', 'courier-pop', 'courier-pop' );
        my @toDisableServices = ();

        if ( $main::imscpConfig{'SERVICES_SSL_ENABLED'} eq 'yes' ) {
            push @toEnableServices, 'courier-pop-ssl', 'courier-imap-ssl';
        } else {
            push @toDisableServices, 'courier-pop-ssl', 'courier-imap-ssl';
        }

        my $srvProvider = iMSCP::Service->getInstance();
        $srvProvider->enable( $_ ) for @toEnableServices;

        for ( @toDisableServices ) {
            $srvProvider->stop( $_ );
            $srvProvider->disable( $_ );
        }
    };
    if ( $@ ) {
        error( $@ );
        return 1;
    }

    $self->SUPER::postinstall();
}

=item uninstall( )

 See iMSCP::Servers::Po::Courier::Abstract::uninstall()

=cut

sub uninstall
{
    my ($self) = @_;

    my $rs = $self->SUPER::uninstall();
    return $rs if $rs;

    eval {
        my $srvProvider = iMSCP::Service->getInstance();
        for ( 'courier-authdaemon', 'courier-pop', 'courier-pop-ssl', 'courier-imap', 'courier-imap-ssl' ) {
            $srvProvider->restart( $_ ) if $srvProvider->hasService( $_ ) && $srvProvider->isRunning( $_ );
        };
    };
    if ( $@ ) {
        error( $@ );
        return 1;
    }

    0;
}

=item dpkgPostInvokeTasks()

 See iMSCP::Servers::Abstract::dpkgPostInvokeTasks()

=cut

sub dpkgPostInvokeTasks
{
    my ($self) = @_;

    return 0 unless -x '';

    $self->_setVersion();
}

=item start( )

 See iMSCP::Servers::Abstract::start()

=cut

sub start
{
    my ($self) = @_;

    eval {
        my $srvProvider = iMSCP::Service->getInstance();
        $srvProvider->start( $_ ) for 'courier-authdaemon', 'courier-pop', 'courier-imap';

        if ( $main::imscpConfig{'SERVICES_SSL_ENABLED'} eq 'yes' ) {
            $srvProvider->start( $_ ) for 'courier-pop-ssl', 'courier-imap-ssl';
        }
    };
    if ( $@ ) {
        error( $@ );
        return 1;
    }

    0;
}

=item stop( )

 See iMSCP::Servers::Abstract::stop()

=cut

sub stop
{
    my ($self) = @_;

    eval {
        my $srvProvider = iMSCP::Service->getInstance();

        for ( 'courier-authdaemon', 'courier-pop', 'courier-imap', 'courier-pop-ssl', 'courier-imap-ssl' ) {
            $srvProvider->stop( $_ );
        }

    };
    if ( $@ ) {
        error( $@ );
        return 1;
    }

    0;
}

=item restart( )

 See iMSCP::Servers::Abstract::restart()

=cut

sub restart
{
    my ($self) = @_;

    eval {
        my $srvProvider = iMSCP::Service->getInstance();
        $srvProvider->restart( $_ ) for 'courier-authdaemon', 'courier-pop', 'courier-imap';

        if ( $main::imscpConfig{'SERVICES_SSL_ENABLED'} eq 'yes' ) {
            $srvProvider->restart( $_ ) for 'courier-pop-ssl', 'courier-imap-ssl';
        }
    };
    if ( $@ ) {
        error( $@ );
        return 1;
    }

    0;
}

=item reload( )

 See iMSCP::Servers::Abstract::reload()

=cut

sub reload
{
    my ($self) = @_;

    eval {
        my $srvProvider = iMSCP::Service->getInstance();
        $srvProvider->reload( $_ ) for 'courier-authdaemon', 'courier-pop', 'courier-imap';

        if ( $main::imscpConfig{'SERVICES_SSL_ENABLED'} eq 'yes' ) {
            $srvProvider->reload( $_ ) for 'courier-pop-ssl', 'courier-imap-ssl';
        }
    };
    if ( $@ ) {
        error( $@ );
        return 1;
    }

    0;
}

=back

=head1 PRIVATE METHODS

=over 4

=item _setVersion( )

 See iMSCP::Servers::Po::Courier::Abstract::_setVersion()

=cut

sub _setVersion
{
    my ($self) = @_;

    my $rs = execute( 'dpkg -s courier-base | grep -i \'^version\'', \ my $stdout, \ my $stderr );
    error( $stderr || 'Unknown error' ) if $rs;
    return $rs if $rs;

    if ( $stdout !~ /version:\s+([\d.]+)/i ) {
        error( "Couldn't guess Courier version from the `dpkg -s courier-base | grep -i '^version'` command output" );
        return 1;
    }

    $self->{'config'}->{'PO_VERSION'} = $1;
    debug( sprintf( 'Courier version set to: %s', $1 ));
    0;
}

=item _cleanup( )

 Processc cleanup tasks

 Return int 0 on success, other on failure

=cut

sub _cleanup
{
    my ($self) = @_;

    my $oldPluginApiVersion = version->parse( $main::imscpOldConfig{'PluginApi'} );

    return unless $oldPluginApiVersion < version->parse( '1.5.2' );

    for ( qw/ pop3d pop3d-ssl imapd imapd-ssl / ) {
        next unless -f "$self->{'config'}->{'PO_CONF_DIR'}/$_";

        my $file = iMSCP::File->new( filename => "$self->{'config'}->{'PO_CONF_DIR'}/$_" );
        my $fileContentRef = $file->getAsRef();
        unless ( defined $fileContentRef ) {
            error( sprintf( "Couldn't read the %s file", $file->{'filename'} ));
            return 1;
        }

        replaceBlocByRef(
            qr/(:?^\n)?# Servers::po::courier::installer - BEGIN\n/m, qr/# Servers::po::courier::installer - ENDING\n/, '', $fileContentRef
        );
    }

    return unless $oldPluginApiVersion < version->parse( '1.5.1' );

    if ( -f "$self->{'cfgDir'}/courier.old.data" ) {
        my $rs = iMSCP::File->new( filename => "$self->{'cfgDir'}/courier.old.data" )->delFile();
        return $rs if $rs;
    }

    if ( -f "$self->{'config'}->{'PO_AUTHLIB_CONF_DIR'}/userdb" ) {
        my $file = iMSCP::File->new( filename => "$self->{'config'}->{'PO_AUTHLIB_CONF_DIR'}/userdb" );
        $file->set( '' );
        my $rs = $file->save();
        $rs ||= $file->mode( 0600 );
        return $rs if $rs;

        $rs = execute( [ 'makeuserdb', '-f', "$self->{'config'}->{'PO_AUTHLIB_CONF_DIR'}/userdb" ], \ my $stdout, \ my $stderr );
        debug( $stdout ) if $stdout;
        error( $stderr || 'Unknown error' ) if $rs;
        return $rs if $rs;
    }

    # Remove postfix user from authdaemon group.
    # It is now added in mail group (since 1.5.0)
    my $rs = iMSCP::SystemUser->new()->removeFromGroup( $self->{'config'}->{'PO_AUTHDAEMON_GROUP'}, $self->{'mta'}->{'config'}->{'MTA_USER'} );
    return $rs if $rs;

    # Remove old authdaemon socket private/authdaemon mount directory.
    # Replaced by var/run/courier/authdaemon (since 1.5.0)
    my $fsFile = File::Spec->canonpath( "$self->{'mta'}->{'config'}->{'MTA_QUEUE_DIR'}/private/authdaemon" );
    $rs ||= umount( $fsFile );
    return $rs if $rs;

    eval { iMSCP::Dir->new( dirname => $fsFile )->remove(); };
    if ( $@ ) {
        error( $@ );
        return 1;
    }

    0;
}

=item _shutdown( $priority )

 See iMSCP::Servers::Abstract::_shutdown()

=cut

sub _shutdown
{
    my ($self, $priority) = @_;

    return unless my $action = $self->{'restart'} ? 'restart' : ( $self->{'reload'} ? 'reload' : undef );

    iMSCP::Service->getInstance()->registerDelayedAction( 'courier', [ $action, sub { $self->$action(); } ], $priority );
}

=back

=head1 AUTHOR

 Laurent Declercq <l.declercq@nuxwin.com>

=cut

1;
__END__
