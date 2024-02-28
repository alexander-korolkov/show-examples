#!/usr/bin/perl
use strict;
use warnings;
use utf8;

use DBI;

use Sys::Syslog qw(:DEFAULT setlogsock);
use Data::Dumper;
use MIME::Lite;
use MIME::Base64;
use Sys::Hostname;
use JSON::XS;
use IO::Socket::INET;
use IO::Compress::Gzip qw( gzip $GzipError );
use Fcntl qw(:DEFAULT :flock);
use FindBin qw($Bin);

my ( $short_name ) = $0 =~ m|([^/]+)\.pl$|;

setlogsock('unix');
openlog( $short_name, 'pid', 'local0');

my %cfg = ();
if ( $#ARGV < 7 ){
	&print_usage();
	exit 0;
} else {
	$cfg{'src'}{'db_host'} = $ARGV[0];
	$cfg{'src'}{'db_name'} = $ARGV[1];
	$cfg{'src'}{'db_user'} = $ARGV[2];
	$cfg{'src'}{'db_pass'} = $ARGV[3];
	$cfg{'dst'}{'db_host'} = $ARGV[4];
	$cfg{'dst'}{'db_name'} = $ARGV[5];
	$cfg{'dst'}{'db_user'} = $ARGV[6];
	$cfg{'dst'}{'db_pass'} = $ARGV[7];
}

my $lockfile = "/tmp/${short_name}_". $cfg{'src'}{'db_name'} .".lock";
open( LOCK, ">$lockfile" ) or &err_lock( "can't open lock file: $!" );
unless (flock(LOCK, LOCK_EX|LOCK_NB)) { &err_lock( "$0 is already running - Aborting" ); }

my %tables = (
	'site_common_daily' => ['acc_id', 'date'],
	'site_common_monthly' => ['acc_id', 'date'],
	'site_common_total' => ['acc_id'],
	'site_review_buy_all' => [ 'acc_id' ],
	'site_review_buy_month' => [ 'acc_id', 'date'  ],
	'site_review_loss_all' => [ 'acc_id' ],
	'site_review_loss_month' => [ 'acc_id', 'date' ],
	'site_review_profit_all' => [ 'acc_id' ],
	'site_review_profit_month' => [ 'acc_id', 'date' ],
	'site_review_sell_all' => [ 'acc_id' ],
	'site_review_sell_month' => [ 'acc_id', 'date' ],
	'site_review_symbol_all' => [ 'acc_id', 'symbol64' ],
	'site_review_symbol_month' => [ 'acc_id', 'date', 'symbol64' ],
	'site_review_symbol_day' => [ 'acc_id', 'date', 'symbol64' ],
	'site_review_quantity' => [ 'acc_id', 'date' ],
	'site_review_ext_month' => [ 'acc_id', 'date' ],
	'site_review_ext_all' => [ 'acc_id' ],
	'top_leaders' => [ 'acc_id', 'date_hour' ],
);

my %dst_names = (
	'top_leaders' => 'site_top_leaders',
);

## read config
#my $config_file = "$Bin/${short_name}.conf";
#print "$config_file\n";
#print ( Dumper(@ARGV), "\n" );
#print "$#ARGV\n";
#exit 0;
my $dbh_src = DBI->connect("DBI:mysql:dbname=$cfg{'src'}{'db_name'};host=$cfg{'src'}{'db_host'}", $cfg{'src'}{'db_user'}, $cfg{'src'}{'db_pass'} ) or &err( "local: ".$DBI::errstr );
my $dbh_dst = DBI->connect("DBI:mysql:dbname=$cfg{'dst'}{'db_name'};host=$cfg{'dst'}{'db_host'}", $cfg{'dst'}{'db_user'}, $cfg{'dst'}{'db_pass'} ) or &err( "remote: ".$DBI::errstr );

if ( $#ARGV == 7 ){
	## sync all tables
	foreach my $table ( keys %tables ){
		&sync_table( $table );
	}
} else {
	## sync one/few tables
	my $cnt = 0;
	foreach my $table ( @ARGV ){
		## skip connection strings
		if ( $cnt > 7 ){
			if ( exists( $tables{$table} ) ){ &sync_table( $table ); }
		}
		$cnt++;
	}
}
#my $current = '';
#open ( my $fh, "<", $config_file ) or &err( "can't open $config_file: $!" );
#while ( <$fh> ){
        #chomp;
