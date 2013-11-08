<?php

/*
 * This file is part of AssetDistribution.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\AssetDistribution\Provider\Credentials;

use Libcast\AssetDistribution\Provider\Credentials\AbstractCredentials;
use Libcast\AssetDistribution\Provider\Credentials\CredentialsInterface;
use Libcast\AssetDistribution\Request\CurlRequest;
use Libcast\AssetDistribution\Request\HttpRequest;

class YoutubeCredentials extends AbstractCredentials implements CredentialsInterface
{
    const STATUS_APPROVED        = 'approved';

    const STATUS_FIRST_LOGIN     = 'first_login';

    const STATUS_LOGIN_REFRESHED = 'refreshed';

    const STATUS_LOGIN_SESSION   = 'session_login';

    /**
     *
     * @var string API access_token
     */
    protected $access_token;

    /**
     *
     * @var integer Date of the access_token expiration
     */
    protected $token_expiration_date;

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        $provider = $this->getProvider(); /* @var $provider \Libcast\AssetDistribution\Provider\YoutubeProvider */

        switch (true) {
            case $token = $this->getAccessToken():
                // application has been authorized

                $provider->log('Youtube authentication: already logged', $token);

                break;

            case isset($_GET['error']):
                // application has been refused authentication

                $provider->log('Youtube authentication: an error occured', $_GET['error']);

                $this->setStatus(self::STATUS_ERROR);

                return false;

            case isset($_GET['code']):
                // application need to exchange a code against an access_token

                $provider->log('Youtube authentication: auth code received', $_GET['code']);

                $request = new CurlRequest($provider->getLogger());
                $request->setUrl($provider->getSetting('token_url'))
                        ->setArguments(array(
                            'code'          => $_GET['code'],
                            'client_id'     => $provider->getSetting('client_id'),
                            'client_secret' => $provider->getSetting('client_secret'),
                            'redirect_uri'  => $provider->getSetting('redirect_uri'),
                            'grant_type'    => 'authorization_code',
                        ))
                        ->post();

                $token = json_decode($request->getResponse(), true);

                if (isset($token['error'])) {
                    $this->setError($token['error']);

                    return false;
                }

                if (isset($token['state']) && $id = $token['state']) {
                    // use google oAuth `state` attribute to keep track of the
                    // provider Id
                    $provider->setId($id);
                }

                if (isset($token['access_token'])) {
                    $this->setAccessToken(
                            $token['access_token'],
                            isset($token['expires_in']) ? $token['expires_in'] : 3600);
                }

                // in case of a first user login, a refresh_token is returned
                // this one should be saved in a database to auto log user next time
                if (isset($token['refresh_token'])) {
                    $provider->log('YouTube refresh_token', $token['refresh_token']);

                    $this->setRefreshToken($token['refresh_token']);

                    $this->setStatus(self::STATUS_FIRST_LOGIN);
                } else {
                    $this->setStatus(self::STATUS_LOGGED_IN);
                }

                break;

            case $refresh_token = $this->getRefreshToken():
                // use refresh_token to get a new access_token without user login

                $provider->log('Youtube authentication: refresh auth', $refresh_token);

                $request = new CurlRequest($provider->getLogger());
                $request->setUrl($provider->getSetting('token_url'))
                        ->setArguments(array(
                            'client_id'     => $provider->getSetting('client_id'),
                            'client_secret' => $provider->getSetting('client_secret'),
                            'refresh_token' => $refresh_token,
                            'grant_type'    => 'refresh_token',
                        ))
                        ->post();

                $token = json_decode($request->getResponse(), true);

                if (isset($token['error'])) {
                    $this->setError($token['error']);

                    // no break: ask for user authorization again
                } elseif (isset($token['access_token'])) {
                    $this->setAccessToken(
                            $token['access_token'],
                            isset($token['expires_in']) ? $token['expires_in'] : 3600);

                    $this->setStatus(self::STATUS_LOGIN_REFRESHED);

                    break;
                }

            default :
                // redirect user to Google authentication form

                $provider->log('Youtube authentication', array('unauthenticated'));

                $request = new HttpRequest($provider->getLogger());
                $request->setUrl('%s?scope=%s&client_id=%s&redirect_uri=%s&response_type=code&access_type=offline&state=%s')
                        ->setArguments(array(
                            $provider->getSetting('authorize_url'),
                            $provider->getSetting('scope'),
                            $provider->getSetting('client_id'),
                            $provider->getSetting('redirect_uri'),
                            $provider->getId(),
                        ))
                        ->redirect();
        }

        return true;
    }

    // https://developers.google.com/+/web/signin/#revoking_access_tokens_and_disconnecting_the_app
    public function revoke()
    {
        $provider = $this->getProvider();
        if ($token = $this->getAccessToken())
        {
            $request = new CurlRequest($provider->getLogger());
            file_get_contents($provider->getSetting('revoke_url') . '?token=' . $token);
            // $request->setUrl('%s?token=%s')
            //     ->setArguments(array(
            //         $provider->getSetting('revoke_url'),
            //         $token
            //     ))
            //     ->get()
            // ;
            // var_dump(get_class_methods($request));die;

        }
    }

    /**
     * Store Google's oAuth access_token in both the class attribute and a PHP
     * session so that it can be used again later by the component.
     *
     * @param  string   $token       API access_token
     * @param  integer  $expires_in  Number of seconds before token expires
     */
    protected function setAccessToken($token, $expires_in = 3600)
    {
        $provider = $this->getProvider(); /* @var $provider \Libcast\AssetDistribution\Provider\YoutubeProvider */

        $id = $provider->getId();

        $provider->log('New access_token has been created.', array(
            $token,
            $expires_in,
        ));

        $this->access_token = $token;
        $_SESSION["youtube_access_token_$id"] = $token;
        $provider->log("Store token in PHP session as 'youtube_access_token_$id'");

        $expiration = time() + (int) $expires_in;
        $this->token_expiration_date = $expiration;
        $_SESSION["youtube_token_expiration_$id"] = $expiration;
        $provider->log("Store token expiration in PHP session as 'youtube_token_expiration_$id'");
    }

    /**
     * Return the access_token if exists or `null` otherwise.
     *
     * @return string|null API access_token
     */
    public function getAccessToken()
    {
        $provider = $this->getProvider(); /* @var $provider \Libcast\AssetDistribution\Provider\YoutubeProvider */

        $id = $provider->getId();

        $expiration = isset($_SESSION["youtube_token_expiration_$id"]) ?
                $_SESSION["youtube_token_expiration_$id"] :
                $this->token_expiration_date;

        if ($expiration && $expiration <= time()) {
            // token expired

            $provider->log('The token expired.', array(
                $this->access_token,
                date('Y-m-d H:i:s', $expiration),
                date('Y-m-d H:i:s'),
            ));

            // unset token
            $this->access_token = null;
            unset($_SESSION["youtube_access_token_$id"]);

            // unset expiration date
            $this->token_expiration_date = null;
            unset($_SESSION["youtube_token_expiration_$id"]);

            return null;
        }

        switch (true) {
            case $this->access_token:
                $provider->log('Retrieve access token from class attribute');
                $this->setStatus(self::STATUS_LOGGED_IN);
                return $this->access_token;

            case isset($_SESSION["youtube_access_token_$id"]):
                $provider->log("Retrieve access token from PHP session 'youtube_access_token_$id'");
                $this->setStatus(self::STATUS_LOGIN_SESSION);
                return $this->access_token = $_SESSION["youtube_access_token_$id"];

            default :
                $provider->log('Impossible to retrieve access token');
                return null;
        }
    }

    /**
     * Store Google's oAuth refresh_token in both the class attribute and a PHP
     * session so that it can be used again later by the component.
     *
     * @param string $token
     */
    protected function setRefreshToken($token)
    {
        $provider = $this->getProvider(); /* @var $provider \Libcast\AssetDistribution\Provider\YoutubeProvider */

        $id = $provider->getId();

        $provider->setParameter('refresh_token', $token);

        $_SESSION["youtube_refresh_token_$id"] = $token;

        $provider->log("Store refresh token in PHP session as 'youtube_refresh_token_$id'");
    }

    /**
     * Return the access_token if exists or `null` otherwise.
     *
     * @return string|null API refresh_token
     */
    public function getRefreshToken()
    {
        $provider = $this->getProvider(); /* @var $provider \Libcast\AssetDistribution\Provider\YoutubeProvider */

        $id = $provider->getId();

        switch (true) {
            case $provider->hasParameter('refresh_token'):
                $provider->log('Retrieve refresh token from provider parameters');
                return $provider->getParameter('refresh_token');

            case isset($_SESSION["youtube_refresh_token_$id"]):
                $provider->log("Retrieve refresh token from PHP session 'youtube_refresh_token_$id'");
                $provider->setParameter('refresh_token', $_SESSION["youtube_refresh_token_$id"]);
                return $provider->getParameter('refresh_token');

            default :
                $provider->log('Impossible to retrieve refresh token');
                return null;
        }
    }
}
