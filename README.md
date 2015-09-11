# facebook-grant
A FacebookGrant for the [OAuth 2.0 Bridge](https://github.com/lucadegasperi/oauth2-server-laravel) for [Laravel](https://github.com/laravel/laravel).

## Install
Require this package using composer.
```
composer require larapack/facebook-grant
```

To enable this grant add the following to the `config/oauth2.php` configuration file.
```
'grant_types' => [
    'facebook' => [
        'class' => '\Larapack\FacebookGrant',
        'callback' => '\App\PasswordVerifier@verifyFacebook',
        'access_token_ttl' => 3600
    ]
]
```

Create a class with a verify method where you check if the provided user is a valid one.
```
use App\User;

class PasswordGrantVerifier
{
  public function verifyFacebook($facebookToken)
  {
      // possible get the user_id from facebook using their API and this token

      if ($user = User::where('facebook_token', $facebookToken)->first()) {
          return $user->id;
      }

      return false;
  }
}
```

Next add a sample `client` to the `oauth_clients` table.

Finally set up a route to respond to the incoming access token requests.
```
Route::post('oauth/facebook/access_token', function() {
    return Response::json(Authorizer::issueAccessToken());
});
```
