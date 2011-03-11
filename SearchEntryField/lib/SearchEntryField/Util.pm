package SearchEntryField::Util;
use strict;
use Exporter;

@SearchEntryField::Util::ISA = qw( Exporter );
use vars qw( @EXPORT_OK );
@EXPORT_OK = qw( include_exclude_blogs );

sub include_exclude_blogs {
    my ( $ctx, $args ) = @_;
    unless ( $args->{ blog_id } || $args->{ include_blogs } || $args->{ exclude_blogs } ) {
        $args->{ include_blogs } = $ctx->stash( 'include_blogs' );
        $args->{ exclude_blogs } = $ctx->stash( 'exclude_blogs' );
        $args->{ blog_ids } = $ctx->stash( 'blog_ids' );
    }
    my ( %blog_terms, %blog_args );
    $ctx->set_blog_load_context( $args, \%blog_terms, \%blog_args ) or return $ctx->error($ctx->errstr);
    my @blog_ids = $blog_terms{ blog_id };
    return undef if ! @blog_ids;
    if ( wantarray ) {
        return @blog_ids;
    } else {
        return \@blog_ids;
    }
}

1;