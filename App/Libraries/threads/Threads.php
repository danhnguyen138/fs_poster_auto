<?php

namespace FSPoster\App\Libraries\threads;

use Exception;
use FSP\phpseclib3\Crypt\AES;
use FSP\phpseclib3\Crypt\PublicKeyLoader;
use FSP\phpseclib3\Crypt\RSA;
use FSP_GuzzleHttp\Client;
use FSP_GuzzleHttp\Cookie\CookieJar;
use FSP_GuzzleHttp\Cookie\SetCookie;
use FSPoster\App\Providers\Helper;

class Threads
{
    private $username;
    private $pass;
	private $mid = null;
	private $sessionid = null;
	private $userid = null;

    /**
     * @var mixed
     */
    private $proxy;

    /**
     * @param $options array
     */
    public function __construct( $options, $proxy )
    {
        $this->username = $options['username'];
        $this->proxy    = $proxy;

        if( isset( $options['password'] ) )
            $this->pass = $options['password'];

        if( isset( $options[ 'mid' ] ) )
            $this->mid = $options[ 'mid' ];

	    if( isset( $options['sessionid'] ) )
		    $this->sessionid = $options['sessionid'];

	    if( isset( $options['userid'] ) )
		    $this->userid = $options['userid'];
    }

    public function sendPost($sendType, $message, $link, $images)
    {
	    $postURL = 'https://www.threads.net/api/v1/media/configure_text_only_post/';
	    $uploadID = (string)(int)(microtime(true) * 1000);

	    $data = [
		    'caption'               => $message,
		    'publish_mode'          => 'text_post',
		    'text_post_app_info'    => [ 'reply_control' => 0 ],
		    'upload_id'             => $uploadID,
	    ];

        if( $sendType === 'image' )
        {
	        $postURL = 'https://www.threads.net/api/v1/media/configure_text_post_app_feed/';

	        $uploaded = $this->uploadIgPhoto( $uploadID, reset($images) );

	        if( !isset( $uploaded['status'] ) || $uploaded['status'] !== 'ok' )
	        {
		        return [
			        'status'    => 'error',
			        'error_msg' => isset( $uploaded['message'] ) ? $uploaded['message'] : fsp__( 'Failed to upload the image!' )
		        ];
	        }

	        $data['scene_capture_type'] = '';

	        unset($data['publish_mode']);
        }
        else if( $sendType === 'link' )
        {
	        $data['text_post_app_info']['link_attachment_url'] = $link;
        }

	    $data['text_post_app_info'] = json_encode( $data['text_post_app_info'] );

        try
        {
	        $response = $this->guzzleClient()->post( $postURL, [ 'form_params' => $data ] )->getBody()->getContents();
        }
        catch (Exception $e)
        {
	        return [
		        'status' => 'error',
		        'error_msg' => $e->getMessage()
	        ];
        }

        $response = json_decode( $response, true );

        if( isset( $response[ 'media' ][ 'code' ] ) )
        {
            return [
                'status' => 'ok',
                'id'     => $response[ 'media' ][ 'code' ],
                'id2'    => isset( $response[ 'media' ][ 'id' ] ) ? esc_html( $response[ 'media' ][ 'id' ] ) : '?'
            ];
        }

        return [
            'status'    => 'error',
            'error_msg' => isset( $response['message'] ) ? $response['message'] : fsp__( 'Unknown error!' )
        ];
    }

    public function uploadIgPhoto ( $uploadId, $photo )
    {
	    $params = [
		    'is_sidecar'            => '0',
		    'is_threads'            => '1',
		    'media_type'            => '1',
		    'upload_id'             => $uploadId
	    ];

	    $entity_name = sprintf( 'fb_uploader_%d', $uploadId );
	    $endpoint    = 'https://www.threads.net/rupload_igphoto/' . $entity_name;

        try
        {
	        $response = (string) $this->guzzleClient()->post( $endpoint, [
		        'headers' => [
			        'X-Instagram-Rupload-Params'    => json_encode( $params ),
			        'X-Entity-Type'                 => Helper::mimeContentType($photo),
			        'X-Entity-Name'                 => $entity_name,
			        'X-Entity-Length'               => filesize( $photo ),
			        'Offset'                        => '0'
		        ],
		        'body'  => file_get_contents($photo)
	        ] )->getBody();

	        $response = json_decode( $response, true );
        }
        catch ( Exception $e )
        {
            $response = [];

            if( method_exists( $e, 'getResponse' ) && is_object($e->getResponse()) && method_exists( $e->getResponse(), 'getBody' ) )
            {
                $response = json_decode($e->getResponse()->getBody()->getContents(), true);
            }
        }

        return $response;
    }


