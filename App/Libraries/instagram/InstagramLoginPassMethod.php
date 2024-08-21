<?php

namespace FSPoster\App\Libraries\instagram;

use Exception;
use FSP\phpseclib3\Crypt\AES;
use FSP\phpseclib3\Crypt\RSA;
use FSPoster\App\Providers\GuzzleClient;
use FSPoster\App\Providers\Helper;
use RuntimeException;
use FSP_GuzzleHttp\Cookie\CookieJar;
use FSP\phpseclib3\Crypt\PublicKeyLoader;
use stdClass;

class InstagramLoginPassMethod
{
    private $username;
    private $pass;
    private $phone_id;
    private $android_device_id;
    private $device_id;
    private $mid = null;
    private $authorization = '';
    private $user_id = '0';

    const RESUMABLE_UPLOAD = 1;

    const SEGMENTED_UPLOAD = 2;

    private $device = [
        'manufacturer' => 'Xiaomi',
        'model' => 'MI 5s',
        'os_version' => 26,
        'os_release' => '8.0.0'
    ];
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

        if(isset($options['password']))
        {
            $this->pass = $options['password'];
        }

        if( isset( $options[ 'mid' ] ) )
        {
            $this->mid = $options[ 'mid' ];
        }

        if( isset( $options[ 'authorization' ] ) )
        {
            $this->authorization = $options[ 'authorization' ];
        }

        if( isset( $options[ 'instagram_id' ] ) )
        {
            $this->user_id = $options[ 'instagram_id' ];
        }

        if( isset($options[ 'phone_id' ]) )
        {
            $this->phone_id = $options[ 'phone_id' ];
        }
        else
        {
            $this->setPhoneID();
        }

        if( isset($options[ 'device_id' ]) )
        {
            $this->device_id = $options[ 'device_id' ];
        }
        else
        {
            $this->setDeviceID();
        }

