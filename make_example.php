<?php

$p['info']['description.textile'] =<<<TEXTILE
h1. Example Pattern

This does nothing of any importance.
TEXTILE;

$p['elements']['small.misc.form'] = '<txp:body />';
$p['elements']['small.page']      = '<txp:body />';

$p = serialize($p);
$p = gzdeflate($p, 9);
$p = base64_encode( $p );
file_put_contents( 'aaa.pattern', $p );

#eof
