<?php

namespace Ttf\staff;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonControllerProvider implements ControllerProviderInterface {

    public function connect(Application $app) {

        $ctr = $app['controllers_factory'];

        $ctr->get('/{uid}', function( $uid ) use ( $app) {

            $userData = $app['ldap']::getUserAttributes(
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

            // to define GLOBALLY
            $domain         =   'example.com';
            $keysubdomain   =   'key';

            $uid            =   $userData['uid'];
            $firstname      =   $userData['cn'];
            $lastname       =   $userData['sn'];
            $position       =   $userData['position'];
            $email          =   $userData['mail']; // we need to add a default alias (firstname@example.com) by default
            $sshkey         =   'https://'.$keysubdomain.'.'.$domain.'/'.strtolower($firstname).'.pub';

            $address        =   array(
                                    'street'    =>  $userData['addressstreet'],
                                    'number'    =>  $userData['addressnr'],
                                    'zipcode'   =>  $userData['addresspostcode'],
                                    'city'      =>  $userData['addresscity'],
                                    'country'   =>  $userData['addresscountry'],
                                );

            $aliases        =   $userData['mailalias'];
            $aliases = is_array($aliases) ? $aliases : array($aliases);
            unset($aliases['count']);
            asort($aliases);

            $emailhash = md5($email);

            $person = array(
                'uid'       =>  $uid,
                'firstname' =>  $firstname,
                'lastname'  =>  $lastname,
                'email'     =>  $email,
                'position'  =>  $position,
                'sshkey'    =>  $sshkey,
                'gravatar'  =>  "https://www.gravatar.com/avatar/$emailhash?s=300"
            );

            return $app['twig']->render( 'profile.twig',
                array(
                    'page'      =>  'staff',
                    'person'    =>  $person,
                    'address'   =>  $address,
                    'aliases'   =>  $aliases,
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

        $ctr->post('/{uid}/edit', function( Request $request, $uid ) use ($app) {

            // Check wether the request comes from the same person or if the request comes from a team administrator
            if (($uid == $app['uid']) OR ($app['isStaffAdmin'] == '1')) {

                $app['ldap']::setUserAttributes(
                    $uid,
                    $attributes = array(
                        $request->get('attribute') => $request->get('value')
                    )
                );

            } else {
                error_log( 'no' );
            }

            $result = array(
                'requester' =>  $app['uid'],
                'attribute' =>  $request->get('attribute'),
                'value'     =>  $request->get('value')
            );

            return $app->json( $result );

        });

        $ctr->post('/{uid}/addAttribute', function( Request $request, $uid ) use ($app) {

            $value      =   $request->get('value');
            $attribute  =   $request->get('attribute');

            // Check wether the request comes from the same person or if the request comes from a team administrator
            if ( $app['isStaffAdmin'] == '1' ) {

                $app['ldap']::addUserAttribute(
                    $uid,
                    $attribute,
                    $value
                );

                $result = array(
                    'uid'       =>  $uid,
                    'requester' =>  $app['uid'],
                    'attribute' =>  $attribute,
                    'value'     =>  $value
                );

                return $app->json( $result, 200 );

            } else {

                return new Response("Could not add $value as a(n) $attribute for user $uid", 401);

            }

        });

        $ctr->post('/{uid}/removeAttribute', function( Request $request, $uid ) use ($app) {

            $value      =   $request->get('value');
            $attribute  =   $request->get('attribute');

            // Check wether the request comes from the same person or if the request comes from a team administrator
            if ( $app['isStaffAdmin'] == '1' ) {

                $app['ldap']::removeUserAttribute(
                    $uid,
                    $attribute,
                    $value
                );

                $result = array(
                    'uid'       =>  $uid,
                    'requester' =>  $app['uid'],
                    'attribute' =>  $attribute,
                    'value'  =>  $value
                );

                return $app->json( $result, 200 );

            } else {

                return new Response("Could not remove $value as a(n) $attribute from user $uid", 401);

            }

        });

        $ctr->post('/{uid}/changePassword', function( Request $request, $uid ) use ($app) {

            // we do not need the uid because nobody can change someone else's password,
            // you can only reset other's passwords

            $password   =   $request->get('password');

            $attributes = array(
                'userPassword'  =>  $password
            );

            $app['ldap']::setUserAttributes(
                $app['uid'],
                $attributes
            );

            $userData = $app['ldap']::getUserAttributes(
                $app['uid'],
                array(
                    'sn',
                    'cn',
                    'mail',
                )
            );

            $htmltext   =   '<html>' .
                            '   <head></head>' .
                            '   <body>' .
                            '       Hi '.$userData['cn'].',<br />' .
                            '       your password has been changed successfully.' .
                            '       <br /><br />' .
                            '       Have a nice rest of the day ;)' .
                            '   </body>' .
                            '</html>';


            $email = $userData['mail'];
            $destination[$email] = $userData['cn'] . ' ' . $userData['sn'];

            $message = \Swift_Message::newInstance()
                    ->setSubject( 'Password changed' )
                    ->setFrom( array( 'staff@example.com' => 'Example.com Management' ) )
                    ->setTo( $destination )
                    ->setBody(
                        $htmltext,
                        'text/html' // Mark the content-type as HTML
                    );

            $app['mailer']->send($message);

            return new Response('Password changed', 200);

        });

        $ctr->post('/{uid}/resetPassword', function( Request $request, $uid ) use ($app) {

            $password = $app['ldap']::generatePassword();

            $attributes = array(
                'userPassword'  =>  $password
            );

            $app['ldap']::setUserAttributes(
                $uid,
                $attributes
            );

            $userData = $app['ldap']::getUserAttributes(
                $uid,
                array(
                    'sn',
                    'cn',
                    'extmail',
                )
            );

            $htmltext   =   '<html>' .
                            '   <head></head>' .
                            '   <body>' .
                            '       Hi '.$userData['cn'].',<br />' .
                            '       your password has been reset.' .
                            '       <br /><br />' .
                            '       Your username is: <b>' . $uid . '</b><br />' .
                            '       Your new password is: <b>' . $password . '</b><br /><br />' .
                            '       Have a nice rest of the day ;)' .
                            '   </body>' .
                            '</html>';


            $email = $userData['extmail'];
            $destination[$email] = $userData['cn'] . ' ' . $userData['sn'];

            $message = \Swift_Message::newInstance()
                    ->setSubject( 'Password reset' )
                    ->setFrom( array( 'staff@example.com' => 'Example.com Management' ) )
                    ->setTo( $destination )
                    ->setBody(
                        $htmltext,
                        'text/html' // Mark the content-type as HTML
                    );

            $app['mailer']->send($message);

            return new Response('Password reset', 200);

        });

        return $ctr;

    }

}
