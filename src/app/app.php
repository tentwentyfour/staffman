<?php

require_once __DIR__.'/bootstrap.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;#
use Ttf\staff\LDAPController;

$app = new Silex\Application();

/**
 * Register Service Providers
 */

$app->register( new Silex\Provider\TwigServiceProvider(),
        array(
		'twig.path' => __DIR__.'/views',
	)
);
$app->register( new Silex\Provider\UrlGeneratorServiceProvider());       // required for path in twig

/**
 *  check environment to use the correct configuration
 */

$app->register(new Silex\Provider\SwiftmailerServiceProvider());

$env = getenv('APP_ENV') ?: 'dev';
$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__."/config/$env.json"));

if ($env == 'dev') {
    $app['uid'] = 'developer';
} else {
    $app['uid'] = $_SERVER['PHP_AUTH_USER'];
}

$app['config'] = array (
    'mail_domain'   =>  'example.com'
);


$app->register( new Silex\Provider\DoctrineServiceProvider(),
    array( $app['db.options'] )
);

/**
 * Replace our request strings by their json counterparts if the content-type is application/json.
 */
$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

/**
 * Default route
 */
$app->get('/', function(Silex\Application $app){
    return $app->redirect('/services');
});

/**
 * Mount further Controller Providers
 */
$app->mount( '/staff', new Ttf\staff\StaffControllerProvider() );
$app->mount( '/person', new Ttf\staff\PersonControllerProvider() );
$app->mount( '/services', new Ttf\staff\ServicesControllerProvider() );

$app['ldap'] = new LDAPController(
    $app['ldap']['hostname'],
    $app['ldap']['port'],
    $app['ldap']['bindDN'],
    $app['ldap']['password'],
    $app['ldap']['baseDN']
);

$isTeamAdmin = $app['ldap']::getUserAttributes(
    $app['uid'],
    array(
        'isTeamAdmin'
    )
);
$app['isStaffAdmin'] = $isTeamAdmin['isteamadmin'];

/**
 * Return $app object to index.php
 */
return $app;