    public function login()
    {
	    /**
	     * :0: = pass plaintext formada olsun (encrypt olmasin)
	     */
	    $encPass = '#PWD_INSTAGRAM_BROWSER:0:' . time() . ':' . $this->pass;

	    $data = [
		    'enc_password'  => $encPass,
		    'username'      => $this->username
	    ];


        try
        {
	        $resp = $this->guzzleClient()->post( 'https://www.threads.net/api/v1/web/accounts/login/ajax/', [ 'form_params' => $data ] );
        }
        catch ( Exception $e )
        {
            if( ! method_exists( $e, 'getResponse' ) || !is_object($e->getResponse()) )
            {
                return [
                    'status' => false,
                    'error_msg' => fsp__( 'Login failed!' )
                ];
            }

            if( ! method_exists( $e->getResponse(), 'getBody' ) )
            {
                return [
                    'status' => false,
                    'error_msg' => fsp__( 'Login failed!' )
                ];
            }

            $resp = $e->getResponse();
        }

	    if( $this->isLoggedIn( $resp ) )
	    {
		    /*
			if( empty( $respArr['logged_in_user']['text_post_app_joiner_number'] ) )
			{
				return [
					'status' => false,
					'error_msg' => fsp__( 'User have not joined Threads yet!' )
				];
			}
			*/

		    return [
			    'status' => true,
			    'data'   => $this->loggedInUserData( $resp )
		    ];
	    }

	    $respArr = json_decode($resp->getBody()->getContents(), true);

	    if( $this->twoFactorRequired( $resp ) )
	    {
		    return [
			    'status' => true,
			    'data'   => $this->twoFactorData( $resp )
		    ];
	    }

	    return [
		    'status'    => false,
		    'error_msg' => $respArr[ 'message' ] ?? fsp__( 'Login failed!' )
	    ];
    }

    public function doTwoFactorAuth ( $two_factor_identifier, $code, $verification_method = '1' )
    {
        $code = preg_replace( '/\s+/', '', $code );

	    $data = [
		    'identifier'            => $two_factor_identifier,
		    'verificationCode'      => $code,
		    'verification_method'   => $verification_method,
		    'username'              => $this->username
	    ];

        try
        {
	        $resp = $this->guzzleClient()->post( 'https://www.threads.net/api/v1/web/accounts/login/ajax/two_factor/', [ 'form_params' => $data ] );
        }
        catch ( Exception $e )
        {
            if( ! method_exists( $e, 'getResponse' ) || ! is_object($e->getResponse()) )
            {
                return [
                    'status' => false,
                    'error_msg' => fsp__( '2FA failed!' )
                ];
            }

            if( ! method_exists( $e->getResponse(), 'getBody' ) )
            {
                return [
                    'status' => false,
                    'error_msg' => fsp__( '2FA failed!' )
                ];
            }

            $resp = $e->getResponse();
        }

	    if( $this->isLoggedIn( $resp ) )
	    {
		    return [
			    'status' => true,
			    'data'   => $this->loggedInUserData( $resp )
		    ];
	    }

	    $body = json_decode($resp->getBody(), true);

	    return [
		    'status'    => false,
		    'error_msg' => $body[ 'message' ] ?? fsp__( '2FA failed!' )
	    ];
    }

    public function checkAccount()
    {
	    $info = $this->fetchLoggedInUserInfo();

        if( $info !== false )
        {
            return [
                'status'    => false,
                'error_msg' => null
            ];
        }

        return [
            'error'     => TRUE,
            'error_msg' => fsp__( 'The account is disconnected from the plugin. Please add your account to the plugin again without deleting the account from the plugin; as a result, account settings will remain as it is.' )
        ];
    }

