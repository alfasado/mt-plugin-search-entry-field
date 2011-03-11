package SearchEntryField::Tags;

use strict;

use SearchEntryField::Util qw( include_exclude_blogs );

sub _hdlr_searchentryfield {
    my ( $ctx, $args, $cond ) = @_;
    my $blog = $ctx->stash( 'blog' );
    my $blog_id = $blog->id;
    my $field = $args->{ basename };
    $field = $args->{ field } if (! $field );
    my $class = $args->{ class } || 'entry';
    my $cf = MT->model( 'field' )->load( { basename => $field,
                                           obj_type => $class,
                                           blog_id  => [ 0, $blog_id ] } );
    my $reg = MT->registry( 'customfield_types' );
    my $col = $reg->{ $cf->type }->{ column_def };
    $col = $args->{ col } if (! $col  );
    $col = 'vchar' if (! $col  );
    $col = 'entry_meta_' . $col;
    my $query = $args->{ query };
    return '' unless $field;
    return '' unless $query;
    my $lastn = $args->{ lastn };
    my $limit = $args->{ limit };
    $limit = $lastn if $lastn;
    my $offset = $args->{ offset };
    my $sort_order = $args->{ sort_order };
    $sort_order = 'ascend' unless $sort_order;
    $limit = 9999 unless $limit;
    my $sort_by = $args->{ sort_by };
    $sort_by = 'id' unless $sort_by;
    $offset = 0 unless $offset;
    $field = 'field.' . $field;
    my $multi = $args->{ multi };
    my $where = '';
    if ( $multi ) {
        my $separator = $args->{ separator } || ',';
        my $pre_suffix = $args->{ pre_suffix } || '';
        my $and_or = $args->{ and_or } || 'OR';
        $and_or = uc( $and_or );
        my $sep = quotemeta( $separator );
        my @params = split( /$sep/, $query );
        for my $q ( @params ) {
            $where .= " $and_or " if ( $where );
            $where .= " `$col` LIKE '%" . $pre_suffix . $q . $pre_suffix . "%' ";
        }
        $where = "( $where )";
    } else {
        $where = " `$col` LIKE '%" . $query . "%' ";
    }
    require MT::Object;
    my $driver = MT::Object->driver;
    my $query = "SELECT * FROM `mt_entry_meta` WHERE `entry_meta_type`='$field' AND $where";
    my $dbh = $driver->{ fallback }->{ dbh };
    my $sth = $dbh->prepare( $query );
    return $ctx->error( "Error in query: " . $dbh->errstr ) if $dbh->errstr;
    $sth->execute();
    return $ctx->error( "Error in query: " . $sth->errstr ) if $sth->errstr;
    my $res = '';
    my $counter = 0;
    my @row;
    my @next_row;
    my $columns = $sth->{ NAME_hash };
    my $num_id = $columns->{ entry_meta_entry_id };
    my @ids;
    @next_row = $sth->fetchrow_array();
    while ( @next_row ) {
        @row = @next_row;
        $counter++;
        push( @ids, $row[ $num_id ] );
        @next_row = $sth->fetchrow_array();
    }
    $sth->finish();
    my %terms;
    my %params;
    my @blog_ids = include_exclude_blogs( $ctx, $args );
    if ( scalar @blog_ids ) {
        $terms{ blog_id } = \@blog_ids;
    }
    $terms{ id } = \@ids;
    require MT::Entry;
    $terms{ status } = MT::Entry::RELEASE();
    $params{ limit } = $limit;
    $params{ offset } = $offset;
    $params{ direction } = $sort_order;
    $params{ sort } = $sort_by;
    my @entries = MT->model( $class )->load( \%terms, \%params );
    my $tokens = $ctx->stash( 'tokens' );
    my $builder = $ctx->stash( 'builder' );
    my $i = 0; my $res = '';
    my $odd = 1; my $even = 0;
    for my $entry ( @entries ) {
        local $ctx->{ __stash }{ 'entry' } = $entry;
        local $ctx->{ __stash }{ blog } = $entry->blog;
        local $ctx->{ __stash }{ blog_id } = $entry->blog_id;
        local $ctx->{ __stash }->{ vars }->{ __first__ } = 1 if ( $i == 0 );
        local $ctx->{ __stash }->{ vars }->{ __counter__ } = $i + 1;
        local $ctx->{ __stash }->{ vars }->{ __odd__ } = $odd;
        local $ctx->{ __stash }->{ vars }->{ __even__ } = $even;
        local $ctx->{ __stash }->{ vars }->{ __last__ } = 1 if ( !defined( $entries[ $i + 1 ] ) );
        my $out = $builder->build( $ctx, $tokens, $cond );
        if ( !defined( $out ) ) { return $ctx->error( $builder->errstr ) };
        $res .= $out;
        if ( $odd == 1 ) { $odd = 0 } else { $odd = 1 };
        if ( $even == 1 ) { $even = 0 } else { $even = 1 };
        $i++;
    }
    return $res;
}

1;