<?php

$plugin['name'] = 'sed_patterns';
$plugin['version'] = '0.1';
$plugin['author'] = 'Netcarver';
$plugin['author_uri'] = 'https://github.com/netcarver/sed_patterns';
$plugin['description'] = 'Pattern package import for your site.';
$plugin['type'] = '3';
$plugin['order'] = 5;
$plugin['flags'] = 0;

@include_once('../zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

if( @txpinterface === 'admin' ) 
{
	global $event, $prefs, $txpcfg;
	$files_path = $prefs['file_base_path'];
	$debug = 0;

	class sed_patterns
	{
		const EVENT       = 'sed_patterns';
		#const UI_EVENT    = 'sed_patterns_ui';
		const PRIVS       = '1';
		const PATTERN_EXT = 'pattern';
		const TAB		  = 'Patterns';


		static private function valid_pattern_name( &$s )
		{
			$p = explode( '.', $s );
			$last = array_pop( $p );
			return ($last === self::PATTERN_EXT );
		}

		/**
		 *
		 */
		static private function process_package( $filename )
		{
			$package = file_get_contents( $filename );
			if( false === $package ) {
				self::show( 'Uploaded file could not be opened.' );
				return false;
			}

			$package = @base64_decode( $package );
			if( false === $package ) {
				self::show( 'Uploaded file could not be opened.' );
				return false;
			}

			$package = @gzinflate( $package );
			if( false === $package ) {
				self::show( 'Uploaded file could not be opened.' );
				return false;
			}

			$p = unserialize( $package );
			if( !is_array($p) ) {
				self::show( 'Uploaded file could not be opened.' );
				unset( $p );
				return false;
			}

			if( !$p['info']['description.textile'] ) {
				self::show( 'Uploaded file could not be opened.' );
				unset( $p );
				return false;
			}

			pagetop( self::TAB );

			global $txpcfg;
			include_once $txpcfg['txpath']. DS . 'lib' . DS . 'classTextile.php';
			$txt = new Textile();
			echo $txt->TextileThis( $p['info']['description.textile'] );

			# TODO genarate a log report of all the items added and their initial values...

			dmp( $p );

		}

		static public function import( $event, $step )
		{
			$name = $_FILES['thefile']['name'];
			$disp_name = htmlspecialchars( $name );
			if( is_string($name) && !empty($name) && self::valid_pattern_name($name) ) {
				if( $_FILES['thefile']['error'] === UPLOAD_ERR_OK ) {
					$tmp_name = $_FILES['thefile']['tmp_name'];

					global $prefs;
					$txp_tmpdir = $prefs['tempdir'];

					$file = get_uploaded_file($_FILES['thefile']['tmp_name'], $txp_tmpdir.DS.basename($_FILES['thefile']['tmp_name']));
					if( $file ) {
						$result = self::process_package( $file );
					}
					else
						self::show( 'File upload failed.' );
				}
				else
					self::show( 'Error uploading package ['.$disp_name.']' );
			}
			else
				self::show( 'Please supply a valid patterns package' );
		}

		static public function show($msg = '')
		{
			pagetop( self::TAB, $msg );
			echo<<<HTML
<h1>SED Patterns</h1>
HTML;
			echo upload_form( 'Install Pattern Pack', '', 'import', self::EVENT, '');
		}

		static public function home( $event, $step )
		{
			if( $step && in_array($step, array('import') ))
				self::$step( $event, $step );
			else {
				self::show();
			}
		}
	}

	add_privs( sed_patterns::EVENT,    sed_patterns::PRIVS );
	register_tab('extensions', sed_patterns::EVENT, gTxt( sed_patterns::TAB ) );
	register_callback( 'sed_patterns::home', sed_patterns::EVENT );

	# TODO add an installation+enable handler that will take care of keeping the plugin disabled if gzinflate is not installed.

/*
	#
	#	identify installable files found in the files/ directory...
	#
	include_once $txpcfg['txpath']. DS . 'include' . DS . 'txp_plugin.php';
	$files = array();
	$path = $files_path;
	if( $debug ) echo br , "Auto Install Plugins... Accessing dir($path) ...";
	$dir = @dir( $path );
	if( $dir === false )
	{
		if( $debug ) echo " failed!";
	}
	else
	{
		while( $file = $dir->read() )
		{
			$parts = pathinfo($file);
			$fileaddr = $path.DS.$file;
			if( !is_dir($fileaddr) )
			{
				if( $debug ) echo br , "... found ($file)";
				switch( @$parts['extension'] )
				{
					case 'plugin' :
						$files['plugins'][] = $file;
						if( $debug ) echo " : accepting as a candidate plugin file.";
						break;
					case 'css' :
						$files['css'][] = $file;
						if( $debug ) echo " : accepting as a candidate CSS file.";
						break;
					case 'page' :
						$files['page'][] = $file;
						if( $debug ) echo " : accepting as a candidate Txp page file.";
						break;
					case 'form' :
						$files['form'][] = $file;
						if( $debug ) echo " : accepting as a candidate Txp form file.";
						break;
					default:
						break;
				}
			}
		}
	}
	$dir->close();


	#
	#	Try to auto-install any plugin files found in the files directory...
	#
	$plugin_count = 0;
	if( empty( $files['plugins'] ) )
	{
		if( $debug ) echo " no plugin candidate files found.";
	}
	else
	{
		include_once $txpcfg['txpath'].'/lib/txplib_head.php';
		foreach( $files['plugins'] as $file )
		{
			if( $debug ) echo br , "Processing $file : ";
			#
			#	Load the file into the $_POST['plugin64'] entry and try installing it...
			#
			$plugin = join( '', file($path.DS.$file) );
			$_POST['plugin64'] = $plugin;
			if( $debug ) echo "installing,";
			plugin_install();
			$plugin_count += 1;
			unlink( $path.DS.$file );
		}
	}


	#
	#	Try to install any CSS files found...
	#
	if( empty( $files['css'] ) )
	{
		if( $debug ) echo " no CSS candidates found.";
	}
	else
	{
		foreach( $files['css'] as $file )
		{
			if( $debug ) echo br , "Processing $file : ";
			$content = doSlash( file_get_contents( $path.DS.$file ) );
			$parts = pathinfo($file);
			$name = doSlash( $parts['filename'] );
			safe_upsert( 'txp_css', "`css`='$content'", "`name`='$name'" , $debug );
			unlink( $path.DS.$file );
		}
	}


	#
	#	Try to install any page files found...
	#
	if( empty( $files['page'] ) )
	{
		if( $debug ) echo " no page candidates found.";
	}
	else
	{
		foreach( $files['page'] as $file )
		{
			if( $debug ) echo br , "Processing $file : ";
			$content = doSlash( file_get_contents( $path.DS.$file ) );
			$parts = pathinfo($file);
			$name = doSlash( $parts['filename'] );
			safe_upsert( 'txp_page', "`user_html`='$content'", "`name`='$name'" , $debug );
			unlink( $path.DS.$file );
		}
	}


	#
	#	Try to install any form files found...
	#
	#	Filename format = name.type.form
	#	where type is one of { article, link, file, comment, misc }
	#
	if( empty( $files['form'] ) )
	{
		if( $debug ) echo " no form candidates found.";
	}
	else
	{
		foreach( $files['form'] as $file )
		{
			if( $debug ) echo br , "Processing $file : ";
			$content = doSlash( file_get_contents( $path.DS.$file ) );
			$parts = pathinfo($file);
			$tmp = explode( '.', $parts['filename'] );
			$type = doSlash( array_pop($tmp) );
			$name = doSlash( implode( '.', $tmp ) );

			echo br, "Found form $name of type $type.";
			safe_upsert( 'txp_form', "`Form`='$content', `type`='$type'", "`name`='$name'" , $debug );
			unlink( $path.DS.$file );
		}
	}


	#
	# Process the cleanups.php file...
	#
	$file = $files_path . DS . 'cleanups.php' ;
	if( file_exists( $file ) )
	{
		$cleanups = array();

		#
		# Include the scripted cleanups...
		#
		@include $file;
		if( is_callable( 'sed_cleaner_config' ) )
			$cleanups = sed_cleaner_config();

		if( !empty( $cleanups ) )
		{
			#
			#	Take the scripted actions...
			#
			foreach( $cleanups as $action )
			{
				$p = explode( ' ', $action );
				if( $debug )
					dmp( $p );

				$action = strtolower( array_shift( $p ) );
				$fn = "sed_cleaner_{$action}_action";

				if( is_callable( $fn ) )
				{
					$fn( $p, $debug );
				}
			}
		}
		elseif( $debug )
			echo "<pre>No installation specific cleanups found.\n</pre>";

		unlink( $file );
	}
	elseif( $debug )
		echo "<pre>No installation specific cleanup file found.\n</pre>";
*/


}



#
#   Action handlers for the cleanups.php script follow...
#
function sed_cleaner_addsection_action( $args, $debug )
{
	$section_title = doSlash( array_shift( $args ) );
	$section_name = strtolower(sanitizeForUrl($section_title));

	if( !empty( $args ) ) 
		$page = doSlash( array_shift( $args ) );
	else
		$page = $default['page'];

	if( !empty( $args ) ) 
		$css = doSlash( array_shift( $args ) );
	else
		$css = $default['css'];

	if( !empty( $args ) ) 
		$rss = doSlash( array_shift( $args ) );
	else
		$rss = 0;

	if( !empty( $args ) ) 
		$frontpage = doSlash( array_shift( $args ) );
	else
		$frontpage = 0;

	if( !empty( $args ) ) 
		$searchable = doSlash( array_shift( $args ) );
	else
		$searchable = 0;

	$default = doSlash(safe_row('page, css', 'txp_section', "name = 'default'"));
	if( $debug ) echo " attempting to add a section entitled '$section_title'.";
	safe_insert( 'txp_section',
		"`name` = '$section_name',
		`title` = '$section_title',
		`page`  = '$page',
		`css`   = '$css',
		`is_default` = 0,
		`in_rss` = $rss,
		`on_frontpage` = $frontpage,
		`searchable` = $searchable",
		$debug );
}

function sed_cleaner_enableplugin_action( $args, $debug )
{
	$plugin = doSlash( array_shift( $args ) );
	if( $debug ) echo " attempting to activate $plugin.";
	safe_update( 'txp_plugin', "`status`='1'", "`name`='$plugin'", $debug );
}

function sed_cleaner_disableplugin_action( $args, $debug )
{
	$plugin = doSlash( array_shift( $args ) );
	if( $debug ) echo " attempting to deactivate $plugin.";
	safe_update( 'txp_plugin', "`status`='0'", "`name`='$plugin'", $debug );
}

function sed_cleaner_setpref_action( $args, $debug )
{
	$key = doSlash( array_shift( $args ) );
	$args = join( ' ', $args );
	$args = doSlash( trim( $args, '" ' ) );
	safe_upsert( 'txp_prefs', "`val`='$args'",  "`name`='$key'", $debug );
}

# --- END PLUGIN CODE ---

/*
# --- BEGIN PLUGIN CSS ---
	<style type="text/css">
	div#sed_patterns_help td { vertical-align:top; }
	div#sed_patterns_help code { font-weight:bold; font: 105%/130% "Courier New", courier, monospace; background-color: #FFFFCC;}
	div#sed_patterns_help code.sed_code_tag { font-weight:normal; border:1px dotted #999; background-color: #f0e68c; display:block; margin:10px 10px 20px; padding:10px; }
	div#sed_patterns_help a:link, div#sed_patterns_help a:visited { color: blue; text-decoration: none; border-bottom: 1px solid blue; padding-bottom:1px;}
	div#sed_patterns_help a:hover, div#sed_patterns_help a:active { color: blue; text-decoration: none; border-bottom: 2px solid blue; padding-bottom:1px;}
	div#sed_patterns_help h1 { color: #369; font: 20px Georgia, sans-serif; margin: 0; text-align: center; }
	div#sed_patterns_help h2 { border-bottom: 1px solid black; padding:10px 0 0; color: #369; font: 17px Georgia, sans-serif; }
	div#sed_patterns_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
	div#sed_patterns_help ul ul { font-size:85%; }
	div#sed_patterns_help h3 { color: #693; font: bold 12px Arial, sans-serif; letter-spacing: 1px; margin: 10px 0 0;text-transform: uppercase;}
	</style>
# --- END PLUGIN CSS ---
# --- BEGIN PLUGIN HELP ---
<div id="sed_patterns_help">

h1(#top). SED Patterns.

Allows you to easily import patterns into your site. A Pattern is simply a package containing all of the elements needed to add a feature to your site.

The simplest example of a pattern would be that of a contact form. The pattern would include the zem_contact_reborn, zem_contact_lang and rvm_counter plugins, forms for the email address, HTML form layout and subject counting & a section to pull them all together.

 <span style="float:right"><a href="#top" title="Jump to the top">top</a></span>

</div>
# --- END PLUGIN HELP ---
*/
?>