	private function isLoggedIn( $response )
	{
		$body = json_decode($response->getBody(), true);

		$status = $body['status'] ?? 'fail';

		if( $status === 'ok' && ($body['authenticated'] ?? false) === true && isset($body['userId']) )
			return true;

		return false;
	}

	private function loggedInUserData( $response )
	{
		$body = json_decode($response->getBody(), true);

		$this->userid = $body['userId'];
		$this->sessionid = $this->getCookieFromResponse( $response, 'sessionid' );
		$fullName = '';
		$profilePic = '';

		$info = $this->fetchLoggedInUserInfo();

		if( $info !== false )
		{
			$fullName = $info['full_name'];
			$profilePic = $info['profile_pic_url'];
		}

		return [
			'name'            => $fullName ?: $this->username,
			'username'        => $this->username,
			'profile_id'      => $this->userid,
			'profile_pic'     => $profilePic,
			'options'         => [
				'username'  => $this->username,
				'userid'    => $this->userid,
				'sessionid' => $this->sessionid,
			]
		];
	}

	private function twoFactorRequired( $response )
	{
		$body = json_decode($response->getBody(), true);

		if( ($body['two_factor_required'] ?? false) === true && isset($body['two_factor_info']) )
			return true;

		return false;
	}

	private function twoFactorData( $response )
	{
		$body = json_decode($response->getBody(), true);

		$verification_method = '1';

		if ( $body['two_factor_info']['whatsapp_two_factor_on'] )
			$verification_method = '6';

		if ( $body['two_factor_info']['totp_two_factor_on'] )
			$verification_method = '3';

		return [
			'needs_challenge' => true,
			'options'         => [
				'password'                  => $this->pass,
				'username'                  => $this->username,
				'mid'                       => $this->getCookieFromResponse( $response, 'mid' ),
				'verification_method'       => $verification_method,
				'two_factor_identifier'     => $body[ 'two_factor_info' ][ 'two_factor_identifier' ],
				'obfuscated_phone_number'   => $body[ 'two_factor_info' ][ 'obfuscated_phone_number' ] ?? ( $body[ 'two_factor_info' ][ 'obfuscated_phone_number_2' ] ?? '' )
			]
		];
	}

	private function fetchLoggedInUserInfo()
	{
		$fullName = '';
		$profilePicUrl = '';

		try
		{
			$userBio = (string) $this->guzzleClient()->get( 'https://www.threads.net/settings/privacy', [
				'allow_redirects' => false,
				'headers' => [ 'Sec-Fetch-Mode' => 'navigate' ]
			] )->getBody();

			preg_match('/\"full_name\":(\"[^\"]*\")/', $userBio, $fName);
			if( isset( $fName[1] ) )
			{
				$fullName = json_decode($fName[1]);
			}

			preg_match('/\"profile_pic_url\":(\"[^\"]*\")/', $userBio, $pic);
			if( isset( $pic[1] ) )
			{
				$profilePicUrl = json_decode($pic[1]);
			}
		}
		catch (Exception $e)
		{
			return false;
		}

		return [
			'full_name'         =>  $fullName,
			'profile_pic_url'   =>  $profilePicUrl,
		];
	}

	private function getCookieFromResponse( $response, $cookieName )
	{
		$headerSetCookies = $response->getHeader('Set-Cookie');

		foreach ( $headerSetCookies as $header )
		{
			$cookie = SetCookie::fromString( $header );

			if( $cookie->getName() === $cookieName )
				return $cookie->getValue();
		}

		return '-';
	}

	private function guzzleClient()
	{
		$csrfToken = uniqid();

		$cookie = 'csrftoken='.$csrfToken.';';

		if( ! empty( $this->userid ) )
		{
			$cookie .= 'ds_user_id=' . $this->userid . ';';
		}

		if( ! empty( $this->sessionid ) )
		{
			$cookie .= 'sessionid=' . $this->sessionid . ';';
		}

		if( ! empty( $this->mid ) )
		{
			$cookie .= 'mid=' . $this->mid . ';';
		}

		return new Client( [
			'verify' => false,
			'headers' => [
				'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0',
				'X-csrftoken'   => $csrfToken,
				'X-IG-APP-ID'   => '238260118697367',
				'Cookie'        => $cookie
			],
			'proxy'   => empty( $this->proxy ) ? null : $this->proxy
		] );
	}

}