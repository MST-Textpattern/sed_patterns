<?php

$p['info']['description.textile'] =<<<TEXTILE
h1. Contact Form

Provides a _Contact Form_ and all required supporting elements.
TEXTILE;

$p['elements']['small.misc.form'] = '';
$p['elements']['small.page']      = '';
$p['elements']['zem_cr.plugin']      = file_get_contents('');
$p['elements']['zem_cl.plugin']      = file_get_contents('');
$p['elements']['rvm_counter.plugin'] = file_get_contents('');

$p = serialize($p);
$p = gzdeflate($p, 9);
$p = base64_encode( $p );
file_put_contents( 'aaa.pattern', $p );

#eof
