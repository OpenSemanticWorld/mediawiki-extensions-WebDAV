<?php

// Bail if PHP is too low
if ( !function_exists( 'version_compare' ) || version_compare( phpversion(), '5.3.2' ) < 0 ) {
	// We need to use dirname( __FILE__ ) here cause __DIR__ is PHP5.3+
	require __DIR__ . '/includes/PHPVersionError.php';
	wfPHPVersionError( 'webdav.php' );
}

// So extensions (and other code) can check whether they're running in WebDAV mode
define( 'WEBDAV', true );

// Initialise common code.
if ( isset( $_SERVER['MW_COMPILED'] ) ) {
	require 'core/includes/WebStart.php';
} else {
	$baseDir = dirname( $_SERVER['SCRIPT_FILENAME'] );
	chdir( $baseDir );
	require $baseDir . '/includes/WebStart.php';
}

use Sabre\DAV;

# \Sabre\DAV\Property\LockDiscovery::$hideLockRoot = true; //This is if Microsoft Office has issues;
#  See http://sabre.io/dav/clients/msoffice/
try {
	$config = \MediaWiki\MediaWikiServices::getInstance()
			->getConfigFactory()->makeConfig( 'wg' );
	$rootNode = $config->get( 'WebDAVRootNode' );
	$server = new DAV\Server( new $rootNode() );
	$server->setBaseUri( $config->get( 'WebDAVBaseUri' ) );

	$plugins = [
		// Make the endpoint browseable using a webbrowser
		'browser' => new DAV\Browser\Plugin(),
		'locks' => new DAV\Locks\Plugin(
			// Make sure users don't overwrite each others changes
			new WebDAVMediaWikiDBLockBackend()
		),
		'temporaryFileFilter' => new WebDAVTempFilePlugin( wfTempDir() )
	];

	\Hooks::run( 'WebDAVPlugins', [ $server, &$plugins ] );
	foreach ( $plugins as $pluginkey => $plugin ) {
		$server->addPlugin( $plugin );
	}

	wfDebugLog(
		'WebDAV',
		'---------------------------------------------------------------------------'
	);
	wfDebugLog(
		'WebDAV',
		'webdav.php: Starting server for user '.RequestContext::getMain()->getUser()->getName()
	);
	wfDebugLog(
		'WebDAV',
		'webdav.php: URL: '.RequestContext::getMain()->getRequest()->getRequestURL()
	);
	wfDebugLog(
		'WebDAV',
		'webdav.php: User agent: '.$_SERVER['HTTP_USER_AGENT']
	);

	$server->exec();
}
catch ( Exception $e ) {
	wfDebugLog(
		'WebDAV',
		'webdav.php: Exception: '.$e->getMessage()
	);
	if ( $e instanceof MWException ) {
		wfDebugLog( 'WebDAV', $e->getText() );
	}
	wfDebugLog( 'WebDAV', var_export( $e->getTraceAsString(), true ) );
	# throw $e;
}

$factory = \MediaWiki\MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
$factory->commitMasterChanges();
$factory->shutdown();