#
        #s/^\s*#.*$//g;
        #s/^\s*$//g;
        #unless ( $_ ){ next; }
#
        #my $line = $_;
#
        #if ( $line =~ m/\[(.+)\]/ ){
                #$current = $1;
        #} elsif ( $line =~ m/\s*(\S+)\s*=\s*(\S+)\s*/ ){
                #$cfg{$current}{$1} = $2;
        #}
#}
#close( $fh );
#print Dumper(%cfg);
#exit 0;
## /read config





my $status_msg = '';
my $status_perf = '';

my %gray = ();

#if ( $#ARGV == -1 ){
	### sync all tables
	#foreach my $table ( keys %tables ){
		#&sync_table( $table );
	#}
#} else {
	### sync one/few tables
	#foreach my $table ( @ARGV ){
		#if ( exists( $tables{$table} ) ){ &sync_table( $table ); }
	#}
#}

# 1) only insert/updates
# 2) detect delete and full reload


#foreach my $table ( keys %tables ){
sub sync_table {
	my $table = shift;
	#print "$table $tables{$table}\n";
	my $cnt = 0;

	my $table_dst = $table;
	if ( exists( $dst_names{ $table } ) ){ $table_dst = $dst_names{ $table }; }

## GET ts
	### src
	my $sth_src = $dbh_src->prepare( "select max(`ts`) from $table" ) or do { &err_table( $dbh_src->errstr, $table ); return 0; };
	$sth_src->execute( ) or do { &err_table( $sth_src->errstr, $table ); return 0; };
	my ( $max_ts_src ) = $sth_src->fetchrow;
	unless( $max_ts_src ){ $max_ts_src = '1970-01-01 02:00:00'; };
	$sth_src->finish();

	### dst
	my $sth_dst = $dbh_dst->prepare( "select max(`ts`) from $table_dst" ) or do { &err_table( $dbh_dst->errstr, $table ); return 0; };
	$sth_dst->execute( ) or do { &err_table( $sth_dst->errstr, $table ); return 0; };
	my $max_ts_dst = $sth_dst->fetchrow;
	unless( $max_ts_dst ){ $max_ts_dst = '1970-01-01 02:00:00'; };
	$sth_dst->finish();
## /GET ts

## RELOAD CHANGED
	if ( $max_ts_src ne $max_ts_dst ){
		#print "[$table] partical reload\n";
		my $query_replace = '';
		my @data = ();

		### read src data
		$sth_src = $dbh_src->prepare( "select * from $table where ts >= ?" ) or do { &err_table( $dbh_src->errstr, $table ); return 0; };
		$sth_src->execute( $max_ts_dst ) or do { &err_table( $sth_src->errstr, $table ); return 0; };
		while ( my $ref = $sth_src->fetchrow_hashref ){
			### prepare insert query
			unless ( $query_replace ){
				my $set = ' ';
				my @set = ();
				foreach my $k ( sort keys %{ $ref }){
					push @set, "`$k` = ?";
				}
				$set .= join( ', ', @set );
				$query_replace = "replace into `$table_dst` set $set";

				#print "$query_replace\n";
				#exit 0;
			}
			### /prepare insert query

			push @data, $ref;
			$cnt++;
		}
		$sth_src->finish();
		### /read src data

		### insert dst
		#print "$query_insert\n";
		$sth_dst = $dbh_dst->prepare( $query_replace ) or do { &err_table( "can't prepare: ".$dbh_dst->errstr, $table ); return 0; };
		foreach my $ref ( @data ){
			my @row = ();
			foreach my $k ( sort keys %{ $ref }){
				push @row, $ref->{$k};
			}
			#my $row = join( "\t", @row );
			#print "$row\n";
			$sth_dst->execute( @row ) or do { &err_table( "can't execute $query_replace:\n".$sth_dst->errstr, $table ); return 0; };
		}
		$sth_dst->finish;
		### /insert dst
	}
	#exit 0;
## /RELOAD CHANGED

## GET CHECKSUM
	$sth_src = $dbh_src->prepare( "checksum table `$table`" ) or do { &err_table( $dbh_src->errstr, $table ); return 0; };
	$sth_src->execute( ) or do { &err_table( $sth_src->errstr, $table ); return 0; };
	my $checksum_src = $sth_src->fetchrow;
	$sth_src->finish();

	$sth_dst = $dbh_dst->prepare( "checksum table `$table_dst`" ) or do { &err_table( $dbh_dst->errstr, $table ); return 0; };
	$sth_dst->execute( ) or do { &err_table( $sth_dst->errstr, $table ); return 0; };
	my $checksum_dst = $sth_dst->fetchrow;
	$sth_dst->finish();
## /GET CHECKSUM

	if ( $checksum_src != $checksum_dst ){
		print "[$table] full reload\n";
		## full reload
		my $query_insert = '';
		my @data = ();
		$cnt = 0;

		#### find old records
		### read src data
		$sth_src = $dbh_src->prepare( "select * from $table" ) or do { &err_table( $dbh_src->errstr, $table ); return 0; };
		$sth_src->execute( ) or do { &err_table( $sth_src->errstr, $table ); return 0; };
		while ( my $ref = $sth_src->fetchrow_hashref ){
			### prepare insert query
			unless ( $query_insert ){
				my $fields = '( '; my $values = '( ';
				my ( @fields, @values );
				foreach my $k ( sort keys %{ $ref }){
					push @fields, "`$k`"; push @values, '?';
				}
				$fields .= join( ', ', @fields ); $fields .= ' )';
				$values .= join( ', ', @values ); $values .= ' )';
				$query_insert = "insert into `$table_dst` $fields values $values";
			}
			### /prepare insert query
			push @data, $ref;
			$cnt++;
		}
		$sth_src->finish();
		### /read src data

		$dbh_dst->{'AutoCommit'} = 0;

		### clean
		$sth_dst = $dbh_dst->prepare( "delete from $table_dst" ) or do { &err_table( $dbh_dst->errstr, $table ); return 0; };
		$sth_dst->execute( ) or do { &err_table( $sth_dst->errstr, $table ); return 0; };
		$sth_dst->finish();
		### /clean

		### insert dst
		$sth_dst = $dbh_dst->prepare( $query_insert ) or do { &err_table( "can't prepare: ".$dbh_dst->errstr, $table ); return 0; };
		foreach my $ref ( @data ){
			my @row = ();
			foreach my $k ( sort keys %{ $ref }){
				push @row, $ref->{$k};
			}
			$sth_dst->execute( @row ) or do {
				my $msg = "[rollback] can't execute $query_insert:\n".$sth_dst->errstr;
				$dbh_dst->rollback;
				&err_table( $msg, $table );
				return 0;
			};
		}
		$sth_dst->finish;

		$dbh_dst->commit;
		### /insert dst
		$dbh_dst->{'AutoCommit'} = 1;
	}



	print "$cnt rows from $table have been moved to www\n";
	$status_msg = "$table:$cnt ";
	$status_perf = "${table}_rows=$cnt ";

	$gray{"_${table}_rows"} = $cnt;

###
	&send_graylog( 'info', \%gray );
	&send_monitoring( 0, $status_msg.' | '.$status_perf, $table );
}

