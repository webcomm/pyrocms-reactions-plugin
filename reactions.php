<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * DON'T BE A DICK PUBLIC LICENSE
 *
 * Version 1, December 2009
 *
 * Copyright (C) 2013 Webcomm Pty Ltd <contact@webcomm.com.au>
 *
 * Everyone is permitted to copy and distribute verbatim or modified
 * copies of this license document, and changing it is allowed as long
 * as the name is changed.
 *
 * DON'T BE A DICK PUBLIC LICENSE
 * TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
 *
 * 1. Do whatever you like with the original work, just don't be a dick.
 *
 *    Being a dick includes - but is not limited to - the following instances:
 *    1a. Outright copyright infringement - Don't just copy this and change the name.
 *    1b. Selling the unmodified original with no work done what-so-ever, that's REALLY being a dick.
 *    1c. Modifying the original work to contain hidden harmful content. That would make you a PROPER dick.
 *
 * 2. If you become rich through modifications, related works/services, or supporting the original work,
 *    share the love. Only a dick would make loads off this work and not buy the original works
 *    creator(s) a pint.
 *
 * 3. Code is provided with no warranty. Using somebody else's code and bitching when it goes wrong makes
 *    you a DONKEY dick. Fix the problem yourself. A non-dick would submit the fix back.
 *
 * @package    Reactions Plugin
 * @version    1.0.0
 * @author     Webcomm Pty Ltd
 * @license    DBAD
 * @copyright  (c) 2013, Webcomm Pty Ltd
 * @link       http://www.webcomm.com.au
 */
 
class Plugin_Reactions extends Plugin
{
    /**
     * Get the FB reactions for the current page.
     *
     * @return  array
     */
    public function facebook()
    {
        // Default URL to current URL
        $url = $this->attribute('url', site_url($this->uri->uri_string()));

        $remote_url = 'http://graph.facebook.com/?'.http_build_query(array('id' => $url));

        $data = array(
            'shares'    => 0,
            'share_url' => 'https://www.facebook.com/sharer/sharer.php?'.http_build_query(array('u' => $url)),
        );

        if ($cached = $this->cached($remote_url) and isset($cached->shares))
        {
            $data['shares'] = $cached->shares;
        }

        return $data;
    }

    public function twitter()
    {
        // Default URL to current URL
        $url = $this->attribute('url', site_url($this->uri->uri_string()));

        $remote_url = 'http://urls.api.twitter.com/1/urls/count.json?'.http_build_query(array('url' => $url));

        $data = array(
            'count'     => 0,
            'tweet_url' => 'http://twitter.com/intent/tweet?'.http_build_query(array('text' => "Checkout {$url}!")),
        );

        if ($cached = $this->cached($remote_url))
        {
            $data['count'] = $cached->count;
        }

        return $data;
    }

    public function pinterest()
    {
        // No default, we need an image
        $url = $this->attribute('url');

        $remote_url = 'http://api.pinterest.com/v1/urls/count.json?'.http_build_query(array(
            'url' => $url,
            'callback' => '',
        ));

        $data = array(
            'count'     => 0,
            'create_url' => 'http://pinterest.com/pin/create/button/?'.http_build_query(array(
                'url'  => $url,
                'text' => "Checkout {$url}!",
            )),
        );

        // Pinterest always wraps the response in a callback. Strip it.
        $cached = $this->cached($remote_url, function($response)
        {
            $response = trim($response, '()');
            return json_decode($response);
        });

        if ($cached)
        {
            $data['count'] = $cached->count;
        }

        return $data;
    }

    protected function cached($url, Closure $callback = null)
    {
        $cache_key = 'reactions-'.md5($url);

        if ( ! $cached = $this->pyrocache->get($cache_key))
        {
            $cached = $this->request($url, $callback);

            $this->pyrocache->write($cached, $cache_key, $this->settings->twitter_cache);
        }

        return $cached;
    }

    protected function request($url, Clsoure $callback = null)
    {
        // Setup CURL
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,

            // Mimick Safari 6 just in case some providers catch on (i.e. Twtiter)
            // [because the API endpoint is not technically allowed]
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/537.43.58 (KHTML, like Gecko) Version/6.1 Safari/537.43.58',
        ));

        // Make request
        $response = curl_exec($ch);

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status != 200) return false;

        return $callback ? $callback($response) : json_decode($response);
    }

}
