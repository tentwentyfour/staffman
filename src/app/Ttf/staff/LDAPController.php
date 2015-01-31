<?php

namespace Ttf\staff;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class LDAPController
{

    public static $ldapConn;
    public static $ldapBaseDN = 'ou=People,dc=example,dc=com';

    /*****************************************************************

    You might want to hide some users like the nagios monitoring from
    the team interface, so just add their UIDs to the following array

    *****************************************************************/

    public static $UIDsToIgnore = array ( 'nagmon', 'staff' );

    /**
     * [__construct Set up the LDAP connection and bind to the server]
     * @param string $ldapHost   [The LDAP hostname]
     * @param string $ldapPort   [Tha LDAP port]
     * @param string $ldapDN     [Admin bind DN]
     * @param string $ldapPW     [The password for the admin bind DN]
     * @param string $ldapBaseDN [The base DN to search at]
     */
    function __construct(
        $ldapHost,
        $ldapPort,
        $ldapDN,
        $ldapPW,
        $ldapBaseDN
    ){

        self::$ldapConn = ldap_connect(
            $ldapHost,
            $ldapPort
        );

        ldap_set_option(
            self::$ldapConn,
            LDAP_OPT_PROTOCOL_VERSION,
            3
        );

        if (self::$ldapConn) {
            $ldapBind = ldap_bind(
                self::$ldapConn,
                $ldapDN,
                $ldapPW
            )
            or die ('Could not bind to LDAP server.');
        }
    }

    /**
     * [getUIDs Get all the UIDs]
     * @return array
     */
    static public function getUIDs(){

        $ldapListResult = ldap_list(
            self::$ldapConn,
            self::$ldapBaseDN,
            "uid=*",
            array('uid')
        );

        $ldapEntries = ldap_get_entries(
            self::$ldapConn,
            $ldapListResult
        );

        for ($i=0; $i < $ldapEntries["count"]; $i++){
            $uid = $ldapEntries[$i]["uid"][0];
            if ( !in_array($uid, self::$UIDsToIgnore) ) {
                $UIDs[] = $ldapEntries[$i]["uid"][0];
            }
        }

        return $UIDs;

    }

    /**
     * [createSSHA creates a SSHA hashed string for a given plaintext string]
     * @param  string $plaintext plaintext string to SSHA hash
     * @return string            the SSHA hashed value of the plaintext string
     */
    static public function createSSHA( $plaintext ) {

        $salt = sha1( rand() );
        $salt = substr( $salt, 0, 4 );
        $hash = base64_encode( sha1( $plaintext . $salt, true ) . $salt );
        return "{SSHA}".$hash;

    }

    /**
     * [generatePassword returns a unique SSHA hashed password]
     * @return string The SSHA hashed password
     */
    static public function generatePassword() {

        // create a unique password
        $password = sha1(
            uniqid( rand(), true )
        );

        // limit the new password to 15 characters
        $password = substr( $password, 0, 15 );

        // return the password
        return $password;

    }

    /**
     * [setUserAttributes Set one or more attributes for a given UID]
     * @param string $UID        The user id
     * @param array  $attributes One or more attributes with the associated value
     */

    static public function setUserAttributes( $uid, $attributes = array() ) {

        /*
        example of attributes array:
            $attributes = array(
                'cn' => 'John',
                'sn' => 'Doe'
            );
         */

        // we need to create an array including all the updated fields and their updates.
        unset( $data );

        foreach ( $attributes as $attribute => $value ) {
            // the [0] is needed to replace the value in the LDAP database, LDAP is not limiting any attributes to only one entry
            // $data[$attribute][0] = $value;

            switch ( $attribute ) {
                // The password needs to be SSHA hashed
                case 'userPassword':
                    $data[$attribute] = self::createSSHA($value);
                    break;
                default:
                    $data[$attribute][0] = $value;
            }

        }

        ldap_modify(self::$ldapConn, 'uid='.$uid.','.self::$ldapBaseDN, $data);

        return true;

    }

    /**
     * Adding an attribute to a user
     * @param [type] $uid       the uid of the user to whom we add an attribute
     * @param [type] $attribute the attribute name
     * @param [type] $value     the value of the added attribute
     */
    static public function addUserAttribute( $uid, $attribute, $value ) {

        $data[$attribute] = $value;

        ldap_mod_add(self::$ldapConn, 'uid='.$uid.','.self::$ldapBaseDN, $data);

        return true;

    }

    /**
     * Removing an attribute from a user
     * @param [type] $uid       the uid of the user to whom we remove an attribute
     * @param [type] $attribute the attribute name
     * @param [type] $value     the value of the added attribute
     */
    static public function removeUserAttribute( $uid, $attribute, $value ) {

        $data[$attribute] = $value;

        ldap_mod_del(self::$ldapConn, 'uid='.$uid.','.self::$ldapBaseDN, $data);

        return true;

    }

    /**
     * [getUserAttributes Get one or more attributes from a given UID]
     * @param  string $UID         The user id
     * @param  array  $attributes  One or more attributes
     * @return array  The attributes with their values
     */
    static public function getUserAttributes(
        $UID,
        $attributes = array()
    ){

        $ldapListResult = ldap_list(
            self::$ldapConn,
            self::$ldapBaseDN,
            "uid=$UID",
            $attributes
        );

        $ldapEntries = ldap_get_entries(
            self::$ldapConn,
            $ldapListResult
        );

        $ldapEntries = $ldapEntries[0];

        /**
         * This removes all the count entries that are messing up the returned data from LDAP...
         */

        foreach ($ldapEntries as $key => $value) {
            if (!is_integer($key)  && $key != "count" && $key != "dn") {

                if ( $value["count"] == 1 ) {

                    $ldapentries = $value[0];

                } else {

                    unset($entries);

                    for ( $i = 0; $i < $value["count"]; $i++ ) {
                        $entries[] = $value[$i];
                    }
                    asort($entries);
                    $ldapentries = $entries;

                }
                $ldap[$key] = $ldapentries;
            }
        }

        return $ldap;

    }


}