$gray{'full_message'} = $status_msg;
$gray{'short_message'} = $gray{'full_message'};
#&send_monitoring( 0, "ok" );

$dbh_src->disconnect;
$dbh_dst->disconnect;


flock( LOCK, LOCK_UN );
close( LOCK );
unlink( $lockfile );


sub send_email {
	my $rcpt = shift;
	my $body = shift;

	my $hostname = hostname;
	if ( $hostname !~ /.+\..+/ ){ $hostname = $hostname . '.forextime-cy.dom'; }
	my $from = "perl\@$hostname";
	my $subj = "[CRON] $0 on $hostname";

	$subj = MIME::Base64::encode($subj,"");
	$subj = "=?utf8?B?".$subj."?=";



	my $msg = MIME::Lite->new (
		From => $from,
		To => $rcpt,
		Subject => $subj,
		Type => 'multipart/mixed',
		Type => 'text/html; charset=utf-8',
		Data => $body,
	);
	$msg->send('smtp','relay.fxtm');
}



sub err_table {
	my $msg = shift;
	my $table = shift;

	print "$msg\n";
	syslog('err', "$msg");
	#&send_email( 'itnotify@forextime.com', $msg );
	&send_email( 'egor.shornikov@forextime.com', $msg );
	&send_graylog( 'error', $msg );
	&send_monitoring( 2, "$msg", $table );

	#next;
}

