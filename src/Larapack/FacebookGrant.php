<?php

namespace Larapack;

use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Event;
use League\OAuth2\Server\Exception;
use League\OAuth2\Server\Util\SecureKey;
use League\OAuth2\Server\Grant\PasswordGrant;
use Illuminate\Support\Facades\Config;
use Facebook\Facebook;
use InvalidArgumentException;

class FacebookGrant extends PasswordGrant
{
    /**
     * Grant identifier
     *
     * @var string
     */
    protected $identifier = 'facebook';

    /**
     * Complete the password grant
     *
     * @return array
     *
     * @throws
     */
    public function completeFlow()
    {
        // Get the required params
        $clientId = $this->server->getRequest()->request->get('client_id', $this->server->getRequest()->getUser());
        if (is_null($clientId)) {
            throw new Exception\InvalidRequestException('client_id');
        }

        $clientSecret = $this->server->getRequest()->request->get('client_secret',
            $this->server->getRequest()->getPassword());
        if (is_null($clientSecret)) {
            throw new Exception\InvalidRequestException('client_secret');
        }

        // Validate client ID and client secret
        $client = $this->server->getClientStorage()->get(
            $clientId,
            $clientSecret,
            null,
            $this->getIdentifier()
        );

        if (($client instanceof ClientEntity) === false) {
            $this->server->getEventEmitter()->emit(new Event\ClientAuthenticationFailedEvent($this->server->getRequest()));
            throw new Exception\InvalidClientException();
        }

        $token = $this->server->getRequest()->request->get('token', null);
        if (is_null($token)) {
            throw new Exception\InvalidRequestException('token');
        }

        // Get facebook user if enabled
        $facebookUser = null;
        if ($this->getGatherUser()) {
            $facebookUser = $this->gatherUser($token);
        }

        // Check if credentials are correct
        $userId = call_user_func($this->getVerifyCredentialsCallback(), $token, $facebookUser);

        if ($userId === false) {
            $this->server->getEventEmitter()->emit(new Event\UserAuthenticationFailedEvent($this->server->getRequest()));
            throw new Exception\InvalidCredentialsException();
        }

        // Validate any scopes that are in the request
        $scopeParam = $this->server->getRequest()->request->get('scope', '');
        $scopes = $this->validateScopes($scopeParam, $client);

        // Create a new session
        $session = new SessionEntity($this->server);
        $session->setOwner('user', $userId);
        $session->associateClient($client);

        // Generate an access token
        $accessToken = new AccessTokenEntity($this->server);
        $accessToken->setId(SecureKey::generate());
        $accessToken->setExpireTime($this->getAccessTokenTTL() + time());

        // Associate scopes with the session and access token
        foreach ($scopes as $scope) {
            $session->associateScope($scope);
        }

        foreach ($session->getScopes() as $scope) {
            $accessToken->associateScope($scope);
        }

        $this->server->getTokenType()->setSession($session);
        $this->server->getTokenType()->setParam('access_token', $accessToken->getId());
        $this->server->getTokenType()->setParam('expires_in', $this->getAccessTokenTTL());

        // Associate a refresh token if set
        if ($this->server->hasGrantType('refresh_token')) {
            $refreshToken = new RefreshTokenEntity($this->server);
            $refreshToken->setId(SecureKey::generate());
            $refreshToken->setExpireTime($this->server->getGrantType('refresh_token')->getRefreshTokenTTL() + time());
            $this->server->getTokenType()->setParam('refresh_token', $refreshToken->getId());
        }

        // Save everything
        $session->save();
        $accessToken->setSession($session);
        $accessToken->save();

        if ($this->server->hasGrantType('refresh_token')) {
            $refreshToken->setAccessToken($accessToken);
            $refreshToken->save();
        }

        return $this->server->getTokenType()->generateResponse();
    }

    /**
     * Return the configuration for gather_user
     *
     * @return boolean
     */
    protected function getGatherUser()
    {
        return Config::get('oauth.grant_types.facebook.gather_user', false);
    }

    /**
     * Return the configuration for gather_user
     *
     * @return boolean
     * 
     * @throws
     */
    protected function gatherUser($token)
    {
        $fb = new Facebook([
		'app_id' => $this->getClientId(),
		'app_secret' => $this->getClientSecret(),
	]);
	
	$me = $fb->get('/me?fields=id', $token);
	
	return $me;
    }

    /**
     * Return the client_secret
     *
     * @return string
     * 
     * @throws
     */
    protected function getClientId()
    {
        $value = Config::get('oauth.grant_types.facebook.client_id', null);
        
        if ($value == null) throw new InvalidArgumentException('[client_id] is not set.');
        
        return $value;
    }

    /**
     * Return the client_secret
     *
     * @return string
     * 
     * @throws
     */
    protected function getClientSecret()
    {
        $value = Config::get('oauth.grant_types.facebook.client_secret', null);
        
        if ($value == null) throw new InvalidArgumentException('[client_secret] is not set.');
        
        return $value;
    }
}
