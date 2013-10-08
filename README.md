PHP AssetDistribution component
===========================

PHP component that uploads and manage asset from remote social/content provider.

Currently handle video for YouTube.
The component could easily handle video for Vimeo, Dailymotion, Metacafé... but also
documents for SlideShare, Scribd, or audio for SoundCloud.

Please feel free to integrate other asset/providers :)

Vocabulary:

  * a `Asset` defines the file (video, audio, document...) and its metadata. By
    itself, the asset is not related to any provider. Therefore, a `Provider` object
    or collection has to be affected to a asset to enable its corresponding
    management.

  * `Provider` are composed of `Credentials` and a `Manager`. Providers also store
    settings (static metadata such as webservice endpoints) and parameters (session
    dependant informations such as authentication token).

    When serialized, provider objects register all parameters (settings should be
    statically described inside their specialized class or coming from a setter
    so they can't be persisted eg. \Libcast\AssetDistribution\Provider\YoutubeProvider).
    This way, `Provider` objects can be persisted with anything but the required data
    for future use.

  * `ProviderCollection` serializable collection of `Provider` objects.

  * `Credentials` objects handle component authentication on its corresponding
    provider on behalf of a user. Users can be redirected on a dedicated login page
    and asked for aproval before any credential are given to the component.

  * `Manager` objects are used from `Asset` to handle the following methods:
    - **save()** uploads the file if the `Asset` is new or persists the asset's
      metadata otherwise
    - **update()** edit the asset from the remote provider
    - **upload()** transfers the file on the remote provider
    - **delete()** removes the asset from the remote provider
    - **find( _$key_ )** search the asset based on a `$key`


Install
-------

Use composer to install the component dependencies :

    git clone https://github.com/bricev/AssetDistribution.git
    cd AssetDistribution
    curl -sS https://getcomposer.org/installer | php
    php composer.phar update


Use it
------

Here is how you set up a provider:
```php
use Libcast\AssetDistribution\Provider\YoutubeProvider;

$youtube = new YoutubeProvider(array(
    'client_id' => '111222333444-a1b2c3d4e5.googleusercontent.com',
    'client_secret' => '1a2b3c4d5e6f7g8h9i0j1k2l',
    'redirect_uri' => 'http://localhost:8888/path/to/oauthcallback',
));

// Alternatively, you can use a '.ini' file to group all your providers configuration
in a single place. Here is how you submit your configuration then:
$youtube = new YoutubeProvider('/path/to/config.ini');
```

Here is how you declare your settings in a *.ini* configuration file:
```php
[youtube]
client_id = "111222333444-a1b2c3d4e5.googleusercontent.com"
client_secret = "1a2b3c4d5e6f7g8h9i0j1k2l"
redirect_uri = "http://localhost:8888/path/to/oauthcallback"
```

You also may want to declare some parameters like a refresh_token to avoid user login
each time you need to manage its asset:
```php
$youtube = new YoutubeProvider('/path/to/config.ini', array(
    'refresh_token' => '1/A1Bc2dE3Fg4h-I5Jk6lM7No8pQ9Rs0tU1Vw2x',
));
```

You can add several providers to a collection (eg. a same video must be sent to many
YouTube accounts):
```php
use Libcast\AssetDistribution\Provider\ProviderCollection;

$youtube1 = new YoutubeProvider(array(
    'client_id' => '111222333444-a1b2c3d4e5.googleusercontent.com',
    'client_secret' => '1a2b3c4d5e6f7g8h9i0j1k2l',
    'redirect_uri' => 'http://localhost:8888/path/to/oauthcallback',
), array(
    'refresh_token' => '1/A1Bc2dE3Fg4h-I5Jk6lM7No8pQ9Rs0tU1Vw2x',
));

$youtube2 = new YoutubeProvider('/path/to/config.ini', array(
    'refresh_token' => '1/I5Jk6lM7No8p-A1Bc2dE3Fg4hQ9Rs0tU1Vw2x',
));

$collection = new ProviderCollection;
$collection[] = $youtube1;
$collection[] = $youtube2;
```

Then all you have to create an `Asset` object on which you must inject either a
`Provider` or a `ProviderCollection` so that you can trigger manager's methods like
**save()** directly from the resulting object:
```php
use Libcast\AssetDistribution\Asset\Asset;

$video = Asset::load('/path/to/a/video.mp4', $collection);
$video->save();
```

You may want to persist a `Provider`, a `ProviderCollection` or an `Asset` to be able
to use them later.

All you have to do is to serialize the object.
* Serialized `Provider` will register all parameters (eg. oAuth `refresh_token`)
* Serialized `ProviderCollection` will list a group of serialized `Provider` objects
* Serialized `Asset` will register all parameters (global and provider specific)

To update an `Asset` from its providers, all you have to do is :

```php
$serializedCollection; // serialized ProviderCollection
$serializedVideo;      // serialized Asset

$video = unserialize($serializedVideo);
$provider = unserialize($serializedCollection);

$video->addProviderCollection($collection)
    ->setParameter('keywords', array('foo', 'bar'))
    ->save();
```


Complete example
----------------

```php
<?php

require '/path/to/vendor/autoload.php';

use Libcast\AssetDistribution\Provider\YoutubeProvider;
use Libcast\AssetDistribution\Asset\Asset;

$youtube = new YoutubeProvider('/path/to/config.ini', array(
    'refresh_token' => '1/I5Jk6lM7No8p-A1Bc2dE3Fg4hQ9Rs0tU1Vw2x',
));

$video = Asset::load('/path/to/a/video.mp4', $youtube);
$video
    ->setParameter('title',       'Title of my awesome video!')
    ->setParameter('description', 'This video is pure awesomeness! xD')
    ->setParameter('keywords',    array('awesome', 'fun'))
    ->setParameter('category',    22)
    ->save();
```

Get credentials manually
------------------------

If you don't want the authentication process to be triggered automatically on
manager's actions like `$video->save()` you can use this trick directly on a
`Provider`:
```php
use Libcast\AssetDistribution\Provider\YoutubeProvider;

$provider = new YoutubeProvider('/path/to/config.ini');
$provider->authenticate();
```

If you have persisted a `Provider` or already may have authenticate in your script,
and need to check if authentication is required again, you can use the
`$provider->isAuthorized()` method.


API and tests
-------------

Generate the API doc:

    php vendor/bin/sami.php update config/sami.php

Run tests

    php vendor/bin/phpunit
