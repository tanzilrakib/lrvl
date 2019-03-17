<?php

namespace App\Http\Controllers\Auth;

use Socialite;
use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use App\UserProvider;
use App\Store;

class LoginShopifyController extends Controller
{

    /**
     * Redirect the user to the GitHub authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider(Request $request)
    {

        $this->validate($request, [
            'domain' => 'string|required'
        ]);

        $config = new \SocialiteProviders\Manager\Config(
            env('SHOPIFY_KEY'),
            env('SHOPIFY_SECRET'),
            env('SHOPIFY_REDIRECT'),
            ['subdomain' => $request->get('domain')]
        );

        return Socialite::with('shopify')
            ->setConfig($config)
            ->scopes(['read_products','write_products'])
            ->redirect();

    }

    /**
     * Obtain the user information from GitHub.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback()
    {

        // Makes a stateless call by not checking if www in present or not, assured by stateless method
        $shopifyUser = Socialite::driver('shopify')->setHttpClient(new \GuzzleHttp\Client(['verify' => false]))->stateless()->user();

        // Create user
        $user = User::firstOrCreate([
            'name' => $shopifyUser->name,
            'email' => $shopifyUser->email,
            'password' => '',
        ]);

        // Store the OAuth Identity
        UserProvider::firstOrCreate([
            'user_id' => $user->id,
            'provider' => 'shopify',
            'provider_user_id' => $shopifyUser->id,
            'provider_token' => $shopifyUser->token,
        ]);

        // Create shop
        $shop = Store::firstOrCreate([
            'name' => $shopifyUser->name,
            'domain' => $shopifyUser->nickname,
        ]);

        // Attach shop to user
        $shop->users()->syncWithoutDetaching([$user->id]);

        // Login with Laravel's Authentication system
        Auth::login($user, true);

        return redirect('/home');

    }

}