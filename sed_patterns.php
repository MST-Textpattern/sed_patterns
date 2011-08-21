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
	/**
	 * Strings for internationalisation. Adjust as needed for your language...
	 **/
	static $sed_patterns_texts = array(
		'h2.summary'       => 'Summary of Actions',
		'summary'          => 'The following {pattern} elements will be installed&#8230;',
		'existing-changed' => 'Cannot install this copy of {name} [{version}] as the installed copy has edits that you would loose.',
		'existing-newer'   => 'Cannot install this copy of {name} [{version}] as it is older than the installed version [{installed_version}].',
		'existing-same'    => 'Plugin already installed.',
	);

	/**
	 * DO NOT EDIT ANYTHING ELSE!
	 **/

	/**
	 * Install the language strings...
	 **/
	global $textarray;
	foreach( $sed_patterns_texts as $k => $v )
		$textarray['sed_patterns_'.$k] = $v;


	/**
	 * The elements interface enforces a known standard on all element types.
	 *
	 * Elements can be plugins, pages, forms, styles, section, categories, images (public), images (admin), strings or external files (JS, static CSS).
	 **/
	interface sed_patterns_element
	{
		/**
		 * Expands a blobbed element into an unblobbed element. For some element types, this is idempotent.
		 * @return the deblobbed element or return false if deblobbing fails;
		 **/
		static public function deblob( $blobbed_element );	

		/**
		 * Determine if the element can be safely installed. Eg: check plugin doesn't exist or, if it does, has no edits and is not a newer version.
		 * @return true if installation is possible, else a string giving the reason installation is not possible.
		 **/
		static public function can_install( $element, $name );

		/**
		 * Installs the element.
		 * @return true if installation was carried out with no errors, else a string giving the reason why the install failed.
		 * Some installers (plugins) use the blobbed version for installation.
		 **/
		static public function install( $element , $blobbed_element, $name );
	}


	/**
	 * Common base class.
	 **/
	class sed_patterns_common
	{
		static public final function t( $msg, $args = array() )
		{
			return gTxt( 'sed_patterns_'.$msg, $args );
		}

		static public function deblob( $blobbed_element )
		{
			return $blobbed_element;
		}

		static public function can_install( $element, $name )
		{
			return __METHOD__ . ' No way!';
		}

		/**
		 * Installs the element.
		 * @return true if installation was carried out with no errors, else a string giving the reason why the install failed.
		 **/
		static public function install( $element, $blobbed_element, $name )
		{
			return __METHOD__ . ' No way!';
		}
	}


	/**
	 * Class sed_patterns_form
	 **/
	class sed_patterns_form
		extends sed_patterns_common
		implements sed_patterns_element
	{
		static public function can_install( $element, $name )
		{
			$p = explode('.',$name);
			
			$existing = safe_row( '*' , 'txp_form' , "`name`='{$p[0]}' AND `type`='{$p[1]}'" );
			if( empty( $existing ) )
				return true;

			$existing['Form'] = trim( $existing['Form'] );
			if( empty($existing['Form']))
				return true;

			if( $existing['Form'] === trim( $element ) )
				return 'Form already installed.';

			return 'Form already exists and is not empty.';
		}

		/**
		 * Installs the element.
		 * @return true if installation was carried out with no errors, else a string giving the reason why the install failed.
		 **/
		static public function install( $element, $blobbed_element, $name )
		{
			$p = explode('.',$name);
			$type = doSlash( array_pop($p) );
			$name = doSlash( implode( '.', $p ) );
			$form = doSlash( $element );

			safe_upsert( 'txp_form', "`Form`='$form', `type`='$type'", "`name`='$name'" ); # TODO add error reporting
			return true;
		}
	}

	/**
	 * Class sed_patterns_page
	 **/
	class sed_patterns_page
		extends sed_patterns_common
		implements sed_patterns_element
	{
		static public function can_install( $element, $name )
		{
			$name = doSlash($name);
			$existing = safe_row( '*' , 'txp_page' , "`name`='{$name}'" );
			if( empty( $existing ) )
				return true;

			$existing['user_html'] = trim( $existing['user_html'] );
			if( empty($existing['user_html']))
				return true;

			if( $existing['user_html'] === trim( $element ) )
				return 'Page already installed.';

			return 'This page already exists and is not empty.';
		}

		/**
		 * Installs the element.
		 * @return true if installation was carried out with no errors, else a string giving the reason why the install failed.
		 **/
		static public function install( $element, $blobbed_element, $name )
		{
			$name = doSlash($name);
			$page = doSlash($element);

			safe_upsert( 'txp_page', "`user_html`='$page'", "`name`='$name'" ); # TODO add error reporting
			return true;
		}

	}

	/**
	 * 
	 **/
	class sed_patterns_plugin 
		extends sed_patterns_common
		implements sed_patterns_element
	{
		/**
		 *	Takes a compiled plugin blob and decodes it to a normal plugin.
		 */
		static public function deblob( $blobbed_element )
		{
			$plugin = preg_replace('/^#.*$/m', '', $blobbed_element );
			if ($plugin === null)
				return false;

			$plugin = base64_decode($plugin);

			if (strncmp($plugin, "\x1F\x8B", 2) === 0) 
				$plugin = gzinflate(substr($plugin, 10));

			if ($plugin = @unserialize($plugin)) {
				if(!is_array($plugin))
					return false;
			}
			else
				return false;

			return $plugin;
		}


		/**
		 * 	Determines if the given de-blobbed plugin can be installed...
		 *
		 * 	It can be installed if not currently installed OR
		 * 	currently installed version is older && unchanged from original
		 *
		 * 	Return values: true => PROCEED, string => DO NOT INSTALL, string is reason.
		 */
		static public function can_install( $plugin, $name )
		{
			$pname = doSlash( $plugin['name'] );
			$existing = safe_row('*, abs(strcmp(md5(code),code_md5)) as modified','txp_plugin', "`name`='{$pname}'" );
			if( $existing ) {
				if( (bool)$existing['modified'] )	{ # Don't allow mods to plugins to be overwrittern as this may destroy existing site functionality...
					return self::t('existing-changed', array( 
						'{name}'              => htmlspecialchars($plugin['name']), 
						'{version}'           => htmlspecialchars($plugin['version']), 
					));
				}
				elseif( version_compare( $plugin['version'], $existing['version'] ) ) {	# Don't allow downgrading of an installed plugin...
					return self::t('existing-newer', array( 
						'{installed_version}' => htmlspecialchars($existing['version']),
						'{name}'              => htmlspecialchars($plugin['name']), 
						'{version}'           => htmlspecialchars($plugin['version']), 
					));
				}
				elseif( version_compare( $plugin['version'], $existing['version'], '=' ) ) {
					return self::t('existing-same', array(
						'{name}'              => htmlspecialchars($plugin['name']), 
						'{version}'           => htmlspecialchars($plugin['version']), 		
					));
				}
			}

			return true;
		}

		static public function install( $plugin, $blobbed_plugin, $name )
		{
			global $txpcfg, $event;
			include_once $txpcfg['txpath']. DS . 'include' . DS . 'txp_plugin.php';

			$_POST['plugin64'] = $blobbed_plugin;
			$_POST['event'] = 'plugin';

			$current_event = $event;
			
			#
			#	Use a new output buffer to swallow the view that is output by calling plugin_install()
			#
			ob_start();
			plugin_install();
			ob_clean();

			#
			#	Unfortunately, due to Txp's lack of separation of view from model, we loose the result of the install and have to assume it went ok.
			#
			return true;
		}
	}


	/**
	 * 
	 **/
	class sed_patterns
		extends sed_patterns_common
	{
		const EVENT       = 'sed_patterns';
		const PRIVS       = '1';
		const PATTERN_EXT = 'pattern';
		const TAB		  = 'Patterns';


		# =======================================================================================


		static private function valid_pattern_name( &$s )
		{
			$p = explode( '.', $s );
			$last = array_pop( $p );
			return ($last === self::PATTERN_EXT );
		}

		/**
		 * Unpacks a given tmp file and checks if it is a properly structured pattern blob.
		 */
		static public function deblob( $filename )
		{
			$blob = file_get_contents( $filename );
			if( false === $blob ) {
				self::show( 'Uploaded file could not be opened.' );
				return false;
			}

			$blob = preg_replace('/^#.*$/m', '', $blob);
			if( null === $blob ) {
				self::show( 'Uploaded file could not be opened.' );
				return false;
			}
			
			$blob = @base64_decode( $blob );
			if( false === $blob ) {
				self::show( 'Uploaded file could not be opened.' );
				return false;
			}

			$blob = @gzinflate( $blob );
			if( false === $blob ) {
				self::show( 'Uploaded file could not be opened.' );
				return false;
			}

			$p = unserialize( $blob );
			if( !is_array($p) ) {
				self::show( 'Uploaded file could not be opened.' );
				unset( $p );
				return false;
			}

			if( !$p['info']['name'] ) {
				self::show( 'Uploaded file could not be opened.' );
				unset( $p );
				return false;
			}

			return $p;
		}


		static private function action_summary( &$pattern, $blob_location )
		{
			global $txpcfg;
			include_once $txpcfg['txpath']. DS . 'lib' . DS . 'classTextile.php';
			$txt = new Textile();
			echo $txt->TextileRestricted( $pattern['info']['help.textile'], 0, 0 );
			# TODO genarate a log report of all the items added and their initial values...
			echo '<h2>'.self::t('h2.summary').'</h2>'.
				 '<p>' .self::t('summary', array( '{pattern}'=> $pattern['info']['name'])).'</p>';
		}


		/**
		 *	Public entry point for handling the upload event...	
		 */
		static public function upload( $event, $step )
		{
			$name = $_FILES['thefile']['name'];
			$disp_name = htmlspecialchars( $name );

			if( !is_string($name) || empty($name) || !self::valid_pattern_name($name) ) {
				self::show( 'Please upload a valid patterns package' );
				return;
			}

			if( $_FILES['thefile']['error'] !== UPLOAD_ERR_OK ) {
				self::show( 'Error uploading package ['.$disp_name.']' );
				return;
			}

			global $prefs;
			$txp_tmpdir = $prefs['tempdir'];
			$tmp_name   = $_FILES['thefile']['tmp_name'];
			$blob_location =  $txp_tmpdir.DS.basename($_FILES['thefile']['tmp_name']);
			$file = get_uploaded_file($_FILES['thefile']['tmp_name'], $blob_location);

			if( !$file ) {
				self::show( 'File upload failed.' );
				return;
			}

			$pattern = self::deblob( $file );
			if( false === $pattern ) {
				self::show( 'Uploaded file not packaged correctly.' );
				return;
			}

			pagetop( self::TAB );
			self::action_summary( $pattern, $blob_location );
			echo "<div id=\"list_container\" class=\"txp-container txp-list\">
					<table id=\"list\" class=\"list\">
					<caption>Installation Actions...</caption>
					<thead><tr><th>Action</th><th>Result</th><th>Notes&#8230;</th></tr></thead>
					<tbody>";
			foreach( $pattern['elements'] as $name => $blobbed_element ) {
				echo "<tr>";

				$p = explode( '.', $name );
				$type = strtolower(trim(array_pop( $p )));
				$name = implode( '.', $p );
				echo '<td>Installing '.htmlspecialchars($type.' "'.$name).'"</td>';
			
				$deblobber = array("sed_patterns_{$type}", 'deblob');
				$checker   = array("sed_patterns_{$type}", 'can_install');
				$installer = array("sed_patterns_{$type}", 'install');

				if( is_callable($deblobber) && is_callable($checker) && is_callable($installer) ) {
					$element    = call_user_func( $deblobber, $blobbed_element );
					if( false === $element ) {
						echo td('ERROR','','not-ok').td('Could not understand the format of this element','','not-ok')."</tr>\n";
						continue;
					}

					$can_install = call_user_func( $checker, $element, $name );
					if( true !== $can_install ) {
						echo '<td>SKIPPED</td><td>'.htmlspecialchars($can_install)."</td></tr>\n";
						continue;
					}

					$installed = call_user_func( $installer, $element, $blobbed_element, $name );
					if( true !== $installed ) {
						echo td('ERROR','','not-ok').td( htmlspecialchars($installed), '', 'not-ok' )."</tr>\n";
						continue;
					}

					echo td('OK').td('Installed');
				} else
					echo td('ERROR','','not-ok').td('No handler for this type of element','','not-ok');

				echo "</tr>\n";
			}
			echo "</tbody></table></div>\n\n";
			unset( $pattern );
		}


		static private function show($msg = '')
		{
			pagetop( self::TAB, $msg );
			echo '<h1>SED Patterns</h1>';
			echo upload_form( 'Install Pattern Pack', '', 'upload', self::EVENT, '');
		}


		/**
		 *	Public entry point for rendering the default (home) page
		 */
		static public function home( $event, $step )
		{
			if( $step && in_array($step, array('upload') ))
				self::$step( $event, $step );
			else {
				self::show();
			}
		}
	}

	add_privs( sed_patterns::EVENT, sed_patterns::PRIVS );
	register_tab('extensions', sed_patterns::EVENT, gTxt( sed_patterns::TAB ) );
	register_callback( 'sed_patterns::home', sed_patterns::EVENT );

	# TODO add an installation+enable handler that will take care of keeping the plugin disabled if gzinflate is not installed.

/*
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