sub err {
	my $msg = shift;
	my $table = shift;

	print "$msg\n";
	syslog('err', "$msg");
	#&send_email( 'itnotify@forextime.com', $msg );
	&send_email( 'egor.shornikov@forextime.com', $msg );
	&send_graylog( 'error', $msg );
	#&send_monitoring( 2, "$msg" );

	$dbh_src->disconnect();
	$dbh_dst->disconnect();

	flock( LOCK, LOCK_UN );
	close( LOCK );
	unlink($lockfile);

	exit 1;
}

sub err_lock {
	my $msg = shift;
	print "$msg\n";

	syslog('err', "$msg");
	#&send_email( 'itnotify@forextime.com', $msg );
	&send_email( 'egor.shornikov@forextime.com', $msg );
	#&send_monitoring( 1, "$msg" );

	exit 1;
}

sub send_graylog {
	my $level = shift;
	my $ref = shift;

	my $hostname = hostname;
	if ( $hostname !~ /.+\..+/ ){ $hostname = $hostname . '.forextime-cy.dom'; }


	my %levels = (
		"debug" => 7,
		"info"	=> 6,
		"notice"=> 5,
		"warn"	=> 4,
		"error" => 3,
		"fatal" => 2
	);
	my $gelf = {
		"version" => "1.1",
		"host" => $hostname,
		"timestamp" => time(),
		"_pid" => $$,
		"level"=> $levels{$level},
		"file"=> $short_name,
		'facility' => 'sync_copy_trading',
	};

	if( ref( $ref ) eq 'HASH' ){
		foreach my $key ( keys %{ $ref } ){
			$gelf->{$key} = $ref->{ $key };
		}
	} else {
		my $short_message = substr $ref, 0, 150;
		if ( length $short_message == 150 ){ $short_message .= ' ... '; }
		$gelf->{'full_message'} = $ref;
		$gelf->{'short_message'} = $short_message;
	}

	my $gelf_json = encode_json( $gelf );
	my $sock = IO::Socket::INET->new(
		Proto	  => 'udp',
		PeerAddr  => 'graylog1.fxtm',
		PeerPort  => 12201,
	) or &err( "Creating socket: $!\n" );

	my $gzipped_message;
	gzip \$gelf_json =>  \$gzipped_message or &err( "gzip failed: $GzipError\n" );
	print $sock $gzipped_message;
}

sub send_monitoring {
	my $code = shift;
	my $msg = shift;
	my $table = shift;

	#open ( my $nsca, "| /usr/sbin/send_nsca -H 'monitoring.alpari-uk.dom' -c /etc/send_nsca.cfg" ) or &err( $! );
	#open ( my $nsca, "| /usr/sbin/send_nsca -H icinga1.forextime-cy.dmz.dom -c /etc/send_nsca.cfg" ) or &err( $! );
	open ( my $nsca, "| /usr/sbin/send_nsca -c /etc/send_nsca.cfg" ) or &err( $! );
	print $nsca hostname."\tcopy_trading sync $table\t$code\t$msg\n";
	close $nsca;
	return 0;
}


sub print_usage {
	print "usage: \t\n$0 src_host src_db src_user src_pass dst_host dst_db dst_user dst_pass <- for sync all tables\n";
	#foreach my $table ( sort keys %tables ){
		#print "$table|";
	#}
	print "or\n$0 src_host src_db src_user src_pass dst_host dst_db dst_user dst_pass table1 table2 tableN\n";
	exit 0;
}
