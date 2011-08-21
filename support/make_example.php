<?php

$p['info']['name']                         = 'Contact Form';
$p['info']['summary']                      = 'Adds a simple contact form to your site.';
$p['info']['version']                      = 'v0.1';
$p['info']['contact']                      = 'netcarver';
$p['info']['url']                          = 'http://github.com/netcarver';
$p['info']['help.textile']                 = file_get_contents('../patterns/contact form/info/description.textile');
$p['elements']['contact_email.misc.form']  = file_get_contents('../patterns/contact form/elements/contact_email.misc.form');
$p['elements']['contact_count.misc.form']  = file_get_contents('../patterns/contact form/elements/contact_count.misc.form');
$p['elements']['contact_thanks.misc.form'] = file_get_contents('../patterns/contact form/elements/contact_thanks.misc.form');
$p['elements']['contact.page']             = file_get_contents('../patterns/contact form/elements/contact.page');
$p['elements']['zem_cr.plugin']            = file_get_contents('../patterns/contact form/elements/zem_cr.plugin');
$p['elements']['zem_cl.plugin']            = file_get_contents('../patterns/contact form/elements/zem_cl.plugin');
$p['elements']['rvm_counter.plugin']       = file_get_contents('../patterns/contact form/elements/rvm_counter.plugin');

$blob = serialize($p);
$blob = gzdeflate($blob, 9);
$blob = base64_encode( $blob );
$blob = chunk_split( $blob, 76 );

$blob =<<<BLOB
# ..........................................................................
# Pattern     : {$p['info']['name']}
# Description : {$p['info']['summary']}
# Version     : {$p['info']['version']}
# Contact     : {$p['info']['contact']}
# URL         : {$p['info']['url']}
# ..........................................................................
#
$blob
BLOB;

unlink( '/home/sed/aaa.pattern' );
file_put_contents( '/home/sed/aaa.pattern', $blob );

#eof
