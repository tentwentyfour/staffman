<?php

namespace Ttf\staff;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffControllerProvider implements ControllerProviderInterface {

    public function connect(Application $app) {

        $ctr = $app['controllers_factory'];

        $ctr->get('/', function( Application $app) {

            $UIDs = $app['ldap']::getUIDs();

            foreach ($UIDs as $uid) {
                $staff[$uid] = $app['ldap']::getUserAttributes(
                    $uid,
                    array(
                        'uid',
                        'sn',
                        'cn',
                        'mail',
                        'active',
                        'position',
                        'mailalias',
                        'addressNr',
                        'addressStreet',
                        'addressPostcode',
                        'addressCity',
                        'addressCountry'
                    )
                );
            }

            asort($staff);

            return $app['twig']->render( 'staff.twig',
                array(
                    'page'  =>  'staff',
                    'staff' =>  $staff,
                    'user'  =>  $app['ldap']::getUserAttributes(
                        $app['uid'],
                        array(
                            'uid',
                            'cn'
                        )
                    )
                )
            );

        });

        return $ctr;

    }

}
