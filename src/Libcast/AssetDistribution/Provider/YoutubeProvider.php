<?php

/*
 * This file is part of AssetDistribution.
 *
 * (c) Brice Vercoustre <brcvrcstr@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Libcast\AssetDistribution\Provider;

use Libcast\AssetDistribution\Provider\AbstractProvider;
use Libcast\AssetDistribution\Provider\ProviderInterface;

class YoutubeProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->setName('youtube');

        $this->setSettings(array(
            'authorize_url' => 'https://accounts.google.com/o/oauth2/auth',
            'revoke_url'    => 'https://accounts.google.com/o/oauth2/revoke',
            'token_url'     => 'https://accounts.google.com/o/oauth2/token',
            'scope'         => implode(' ', array(
                'https://www.googleapis.com/auth/youtube',
                'https://www.googleapis.com/auth/youtube.readonly',
                'https://www.googleapis.com/auth/youtube.upload',
            )),
            'upload_url'    => 'https://www.googleapis.com/upload/youtube/v3/videos',
            'data_url'      => 'https://www.googleapis.com/youtube/v3/videos',
        ));

        $this->setFieldNamesMap(array(
            'title'         => 'title',
            'description'   => 'description',
            'keywords'      => 'tags',
            'category'      => 'categoryId',
            'shareable'     => 'embeddable',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthorized()
    {
        if (!$this->getCredentials()->getAccessToken()) {
            return false;
        }

        return true;
    }
}