        if( isset($options[ 'android_device_id' ]) )
        {
            $this->android_device_id = $options[ 'android_device_id' ];
        }
        else
        {
            $this->setAndroidDeviceID();
        }
    }

    private function getClient ()
    {
        return new GuzzleClient([
            'proxy' => $this->proxy ?: null
        ]);
    }

    private function getDefaultHeaders()
    {
        return [
            "User-Agent" => "Barcelona 289.0.0.77.109 Android (26/8.0.0; 480dpi; 1080x1920; Xiaomi; MI 5s; capricorn; qcom; en_US; 314665256)",
            "Accept-Encoding" => "gzip, deflate",
            "Accept" => "*/*",
            "Connection" => "keep-alive",
            "X-IG-App-Locale" => "en_US",
            "X-IG-Device-Locale" => "en_US",
            "X-IG-Mapped-Locale" => "en_US",
            "X-Pigeon-Session-Id" => "UFS-" . $this->generateUUID() . "-1",
            "X-Pigeon-Rawclienttime" => sprintf('%.3f', microtime(true)),
            "X-IG-Bandwidth-Speed-KBPS" => sprintf('%.3f', mt_rand(2500000, 3000000)/1000),
            "X-IG-Bandwidth-TotalBytes-B" => (string) mt_rand(5000000, 90000000),
            "X-IG-Bandwidth-TotalTime-MS" => (string) mt_rand(2000, 9000),
            "X-IG-App-Startup-Country" => "US",
            "X-Bloks-Version-Id" => "5fd5e6e0f986d7e592743211c2dda24efc502cff541d7a7cfbb69da25b293bf1",
            "X-IG-WWW-Claim" => "0",
            "X-Bloks-Is-Layout-RTL" => "false",
            "X-Bloks-Is-Panorama-Enabled" => "true",
            "X-IG-Device-ID" => $this->getDeviceID(),
            "X-IG-Family-Device-ID" => $this->getPhoneID(),
            "X-IG-Android-ID" => $this->getAndroidDeviceID(),
            "X-IG-Timezone-Offset" => "-14400",
            "X-IG-Connection-Type" => "WIFI",
            "X-IG-Capabilities" => "3brTvx0=",
            "X-IG-App-ID" => "567067343352427",
            "Priority" => "u=3",
            "Accept-Language" => "en-US",
            "X-MID" => $this->mid,
            "Host" => "i.instagram.com",
            "X-FB-HTTP-Engine" => "Liger",
            "X-FB-Client-IP" => "True",
            "X-FB-Server-Cluster" => "True",
            "IG-INTENDED-USER-ID" => $this->user_id,
            "X-IG-Nav-Chain" => "9MV =>self_profile =>2,ProfileMediaTabFragment =>self_profile =>3,9Xf =>self_following =>4",
            "X-IG-SALT-IDS" => (string) mt_rand(1061162222, 1061262222),
            "Authorization" => $this->authorization,
            "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8"
        ];
    }

    public function login()
    {
        $this->prefill();
        $key = $this->sync();

        if( $key === false )
        {
            return [
                'status' => false,
                'error_msg' => fsp__( 'Login failed!' )
            ];
        }

        $encPass = $this->encPass( $this->pass, $key['key_id'], $key['pub_key'] );

        $data = [
            'jazoest' => '22578',
            'country_codes' => [ json_encode([
                'country_code' => '1',
                'source' => ['default']
            ], JSON_UNESCAPED_SLASHES) ],
            'phone_id' => $this->getPhoneID(),
            'enc_password' => $encPass,
            'username' => $this->username,
            'adid' => $this->generateUUID(),
            'guid' => $this->getDeviceID(),
            'device_id' => $this->getAndroidDeviceID(),
            'google_tokens' => '[]',
            'login_attempt_count' => 0,
        ];

        $client = $this->getClient();

        try{
            $resp = $client->post('https://i.instagram.com/api/v1/accounts/login/', [
                'headers' => $this->getDefaultHeaders(),
                'form_params' => [
                    'signed_body' => 'SIGNATURE.' . json_encode($data, JSON_UNESCAPED_SLASHES)
                ],
                'proxy' => empty( $this->proxy ) ? null : $this->proxy,
            ]);

            $respArr = json_decode((string) $resp->getBody(), true);

            if( isset($respArr['logged_in_user']['pk_id']) && !empty($resp->getHeader('ig-set-authorization')[0]) )
            {
                $this->authorization = $resp->getHeader('ig-set-authorization')[0];
                $this->user_id = $respArr['logged_in_user']['pk_id'];

                $this->sendPostLoginFlow();

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
                    'data'   => [
                        'needs_challenge' => false,
                        'name'            => $respArr['logged_in_user']['full_name'],
                        'username'        => $this->username,
                        'profile_id'      => $respArr['logged_in_user']['pk_id'], //$respArr['logged_in_user']['text_post_app_joiner_number'],
                        'profile_pic'     => $respArr['logged_in_user']['profile_pic_url'],
                        'options'         => [
                            'username'          => $this->username,
                            'instagram_id'      => $respArr['logged_in_user']['pk_id'],
                            'mid'               => $this->mid,
                            'authorization'     => $resp->getHeader('ig-set-authorization')[0],
                            'phone_id'          => $this->phone_id,
                            'device_id'         => $this->device_id,
                            'android_device_id' => $this->android_device_id
                        ],
                    ]
                ];
            }
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

        $resp = (string) $resp->getBody();

        $resp = json_decode( $resp, true );

        if( ! isset( $resp[ 'two_factor_info' ] ) )
        {
            return [
                'status' => false,
                'error_msg' => isset($resp['message']) ? $resp['message'] : fsp__( 'Login failed!' )
            ];
        }

        $verification_method = '1';

        /*
        if ($resp['two_factor_info']['sms_two_factor_on'])
        {
            $verification_method = '1';
        }
        */

        if ($resp['two_factor_info']['whatsapp_two_factor_on'])
        {
            $verification_method = '6';
        }

        if ($resp['two_factor_info']['totp_two_factor_on'])
        {
            $verification_method = '3';
        }

        return [
            'status' => true,
            'data'   => [
                'needs_challenge' => true,
                'options'         => [
                    'username'                => $this->username,
                    'instagram_id'            => $resp['two_factor_info']['pk'],
                    'mid'                     => $this->mid,
                    'phone_id'                => $this->phone_id,
                    'device_id'               => $this->device_id,
                    'android_device_id'       => $this->android_device_id,
                    'verification_method'     => $verification_method,
                    'two_factor_identifier'   => $resp['two_factor_info']['two_factor_identifier'],
                    'obfuscated_phone_number' => isset( $resp['two_factor_info']['obfuscated_phone_number'] ) ? $resp['two_factor_info']['obfuscated_phone_number'] : ( isset($resp['two_factor_info']['obfuscated_phone_number_2']) ? $resp['two_factor_info']['obfuscated_phone_number_2'] : '' )
                ],
            ]
        ];
    }

    public function doTwoFactorAuth ( $two_factor_identifier, $code, $verification_method = '1' )
    {
        $code = preg_replace( '/\s+/', '', $code );
        $data = [
            "verification_code" => $code,
            "phone_id" => $this->getPhoneID(),
            "_csrftoken" => $this->generateToken(64),
            "two_factor_identifier" => $two_factor_identifier,
            "username" => $this->username,
            "trust_this_device" => "0",
            "guid" => $this->getDeviceID(),
            "device_id" => $this->getAndroidDeviceID(),
            "waterfall_id" => $this->generateUUID(),
            "verification_method" => $verification_method
        ];

        $client = $this->getClient();

        try{
            $resp = $client->post('https://i.instagram.com/api/v1/accounts/two_factor_login/', [
                'headers' => $this->getDefaultHeaders(),
                'form_params' => [
                    'signed_body' => 'SIGNATURE.' . json_encode($data, JSON_UNESCAPED_SLASHES)
                ],
                'proxy'   => empty( $this->proxy ) ? null : $this->proxy
            ]);
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

        $auth = $resp->getHeader( 'ig-set-authorization' );
        $body = json_decode($resp->getBody(), true);

        if( empty($auth[0]) )
        {
            return [
                'status'    => false,
                'error_msg' => isset($body['message']) ? $body['message'] : ''
            ];
        }

        $this->authorization = $auth[0];

        $data = [
            'name'            => $body['logged_in_user']['full_name'],
            'username'        => $this->username,
            'profile_id'      => $body['logged_in_user']['text_post_app_joiner_number'],
            'profile_pic'     => $body['logged_in_user']['profile_pic_url'],
            'options'         => [
                'username'          => $this->username,
                'instagram_id'      => $body['logged_in_user']['pk_id'],
                'mid'               => $this->mid,
                'authorization'     => $auth[0],
                'phone_id'          => $this->phone_id,
                'device_id'         => $this->device_id,
                'android_device_id' => $this->android_device_id
            ]
        ];

        return [
            'status' => true,
            'data'   => $data
        ];
    }

    public function prefill()
    {
        $client = $this->getClient();

        try{
            $resp = $client->post('https://i.instagram.com/api/v1/accounts/contact_point_prefill/', [
                'headers' => $this->getDefaultHeaders(),
                'post_params' => [
                    'signed_body' => 'SIGNATURE.' . json_encode( [
                            'phone_id' => $this->getPhoneID(),
                            'usage'    => 'prefill'
                        ] )
                ],
                'proxy' => empty( $this->proxy ) ? null : $this->proxy,
            ]);

            if( ! empty($resp->getHeader('ig-set-x-mid')[0]) )
            {
                $this->mid = $resp->getHeader('ig-set-x-mid')[0];
            }
        }
        catch ( Exception $e )
        {
            if( method_exists( $e, 'getResponse' ) && is_object($e->getResponse()) && method_exists( $e->getResponse(), 'getHeader' ) && ! empty($e->getResponse()->getHeader('ig-set-x-mid')[0]) )
            {
                $this->mid = $e->getResponse()->getHeader('ig-set-x-mid')[0];
            }
        }
    }

    private function sync(){
        $client = $this->getClient();

        try{
            $resp = $client->get('https://i.instagram.com/api/v1/qe/sync/', [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1.2 Safari/605.1.15',
                    'Accept-Encoding' => 'gzip,deflate',
                    'Accept' => '*/*',
                    'Connection' => 'Keep-Alive',
                    'Accept-Language' => 'en-US'
                ],
                'cookies' => CookieJar::fromArray([
                    'csrftoken' => $this->generateToken(32),
                    'ig_did' => strtoupper($this->generateUUID()),
                    'ig_nrcb' => '1',
                    'mid' => $this->generateToken(28)
                ], 'i.instagram.com'),
                'proxy' => empty( $this->proxy ) ? null : $this->proxy,
            ]);
        }
        catch ( Exception $e )
        {
            if( ! method_exists( $e, 'getResponse' ) || !is_object($e->getResponse()) )
            {
                return false;
            }

            if( ! method_exists( $e->getResponse(), 'getHeader' ) )
            {
                return false;
            }

            $resp = $e->getResponse();
        }

        foreach ($resp->getHeader('Set-Cookie') as $cookie)
        {
            if(strpos($cookie, 'mid') === 0)
            {
                $mid = explode( ';', $cookie )[0];
                $mid = explode('=', $mid)[1];
                if( ! empty($mid) )
                {
                    $this->mid = $mid;
                }
            }
        }

        if( isset($resp->getHeader('Ig-Set-Password-Encryption-Key-Id')[0], $resp->getHeader('Ig-Set-Password-Encryption-Pub-Key')[0]) )
        {
            return [
                'key_id'  => $resp->getHeader('Ig-Set-Password-Encryption-Key-Id')[0],
                'pub_key' => $resp->getHeader('Ig-Set-Password-Encryption-Pub-Key')[0]
            ];
        }

        return false;
    }

    private function encPass ( $password, $publicKeyId, $publicKey )
    {
        $key  = substr( md5( uniqid( mt_rand() ) ), 0, 32 );
        $iv   = substr( md5( uniqid( mt_rand() ) ), 0, 12 );
        $time = time();

        $rsa          = PublicKeyLoader::loadPublicKey( base64_decode( $publicKey ) );
        $rsa          = $rsa->withPadding( RSA::ENCRYPTION_PKCS1 );
        $encryptedRSA = $rsa->encrypt( $key );

        $aes = new AES( 'gcm' );
        $aes->setNonce( $iv );
        $aes->setKey( $key );
        $aes->setAAD( strval( $time ) );
        $encrypted = $aes->encrypt( $password );

        $payload = base64_encode( "\x01" | pack( 'n', intval( $publicKeyId ) ) . $iv . pack( 's', strlen( $encryptedRSA ) ) . $encryptedRSA . $aes->getTag() . $encrypted );

        return sprintf( '#PWD_INSTAGRAM:4:%s:%s', $time, $payload );
    }

    /**
     * X-IG-Family-Device-ID
     */
    private function setPhoneID()
    {
        $this->phone_id = $this->generateUUID();
    }

    /**
     * X-IG-Family-Device-ID
     */
    private function getPhoneID()
    {
        return $this->phone_id;
    }

    /**
     * X-IG-Adroid-ID
     */
    private function setAndroidDeviceID()
    {
        $this->android_device_id = 'android-' . strtolower($this->generateToken(20));
    }

    /**
     * X-IG-Android-ID
     */
    private function getAndroidDeviceID()
    {
        return $this->android_device_id;
    }

    /**
     * X-IG-Device-ID
     */
    private function setDeviceID()
    {
        $this->device_id = $this->generateUUID();
    }

    /**
     * X-IG-Device-ID
     */
    private function getDeviceID()
    {
        return $this->device_id;
    }

    private function generateUUID ()
    {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000, mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
    }

    private function generateToken( $len = 10 )
    {
        $letters = 'QWERTYUIOPASDFGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm1234567890';

        $token = '';
        mt_srand(time());
        for( $i = 0; $i < $len; $i++ ){
            $token .= $letters[mt_rand()%strlen($letters)];
        }

        return $token;
    }

    private function sendPostLoginFlow ()
    {
    }

    public function checkAccount(){
        $client = $this->getClient();
        try
        {
            $res = $client->post('https://www.threads.net/api/graphql', [
                'headers' => [
                    'Authorization' =>  $this->authorization,
                    'User-Agent' => 'Barcelona 289.0.0.77.109 Android',
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'authority' => 'www.threads.net',
                    'accept' => '*/*',
                    'accept-language' => 'ko',
                    'cache-control' => 'no-cache',
                    'origin' => 'https://www.threads.net',
                    'pragma' => 'no-cache',
                    'Sec-Fetch-Site' => 'same-origin',
                    'x-asbd-id' => '129477',
                    'x-fb-lsd' => 'NjppQDEgONsU_1LCzrmp6q',
                    'x-ig-app-id' => '567067343352427',
                    'referer' => 'https://www.threads.net/@' . $this->username
                ],
                'query' => [
                    'lsd' => 'NjppQDEgONsU_1LCzrmp6q',
                    'variables' => '{"userID":"' . $this->user_id . '"}',
                    'doc_id' => '23996318473300828'
                ],
                'proxy' => empty( $this->proxy ) ? null : $this->proxy
            ])->getBody()->getContents();

            $userInfo = json_decode( $res, true );

            if(isset( $userInfo['data']['userData']['user'] ))
            {
                return [ 'status' => true ];
            }
        }
        catch ( Exception $e )
        {}

        return [
            'status'    => false,
            'error_msg' => fsp__( 'The account is disconnected from the plugin. Please add your account to the plugin again without deleting the account from the plugin; as a result, account settings will remain as it is.' )
        ];
    }

    /* PUBLISH */
	public function uploadPhoto ( $account_id, $photo, $caption, $link = '', $target = 'timeline', $instagramPinThePost = 0 )
	{
		$uploadId = $this->createUploadId();

		$uploadIgPhoto = $this->uploadIgPhoto( $uploadId, $photo );

		$result = $target === 'story' ?  $this->configurePhotoToStory($photo, $uploadId, $link) : $this->configurePhotoToTimeline($photo, $caption, $uploadId);

		if ( isset( $result[ 'status' ] ) && $result[ 'status' ] == 'fail' )
		{
			throw new Exception( ! empty( $result[ 'message' ] ) && is_string( $result[ 'message' ] ) ? esc_html( $result[ 'message' ] ) : 'Error!' );
		}

		if ( $result[ 'media' ][ 'pk' ] && $instagramPinThePost === 1 )
		{
			$this->pinPost( $result[ 'media' ][ 'pk' ] );
		}

		return [
			'status' => 'ok',
			'id'     => isset( $result[ 'media' ][ 'code' ] ) ? esc_html( $result[ 'media' ][ 'code' ] ) : '?',
			'id2'    => isset( $result[ 'media' ][ 'id' ] ) ? esc_html( $result[ 'media' ][ 'id' ] ) : '?'
		];
	}

	public function uploadCarouselItem ( $photo )
	{
		$uploadId = $this->createUploadId();

		$params = [
			'media_type'          => '1',
			'upload_media_height' => (string) $photo[ 'height' ],
			'upload_media_width'  => (string) $photo[ 'width' ],
			'upload_id'           => $uploadId,
		];

		try
		{
            $response = (string) $this->getClient()->post( 'https://www.instagram.com/rupload_igphoto/fb_uploader_' . $uploadId, [
                'headers' => array_merge($this->getDefaultHeaders(), [
                    'X-Instagram-Rupload-Params' => json_encode( $this->reorderByHashCode( $params ) ),
                    'X-Entity-Type'              => 'image/jpeg',
                    'X-Entity-Name'              => 'feed_' . $uploadId,
                    'X-Entity-Length'            => filesize( $photo[ 'path' ] ),
                    'Offset'                     => '0',
                    'Content-Type'               => 'application/octet-stream'
                ]),
                'body'    => fopen( $photo[ 'path' ], 'r' )
            ] )->getBody();
			$result   = json_decode( $response, TRUE );
			if ( $result[ 'status' ] == 'fail' )
			{
				return [
					'status'    => 'error',
					'error_msg' => isset( $result[ 'message' ] ) ? $result[ 'message' ] : fsp__( 'Error' )
				];
			}

			return $result;
		}
		catch ( Exception $e )
		{
			return [
				'status'    => 'error',
				'error_msg' => $e->getMessage()
			];
		}
	}

	public function generateAlbum ( $accountID, $photos, $caption, $instagramPinThePost )
	{
		$body = [
			"caption"                       => $caption,
			"children_metadata"             => [],
			"client_sidecar_id"             => $this->createUploadId(),
			"disable_comments"              => "0",
			"like_and_view_counts_disabled" => FALSE,
			"source_type"                   => "library"
		];

		foreach ( $photos as $photo )
		{
			$response = $this->uploadCarouselItem( $photo );
			if ( $response[ 'status' ] === "ok" )
			{
				$body[ "children_metadata" ][] = [
					"upload_id" => $response[ 'upload_id' ]
				];
			}
			else
			{
				return $response; // when fail
			}
		}

		if ( count( $body[ 'children_metadata' ] ) == 0 )
		{
			return [
				'status'    => 'error',
				'error_msg' => fsp__( 'Error' )
			];
		}

		try
		{
            $response = (string) $this->getClient()->post( "https://i.instagram.com/api/v1/media/configure_sidecar/", [
                'headers' => array_merge($this->getDefaultHeaders(), [
                    'IG-U-DS-USER-ID' => $this->user_id
                ]),
                'form_params'    => [
                    'signed_body' => 'SIGNATURE.' . json_encode($body)
                ]
            ] )->getBody();

			$result = json_decode( $response, TRUE );

			if ( isset( $result[ 'status' ] ) && $result[ 'status' ] == 'fail' )
			{
				return [
					'status'    => 'error',
					'error_msg' => ! empty( $result[ 'message' ] ) && is_string( $result[ 'message' ] ) ? InstagramApi::error( $result[ 'message' ], $accountID ) : InstagramApi::error()
				];
			}

			if ( $result[ 'media' ][ 'pk' ] && $instagramPinThePost === 1 )
			{
				$this->pinPost( $result[ 'media' ][ 'pk' ] );
			}

			return [
				'status' => 'ok',
				'id'     => isset( $result[ 'media' ][ 'code' ] ) ? $result[ 'media' ][ 'code' ] : '?',
				'id2'    => isset( $result[ 'media' ][ 'id' ] ) ? $result[ 'media' ][ 'id' ] : '?'
			];
		}
		catch ( Exception $e )
		{
			return [
				'status'    => 'error',
				'error_msg' => InstagramApi::error( $e->getMessage(), $accountID )
			];
		}
	}

	public function uploadVideo ( $account_id, $video, $caption, $link, $target = 'timeline', $instagramPinThePost = 0 )
	{
		$uploadId = $this->createUploadId();

		$uploadIgVideo  = $this->uploadIgVideo( $uploadId, $video, $target );
		$uploadThumbail = $this->uploadIgPhoto( $uploadId, $video[ 'thumbnail' ] );

		$result = $this->configureVideo( $video, $caption, $uploadId, $link, $target );

		if ( isset( $result[ 'status' ] ) && $result[ 'status' ] == 'fail' )
		{
			throw new Exception( ! empty( $result[ 'message' ] ) && is_string( $result[ 'message' ] ) ? esc_html( $result[ 'message' ] ) : 'Error!' );
		}

		if ( $result[ 'media' ][ 'pk' ] )
		{
			$this->pinPost( $result[ 'media' ][ 'pk' ] );
		}

		return [
			'status' => 'ok',
			'id'     => isset( $result[ 'media' ][ 'code' ] ) ? esc_html( $result[ 'media' ][ 'code' ] ) : '?',
			'id2'    => isset( $result[ 'media' ][ 'id' ] ) ? esc_html( $result[ 'media' ][ 'id' ] ) : '?'
		];
	}

    public function pinPost ( $postID )
    {
        $data = [
            'post_id'    => $postID,
            '_uuid'      => $this->getDeviceID(),
            'device_id'  => $this->getAndroidDeviceID(),
            'radio_type' => 'wifi_none'
        ];

        try
        {
            $response = (string) $this->getClient()->post( 'https://i.instagram.com/api/v1/users/pin_timeline_media/', [
                'headers' => $this->getDefaultHeaders(),
                'form_params' => [
                    'signed_body' => 'SIGNATURE.' . json_encode($data)
                ]
            ] )->getBody();
        }
        catch ( Exception $e )
        {
        }
    }

	private function uploadIgPhoto ( $uploadId, $photo )
	{
		$params = [
			'media_type'          => '1',
			'upload_media_height' => (string) $photo[ 'height' ],
			'upload_media_width'  => (string) $photo[ 'width' ],
			'upload_id'           => $uploadId,
			'image_compression'   => '{"lib_name":"moz","lib_version":"3.1.m","quality":"87"}',
			'xsharing_user_ids'   => '[]',
			'retry_context'       => json_encode( [
				'num_step_auto_retry'   => 0,
				'num_reupload'          => 0,
				'num_step_manual_retry' => 0
			] )
		];

		$entity_name = sprintf( '%s_%d_%d', $uploadId, 0, $this->hashCode( basename( $photo[ 'path' ] ) ) );
		$endpoint    = 'https://i.instagram.com/rupload_igphoto/' . $entity_name;

		try
		{
            $response = (string) $this->getClient()->post( $endpoint, [
                'headers' => array_merge($this->getDefaultHeaders(), [
                    'X_FB_PHOTO_WATERFALL_ID'    => $this->generateUUID(),
                    'X-Instagram-Rupload-Params' => json_encode( $this->reorderByHashCode( $params ) ),
                    'X-Entity-Type'              => 'image/jpeg',
                    'X-Entity-Name'              => $entity_name,
                    'X-Entity-Length'            => filesize( $photo[ 'path' ] ),
                    'Offset'                     => '0',
                    'Content-Type'               => 'application/octet-stream'
                ]),
                'body'    => fopen( $photo[ 'path' ], 'r' )
            ] )->getBody();

			$response = json_decode( $response, TRUE );
		}
		catch ( Exception $e )
		{
			$response = [];
		}

		return $response;
	}

    //configurePhoto
    private function configurePhotoToTimeline ($photo, $caption, $uploadId)
    {
        $date = date('Ymd\THis.000\Z', time());

        $sendData = [
            '_uuid'                     => $this->getDeviceID(),
            'device_id'                 => $this->getAndroidDeviceID(),
            'timezone_offset'           => date('Z'),
            'camera_model'              => $this->device['model'],
            'camera_make'               => $this->device['manufacturer'],
            'scene_type'                => '?',
            'nav_chain'                 => '8rL:self_profile:4,ProfileMediaTabFragment:self_profile:5,UniversalCreationMenuFragment:universal_creation_menu:7,ProfileMediaTabFragment:self_profile:8,MediaCaptureFragment:tabbed_gallery_camera:9,Dd3:photo_filter:10,FollowersShareFragment:metadata_followers_share:11',
            'date_time_original'        => $date,
            'date_time_digitalized'     => $date,
            'creation_logger_session_id'=> $this->generateUUID(),
            'scene_capture_type'        => 'standard',
            'software'                  => 'MI+5s-user+8.0.0+OPR1.170623.032+V10.2.3.0.OAGMIXM+release-keys',
            'multi_sharing'             => '1',
            'location'                  => json_encode(new stdClass()),
            'usertags'                  => json_encode(['in' => []]),
            'edits'                     => [
                'crop_original_size'    => [(float)$photo['width'], (float)$photo['height']],
                'crop_zoom'             => 1.0,
                'crop_center'           => [0.0, -0.0]
            ],
            'extra'                     => [
                'source_width'          => (float) $photo['width'],
                'source_height'         => (float) $photo['height'],
            ],
            'upload_id'                 => $uploadId,
            'device'                    => $this->device,
            'caption'                   => $caption,
            'source_type'               => '4',
            'media_folder'              => 'Camera',
        ];

        try
        {
            $c = $this->getClient();
            $response = (string) $c->post( 'https://i.instagram.com/api/v1/media/configure/', [
                'headers' => array_merge($this->getDefaultHeaders(), [
                    'IG-U-DS-USER-ID' => $this->user_id
                ]),
                'form_params' => [
                    'signed_body' => 'SIGNATURE.' . json_encode($sendData)
                ]
            ] )->getBody();

            $response = json_decode( $response, true );
        }
        catch ( Exception $e )
        {
            $response = [];
        }

        return $response;
    }

    private function configurePhotoToStory ( $photo, $uploadId, $link = '' )
    {
        $tap_models = '}';

        if ( ! empty( $link ) )
        {
            //$link_x = (float) Helper::getOption( 'instagram_story_link_left', '' );
            $link_y = (float) Helper::getOption('instagram_story_link_top', 1000);

            //$link_x = (float) $photo['width'];
            $link_y = $link_y / $photo[ 'height' ];

            try
            {
                $this->getClient()->post( 'https://i.instagram.com/api/v1/media/validate_reel_url/', [
                    'headers'     => array_merge( $this->getDefaultHeaders(), [
                        'IG-U-DS-USER-ID' => $this->user_id
                    ] ),
                    'form_params' => [
                        'signed_body' => 'SIGNATURE.{"url":"' . $link . '","_uid":"' . $this->user_id . '","_uuid":"' . $this->getDeviceID() . '"}'
                    ]
                ] )->getBody();
            }
            catch ( Exception $e )
            {}

            $link_model = '{\"x\":0.5126011,\"y\":' . $link_y . ',\"z\":0,\"width\":0.80998676,\"height\":0.12075,\"rotation\":0.0,\"type\":\"story_link\",\"is_sticker\":true,\"selected_index\":0,\"tap_state\":0,\"link_type\":\"web\",\"url\":\"' . $link . '\",\"tap_state_str_id\":\"link_sticker_default\"}';
        }

        $hashtag = Helper::getOption( 'story_hashtag_text_instagram', '' );
        if ( ! empty( $hashtag ) )
        {
            //$link_x = (float) Helper::getOption( 'instagram_story_link_left', '' );
            $hashtag_y = (float) Helper::getOption( 'instagram_story_hashtag_top', 1000 );

            //$link_x = (float) $photo['width'];
            $hashtag_y     = $hashtag_y / $photo[ 'height' ];
            $hashtag_y     = number_format( $hashtag_y, 2 );
            $hashtag_model = '{\"x\":0.51,\"y\":' . $hashtag_y . ',\"z\":0,\"width\":0.8,\"height\":0.12,\"rotation\":0.0,\"type\":\"hashtag\",\"tag_name\":\"' . $hashtag . '\",\"is_sticker\":true,\"tap_state\":0,\"tap_state_str_id\":\"hashtag_sticker_gradient\"}';
        }

        if ( ! empty( $hashtag_model ) || ! empty( $link_model ) )
        {
            $tap_models = ! empty( $hashtag_model ) && ! empty( $link_model ) ? ( $hashtag_model . ',' . $link_model ) : ( empty( $link_model ) ? $hashtag_model : $link_model );
            $tap_models = ',"tap_models":"[' . $tap_models . ']"}';
        }

        try
        {
            $response = (string) $this->getClient()->post( 'https://i.instagram.com/api/v1/media/configure_to_story/', [
                'headers'     => array_merge($this->getDefaultHeaders(), [
                    'IG-U-DS-USER-ID' => $this->user_id
                ]),
                'form_params' => [
                    'signed_body' => 'SIGNATURE.{"_uuid":"' . $this->getDeviceID() . '","device_id":"' . $this->getAndroidDeviceID() . '","text_metadata":"[{\"font_size\":40.0,\"scale\":1.0,\"width\":611.0,\"height\":169.0,\"x\":0.51414347,\"y\":0.8487708,\"rotation\":0.0}]","supported_capabilities_new":"[{\"name\":+\"SUPPORTED_SDK_VERSIONS\",+\"value\":+\"108.0,109.0,110.0,111.0,112.0,113.0,114.0,115.0,116.0,117.0,118.0,119.0,120.0,121.0,122.0,123.0,124.0,125.0,126.0,127.0\"},+{\"name\":+\"FACE_TRACKER_VERSION\",+\"value\":+\"14\"},+{\"name\":+\"segmentation\",+\"value\":+\"segmentation_enabled\"},+{\"name\":+\"COMPRESSION\",+\"value\":+\"ETC2_COMPRESSION\"},+{\"name\":+\"world_tracker\",+\"value\":+\"world_tracker_enabled\"},+{\"name\":+\"gyroscope\",+\"value\":+\"gyroscope_enabled\"}]","has_original_sound":"1","camera_session_id":"45e0c374-d84f-4289-9f81-a7419752f684","scene_capture_type":"","timezone_offset":"-14400","client_shared_at":"' . ( time() - 5 ) . '","story_sticker_ids":"link_sticker_default","media_folder":"Camera","configure_mode":"1","source_type":"4","creation_surface":"camera","imported_taken_at":1643659109,"capture_type":"normal","rich_text_format_types":"[\"default\"]","upload_id":"' . $uploadId . '","client_timestamp":"' . time() . '","device":{"android_version":26,"android_release":"8.0.0","manufacturer":"Xiaomi","model":"MI+5s"},"_uid":49154269846,"composition_id":"8e56be0b-ba75-44c6-bd61-9fd77680f84a","app_attribution_android_namespace":"","media_transformation_info":"{\"width\":\"720\",\"height\":\"720\",\"x_transform\":\"0\",\"y_transform\":\"0\",\"zoom\":\"1.0\",\"rotation\":\"0.0\",\"background_coverage\":\"0.0\"}","original_media_type":"photo","camera_entry_point":"121","edits":{"crop_original_size":[720.0,720.0],"filter_type":0,"filter_strength":1.0},"extra":{"source_width":720,"source_height":720}' . $tap_models
                ]
            ] )->getBody();

            $response = json_decode( $response, true );
        }
        catch ( Exception $e )
        {
            $response = [];
        }

        return $response;
    }

    private function uploadIgVideo ( $uploadId, $video, $target = 'timeline' )
	{
		$uploadMethod = static::RESUMABLE_UPLOAD;

		if ( $target == 'story' || $video[ 'duration' ] > 10 )
		{
			$uploadMethod = static::SEGMENTED_UPLOAD;
		}

		if ( $uploadMethod === static::RESUMABLE_UPLOAD )
		{
			$response = $this->uploadIgVideoResumableMethod( $uploadId, $video, $target );
		}
		else
		{
			$response = $this->uploadIgVideoSegmentedMethod( $uploadId, $video, $target );
		}

		return $response;
	}

	private function uploadIgVideoResumableMethod ( $uploadId, $video, $target )
	{
		$params = [
			'upload_id'                => $uploadId,
			'retry_context'            => json_encode( [
				'num_step_auto_retry'   => 0,
				'num_reupload'          => 0,
				'num_step_manual_retry' => 0
			] ),
			'xsharing_user_ids'        => '[]',
			'upload_media_height'      => (string) $video[ 'height' ],
			'upload_media_width'       => (string) $video[ 'width' ],
			'upload_media_duration_ms' => (string) $video[ 'duration' ] * 1000,
			'media_type'               => '2',
			'potential_share_types'    => json_encode( [ 'not supported type' ] ),
		];

		if ( $target == 'story' )
		{
			$params[ 'for_album' ] = '1';
		}

		$entity_name = sprintf( '%s_%d_%d', $uploadId, 0, $this->hashCode( basename( $video[ 'path' ] ) ) );

		try
		{
            $response = (string) $this->getClient()->post( 'https://i.instagram.com/rupload_igvideo/' . $entity_name, [
                'headers' => array_merge($this->getDefaultHeaders(), [
                    'X_FB_VIDEO_WATERFALL_ID'    => $this->generateUUID(),
                    'X-Instagram-Rupload-Params' => json_encode( $this->reorderByHashCode( $params ) ),
                    'X-Entity-Type'              => 'video/mp4',
                    'X-Entity-Name'              => $entity_name,
                    'X-Entity-Length'            => filesize( $video[ 'path' ] ),
                    'Offset'                     => '0'
                ]),
                'body'    => fopen( $video[ 'path' ], 'r' )
            ] )->getBody();

			$response = json_decode( $response, TRUE );
		}
		catch ( Exception $e )
		{
			$response = [];
		}

		return $response;
	}

	private function uploadIgVideoSegmentedMethod ( $uploadId, $video, $target )
	{
		$videoSegments = $this->splitVideoSegments( $video, $target );

		$params = [
			'upload_id'                => $uploadId,
			'retry_context'            => json_encode( [
				'num_step_auto_retry'   => 0,
				'num_reupload'          => 0,
				'num_step_manual_retry' => 0
			] ),
			'xsharing_user_ids'        => '[]',
			'upload_media_height'      => (string) $video[ 'height' ],
			'upload_media_width'       => (string) $video[ 'width' ],
			'upload_media_duration_ms' => (string) $video[ 'duration' ] * 1000,
			'media_type'               => '2',
			'potential_share_types'    => json_encode( [ 'not supported type' ] ),
		];

		if ( $target == 'story' )
		{
			$params[ 'for_album' ] = '1';
		}

		try
		{
            $startRequest = $this->getClient()->post( 'https://i.instagram.com/rupload_igvideo/' . $this->generateUUID() . '?segmented=true&phase=start', [
                'headers' => array_merge($this->getDefaultHeaders(), [
                    'X-Instagram-Rupload-Params' => json_encode( $this->reorderByHashCode( $params ) )
                ])
            ] )->getBody();

			$startRequest = json_decode( $startRequest, TRUE );
		}
		catch ( Exception $e )
		{
			throw $e;
		}

		$streamId = $startRequest[ 'stream_id' ];

		$offset      = 0;
		$waterfallId = $this->createUploadId();

		foreach ( $videoSegments as $segment )
		{
			$segmentSize = filesize( $segment );
			$isAudio     = preg_match( '/audio\.mp4$/', $segment );

			$headers = [
				'Segment-Start-Offset'       => $offset,
				'Segment-Type'               => $isAudio ? 1 : 2,
				'Stream-Id'                  => $streamId,
				'X_FB_VIDEO_WATERFALL_ID'    => $waterfallId,
				'X-Instagram-Rupload-Params' => json_encode( $this->reorderByHashCode( $params ) )
			];

			$entity_name = md5( $segment ) . '-0-' . $segmentSize;

			try
			{
                $getOffset = $this->getClient()->get( 'https://i.instagram.com/rupload_igvideo/' . $entity_name . '?segmented=true&phase=transfer', [
                    'headers' => array_merge($this->getDefaultHeaders(), $headers)
                ] )->getBody();

				$getOffset = json_decode( $getOffset, TRUE );

				$headers[ 'X-Entity-Type' ]   = 'video/mp4';
				$headers[ 'X-Entity-Name' ]   = $entity_name;
				$headers[ 'X-Entity-Length' ] = $segmentSize;
				$headers[ 'Offset' ]          = isset( $getOffset[ 'offset' ] ) ? (int) $getOffset[ 'offset' ] : 0;

                $this->getClient()->post('https://i.instagram.com/rupload_igvideo/' . $entity_name . '?segmented=true&phase=transfer', [
                    'headers' => array_merge($this->getDefaultHeaders(), $headers),
                    'body'    => fopen( $segment, 'r' ),
                ] )->getBody();
			}
			catch ( Exception $e )
			{
				throw $e;
			}

			$offset += $segmentSize;
		}

		try
		{
            $startRequest = $this->getClient()->post('https://i.instagram.com/rupload_igvideo/' . $this->generateUUID() . '?segmented=true&phase=end', [
                'headers' => array_merge($this->getDefaultHeaders(), [
                    'Stream-Id'                  => $streamId,
                    'X-Instagram-Rupload-Params' => json_encode( $this->reorderByHashCode( $params ) )
                ])
            ] )->getBody();

			$startRequest = json_decode( $startRequest, TRUE );
		}
		catch ( Exception $e )
		{
			throw $e;
		}

		return [];
	}

	private function splitVideoSegments ( $video, $target )
	{
		$segmentTime = $target == 'story' ? 2 : 5;
		$segmentId   = md5( $video[ 'path' ] );

		$segmentsPath         = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fs_' . $segmentId . '_%03d.mp4';
		$segmentsPathForAudio = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fs_' . $segmentId . '_audio.mp4';

		$ffmpeg = FFmpeg::factory();

		try
		{
			$ffmpeg->run( sprintf( '-i %s -c:v copy -an -dn -sn -f segment -segment_time %d -segment_format mp4 %s', FFmpeg::escape( $video[ 'path' ] ), $segmentTime, FFmpeg::escape( $segmentsPath ) ) );

			if ( $video[ 'audio_codec' ] !== NULL )
			{
				$ffmpeg->run( sprintf( '-i %s -c:a copy -vn -dn -sn -f mp4 %s', FFmpeg::escape( $video[ 'path' ] ), FFmpeg::escape( $segmentsPathForAudio ) ) );
			}
		}
		catch ( RuntimeException $e )
		{
			// Find segments for removing them after finish
			$this->findSegments( $segmentId );
			throw $e;
		}

		return $this->findSegments( $segmentId );
	}

	private function findSegments ( $segmentId )
	{
		$segmentsPath      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fs_' . $segmentId . '_*.mp4';
		$segmentsPathAudio = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fs_' . $segmentId . '_audio.mp4';

		$result = glob( $segmentsPath );

		if ( is_file( $segmentsPathAudio ) )
		{
			$result[] = $segmentsPathAudio;
		}

		foreach ( $result as $file_path )
		{
			InstagramApi::moveToTrash( $file_path );
		}

		return $result;
	}

    private function configureVideo ( $video, $caption, $uploadId, $link = '', $target = 'timeline' )
    {
        $sendData = [
            'supported_capabilities_new' => json_encode( [
                [
                    'name'  => 'SUPPORTED_SDK_VERSIONS',
                    'value' => '13.0,14.0,15.0,16.0,17.0,18.0,19.0,20.0,21.0,22.0,23.0,24.0,25.0,26.0,27.0,28.0,29.0,30.0,31.0,32.0,33.0,34.0,35.0,36.0,37.0,38.0,39.0,40.0,41.0,42.0,43.0,44.0,45.0,46.0,47.0,48.0,49.0,50.0,51.0,52.0,53.0,54.0,55.0,56.0,57.0,58.0,59.0,60.0,61.0,62.0,63.0,64.0,65.0,66.0,67.0,68.0,69.0'
                ],
                [ 'name' => 'FACE_TRACKER_VERSION', 'value' => '12' ],
                [ 'name' => 'segmentation', 'value' => 'segmentation_enabled' ],
                [ 'name' => 'COMPRESSION', 'value' => 'ETC2_COMPRESSION' ],
                [ 'name' => 'world_tracker', 'value' => 'world_tracker_enabled' ],
                [ 'name' => 'gyroscope', 'value' => 'gyroscope_enabled' ]
            ] ),
            'video_result'               => '',
            'upload_id'                  => $uploadId,
            'poster_frame_index'         => 0,
            'length'                     => round( $video[ 'duration' ], 1 ),
            'audio_muted'                => false,
            'filter_type'                => 0,
            'source_type'                => 4,
            'device'                     => $this->device,
            'extra'                      => [
                'source_width'  => $video[ 'width' ],
                'source_height' => $video[ 'height' ],
            ],
            '_csrftoken'                 => $this->generateToken(32),
            '_uid'                       => $this->user_id,
            '_uuid'                      => $this->getDeviceID(),
            'caption'                    => $caption
        ];

        switch ( $target )
        {
            case 'story':
                $endpoint = 'media/configure_to_story/';

                $sendData[ 'configure_mode' ]            = 1;
                $sendData[ 'story_media_creation_date' ] = time() - mt_rand( 10, 20 );
                $sendData[ 'client_shared_at' ]          = time() - mt_rand( 3, 10 );
                $sendData[ 'client_timestamp' ]          = time();

                if ( ! empty( $link ) )
                {
                    $sendData[ 'story_cta' ] = '[{"links":[{"linkType": 1, "webUri":' . json_encode( $link ) . ', "androidClass": "", "package": "", "deeplinkUri": "", "callToActionTitle": "", "redirectUri": null, "leadGenFormId": "", "igUserId": "", "appInstallObjectiveInvalidationBehavior": null}]}]';
                }
                break;
            default:
                $endpoint = 'media/configure/';

                $sendData[ 'caption' ] = $caption;
        }

        try
        {
            $response = (string) $this->getClient()->post( 'https://i.instagram.com/api/v1/' . $endpoint . '?video=1', [
                'headers' => $this->getDefaultHeaders(),
                'form_params' => [
                    'signed_body' => 'SIGNATURE.' . json_encode($sendData)
                ]
            ] )->getBody();

            $response = json_decode( $response, true );
        }
        catch ( Exception $e )
        {
            $response = [];
        }

        return $response;
    }

	private function createUploadId ()
	{
		return number_format( round( microtime( TRUE ) * 1000 ), 0, '', '' );
	}

    public function writeComment ( $comment, $mediaId )
    {
        $data = [
            "_uuid"             => $this->getDeviceID(),
            "device_id"         => $this->getAndroidDeviceID(),
            "delivery_class"    => "organic",
            "feed_position"     => "0",
            "container_module"  => "self_comments_v2_feed_contextual_self_profile", // "comments_v2",
            "comment_text"      => $comment,
            'idempotence_token' => $this->generateUUID()
        ];

        $endpoint = sprintf( "https://i.instagram.com/api/v1/media/%s/comment/", $mediaId );

        try
        {
            $response = (string) $this->getClient()->post( $endpoint, [
                'headers' => $this->getDefaultHeaders(),
                'form_params' => [
                    'signed_body' => 'SIGNATURE.' . json_encode($data)
                ]
            ] )->getBody();

            $response = json_decode( $response, true );
        }
        catch ( Exception $e )
        {
            $response = [];
        }

        if ( isset( $response[ 'status' ] ) )
        {
            if ( $response[ 'status' ] != 'ok' && isset( $response[ 'message' ] ) )
            {
                return [
                    'error' => $response[ 'message' ]
                ];
            }
            else if ( $response[ 'status' ] == 'ok' )
            {
                if ( isset( $response[ 'comment' ][ 'pk' ] ) )
                {
                    return [
                        'id' => $response[ 'comment' ][ 'pk' ]
                    ];
                }
            }
        }

        return [
            'error' => fsp__( 'Unknown error' )
        ];
    }

    private function reorderByHashCode ( $data )
    {
        $hashCodes = [];
        foreach ( $data as $key => $value )
        {
            $hashCodes[ $key ] = $this->hashCode( $key );
        }

        uksort( $data, function ( $a, $b ) use ( $hashCodes ) {
            $a = $hashCodes[ $a ];
            $b = $hashCodes[ $b ];
            if ( $a < $b )
            {
                return -1;
            }
            else if ( $a > $b )
            {
                return 1;
            }
            else
            {
                return 0;
            }
        } );

        return $data;
    }

    private function hashCode ( $string )
    {
        $result = 0;
        for ( $i = 0, $len = strlen( $string ); $i < $len; ++$i )
        {
            $result = ( -$result + ( $result << 5 ) + ord( $string[ $i ] ) ) & 0xFFFFFFFF;
        }

        if ( PHP_INT_SIZE > 4 )
        {
            if ( $result > 0x7FFFFFFF )
            {
                $result -= 0x100000000;
            }
            else if ( $result < -0x80000000 )
            {
                $result += 0x100000000;
            }
        }

        return $result;
    }

    public function getMediaInfo ( $postId )
    {
        $url = 'https://i.instagram.com/api/v1/media/' . $postId . '/info/';

        try
        {
            $request = (string) $this->getClient()->get( $url, [
                'headers' => $this->getDefaultHeaders()
            ])->getBody();
        }
        catch ( Exception $e )
        {
            return [];
        }

        return json_decode( $request, true );
    }

    /**
     * @throws Exception
     */
    public function getChanelInfo ()
    {
        $userBio = (string) $this->getClient()->get( 'https://i.instagram.com/api/v1/accounts/current_user/?edit=true', [
            'headers' => $this->getDefaultHeaders()
        ])->getBody();

        $userBio = json_decode( $userBio, true );

        if (!$userBio || empty($userBio['user']))
        {
            throw new Exception();
        }

        return [
            'social_network'     => 'instagram',
            'name'               => !empty($userBio['user']['full_name']) ? $userBio['user']['full_name'] : $this->username,
            'channel_type'       => 'account',
            'remote_id'          => $this->user_id,
            'picture'            => isset($userBio['user']['profile_pic_url']) ? $userBio['user']['profile_pic_url'] : '',
            'username'           => $this->username,
        ];
    }

    /**
     * @throws Exception
     */
    public function checkChannel ()
    {
        $this->getChanelInfo();
    }
}