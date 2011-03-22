<?php
function smarty_block_mtsearchentryfield ( $args, $content, &$ctx, &$repeat ) {
    $localvars = array( 'entry', '_entries_counter', 'entries' );
    $blog = $ctx->stash( 'blog' );
    $blog_id = $blog->id;
    if (! isset( $content ) ) {
        $ctx->localize( $localvars );
        $field = $args[ 'field' ];
        $class = $args[ 'class' ];
        $multi = $args[ 'multi' ];
        $query = $args[ 'query' ];
        if (! $class ) $class = 'entry';
        $tb_prefix = 'entry_';
        require_once( 'class.mt_field.php' );
        $_cf = new Field();
        $extra = array( 'limit' => 1 );
        $where = "field_blog_id IN (0, $blog_id) AND field_obj_type = '{$class}' AND field_basename = '{$field}'";
        $cf = $_cf->Find( $where, FALSE, FALSE, $extra );
        if ( is_array( $cf ) ) {
            $cf = $cf[0];
        } else {
            $repeat = FALSE;
            return;
        }
        if (! $query ) {
            $repeat = FALSE;
            return;
        }
        global $customfield_types;
        $col = $customfield_types[ $cf->field_type ][ 'column_def' ];
        if (! $col ) $col = $args[ 'col' ];
        if (! $col ) $col = 'vchar';
        $col = $tb_prefix . 'meta_' . $col;
        $cf_sql = "SELECT * FROM `mt_{$tb_prefix}meta` WHERE `{$tb_prefix}meta_type`='field.{$field}' ";
        $cf_where = '';
        if ( $multi ) {
            $separator = $args[ 'separator' ];
            if (! $separator ) $separator = ',';
            $pre_suffix = $args[ 'pre_suffix' ];
            if (! $pre_suffix ) $pre_suffix = '';
            $and_or = $args[ 'and_or' ];
            if (! $and_or ) $and_or = 'OR';
            $and_or = strtoupper( $and_or );
            $sep = preg_quote( $separator, '/' );
            $params = preg_split( "/$sep/", $query );
            foreach ( $params as $q ) {
                if ( $cf_where ) $cf_where .= " $and_or ";
                $cf_where .= " `$col` LIKE '%" . $pre_suffix . $q . $pre_suffix . "%' ";
            }
            $cf_where = "AND ( $cf_where )";
        } else {
            $cf_where = "AND `$col` LIKE '%" . $query . "%' ";
        }
        $cf_sql .= $cf_where;
        $db = $ctx->mt->db();
        $match_fld = $db->Execute( $cf_sql );
        $match_cnt = $match_fld->RecordCount();
        if (! $match_cnt ) {
            $repeat = FALSE;
            return;
        }
        $match_cnt--;
        $id_col = "{$tb_prefix}meta_{$tb_prefix}id";
        $ids = array();
        for ( $i = 0; $i <= $match_cnt; $i++ ) {
            $match_fld->Move( $i );
            $row = $match_fld->FetchRow();
            $id = $row[ $id_col ];
            array_push( $ids, $id );
        }
        $ids = join( ',', $ids );
        $limit = $args[ 'lastn' ];
        if (! isset( $limit ) ) {
            $limit = $args[ 'limit' ];
        }
        if (! $limit ) $limit = 9999;
        $offset = $args[ 'offset' ];
        if (! $offset ) $offset = 0;
        $sort_by = $args[ 'sort_by' ];
        if (! $sort_by ) $sort_by = 'id';
        $sort_by = $tb_prefix . $sort_by;
        $sort_order = $args[ 'sort_order' ];
        if ( (! $sort_order ) || ( $sort_order == 'descend' ) ) {
            $sort_order = 'DESC';
        } else {
            $sort_order = 'ASC';
        }
        $include_exclude_blogs = __searchentryfield_blogs( $ctx, $args );
        $where = " {$tb_prefix}blog_id{$include_exclude_blogs} AND {$tb_prefix}status=2 AND ";
        $where .= " {$tb_prefix}id in ({$ids}) ";
        $where .= " order by $sort_by $sort_order ";
        $extra = array(
            'limit'  => $limit,
            'offset' => $offset,
            'distinct' => 1,
        );
        require_once 'class.mt_entry.php';
        $_entry = new Entry;
        $entries = $_entry->Find( $where, false, false, $extra );
        $ctx->stash( 'entries', $entries );
        $ctx->stash( '_entries_counter', 0 );
    } else {
        $counter = $ctx->stash( '_entries_counter' );
        $entries = $ctx->stash( 'entries' );
        if ( $counter < count( $entries ) ) {
            $entry = $entries[ $counter ];
            if (! empty( $entry ) ) {
                $ctx->stash( 'entry', $entry );
                $ctx->stash( '_entries_counter', $counter + 1 );
                $count = $counter + 1;
                $ctx->__stash[ 'vars' ][ '__counter__' ] = $count;
                $ctx->__stash[ 'vars' ][ '__odd__' ]     = ( $count % 2 ) == 1;
                $ctx->__stash[ 'vars' ][ '__even__' ]    = ( $count % 2 ) == 0;
                $ctx->__stash[ 'vars' ][ '__first__' ]   = $count == 1;
                $ctx->__stash[ 'vars' ][ '__last__' ]    = ( $count == count( $entries ) );
                $repeat = TRUE;
            } else {
                $ctx->restore( $localvars );
                $repeat = FALSE;
            }
        } else {
            $ctx->restore( $localvars );
            $repeat = FALSE;
        }
    }
    return $content;
}
function __searchentryfield_blogs ( $ctx, $args ) {
    if ( isset( $args[ 'blog_ids' ] ) ||
         isset( $args[ 'include_blogs' ] ) ||
         isset( $args[ 'include_websites' ] ) ) {
        $args[ 'blog_ids' ] and $args[ 'include_blogs' ] = $args[ 'blog_ids' ];
        $args[ 'include_websites' ] and $args[ 'include_blogs' ] = $args[ 'include_websites' ];
        $attr = $args[ 'include_blogs' ];
        unset( $args[ 'blog_ids' ] );
        unset( $args[ 'include_websites' ] );
        $is_excluded = 0;
    } elseif ( isset( $args[ 'exclude_blogs' ] ) ||
               isset( $args[ 'exclude_websites' ] ) ) {
        $attr = $args[ 'exclude_blogs' ];
        $attr or $attr = $args[ 'exclude_websites' ];
        $is_excluded = 1;
    } elseif ( isset( $args[ 'blog_id' ] ) && is_numeric( $args[ 'blog_id' ] ) ) {
        return ' = ' . $args[ 'blog_id' ];
    } else {
        $blog = $ctx->stash( 'blog' );
        if ( isset ( $blog ) ) return ' = ' . $blog->id;
    }
    if ( preg_match( '/-/', $attr ) ) {
        $list = preg_split( '/\s*,\s*/', $attr );
        $attr = '';
        foreach ( $list as $item ) {
            if ( preg_match('/(\d+)-(\d+)/', $item, $matches ) ) {
                for ( $i = $matches[1]; $i <= $matches[2]; $i++ ) {
                    if ( $attr != '' ) $attr .= ',';
                    $attr .= $i;
                }
            } else {
                if ( $attr != '' ) $attr .= ',';
                $attr .= $item;
            }
        }
    }
    $blog_ids = preg_split( '/\s*,\s*/', $attr, -1, PREG_SPLIT_NO_EMPTY );
    $sql = '';
    if ( $is_excluded ) {
        $sql = ' not in ( ' . join( ',', $blog_ids ) . ' )';
    } elseif ( $args[ include_blogs ] == 'all' ) {
        $sql = ' > 0 ';
    } elseif ( ( $args[ include_blogs ] == 'site' )
            || ( $args[ include_blogs ] == 'children' )
            || ( $args[ include_blogs ] == 'siblings' )
    ) {
        $blog = $ctx->stash( 'blog' );
        if (! empty( $blog ) && $blog->class == 'blog' ) {
            require_once( 'class.mt_blog.php' );
            $blog_class = new Blog();
            $blogs = $blog_class->Find( ' blog_parent_id = ' . $blog->parent_id );
            $blog_ids = array();
            foreach ( $blogs as $b ) {
                array_push( $ids, $b->id );
            }
            if ( $args[ 'include_with_website' ] )
                array_push( $blog_ids, $blog->parent_id );
            if ( count( $blog_ids ) ) {
                $sql = ' in ( ' . join( ',', $blog_ids ) . ' ) ';
            } else {
                $sql = ' > 0 ';
            }
        } else {
            $sql = ' > 0 ';
        }
    } else {
        if ( count( $blog_ids ) ) {
            $sql = ' in ( ' . join( ',', $blog_ids ) . ' ) ';
        } else {
            $sql = ' > 0 ';
        }
    }
    return $sql;
}
?>