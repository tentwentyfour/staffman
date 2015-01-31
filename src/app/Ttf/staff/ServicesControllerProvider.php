<?php

namespace Ttf\staff;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ServicesControllerProvider implements ControllerProviderInterface {

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

            $commits_query = 'SELECT
                    committer,
                    revision,
                    committed_on date,
                    repository_id,
                    name,
                    comments,
                    pro.identifier
                FROM
                    changesets ch, repositories rep, projects pro
                WHERE
                    pro.id = project_id
                AND repository_id = rep.id
                ORDER BY committed_on DESC
                LIMIT 5';

            $commits_db = $app['db']->fetchAll(
                $commits_query
            );


            foreach ($commits_db as $key => $commit) {
                foreach ($commit as $key => $value) {
                    if ($key == "committer") {
                        $return = substr($value, 0, (strpos($value, '<')-1));
                    } else {
                        $return = $value;
                    }

                    $commit[$key] = $return;
                }
                $commits[] = $commit;
            }

            $config = array(
                'redminedomain' =>  'https://redmine.example.com',
                'maildomain'    =>  'https://mail.example.com'
            );

            return $app['twig']->render( 'services.twig',
                array(
                    'page'      =>  'services',
                    'staff'     =>  $staff,
                    'commits'   =>  $commits,
                    'config'    =>  $config,
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
