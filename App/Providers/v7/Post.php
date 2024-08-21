<?php

namespace FSPoster\App\Providers\v7;

use FSPoster\App\Providers\Date;
use FSPoster\App\Providers\DB;
use FSPoster\App\Providers\Helper;
use FSPoster\App\Providers\StatsCollector;

class Post
{
	/**
	 * @var $feedId int
	*/
	private $feedId;

	/**
	 * @var PostData
	 */
	private $postData;

	/**
	 * @var array
	*/
	private $result;

	public function __construct( $feedId )
	{
		$this->feedId = $feedId;
	}

	/**
	 * @param $secure boolean
	 *
	 * @return null|array
	*/
	public function init( $secure )
	{
		$feed = DB::fetch( 'feeds', $this->feedId );

		if ( ! $feed || ( $secure && $feed[ 'is_sended' ] != 2 ) )
		{
			return [
				'status'    => 'error',
				'error_msg' => ''
			];
		}

		$node        = Helper::getAccessToken( $feed[ 'node_type' ], $feed[ 'node_id' ] );
		$accessToken = $node[ 'access_token' ];

		if ( is_array( $accessToken ) )
		{
			$updateFeedSQL = [
				'is_sended'       => 1,
				'send_time'       => Date::dateTimeSQL(),
				'status'          => 'error',
				'error_msg'       => isset( $accessToken[ 'error_msg' ] ) ? Helper::cutText( $accessToken[ 'error_msg' ], 797 ) : '',
				'driver_post_id'  => NULL,
				'driver_post_id2' => NULL
			];

			DB::DB()->update( DB::table( 'feeds' ), $updateFeedSQL, [ 'id' => $this->feedId ] );

			return [
				'status'    => 'error',
				'error_msg' => isset( $accessToken[ 'error_msg' ] ) ? $accessToken[ 'error_msg' ] : ''
			];
		}

		$this->postData = new PostData();

		$this->postData->setFeed( $feed )
		               ->setNode( $node )
		               ->init();
		
		return null;
	}

	/**
	 * @return void
	*/
	public function share()
	{
        try
        {
            $this->result = apply_filters( 'fsp_share_post_' . $this->postData->getDriver(), [], $this->postData );

            if ( empty( $this->result ) )
            {
                $this->result = [
                    'status'    => 'error',
                    'error_msg' => fsp__( 'The account has been deleted. <a href="https://www.fs-poster.com/documentation/commonly-encountered-issues#issue13" target=\'_blank\'>Learn more!</a>', [], TRUE )
                ];
            }
        }
        catch (\Exception $e)
        {
            $this->result = [
                'status'    => 'error',
                'error_msg' => $e->getMessage()
            ];
        }

	}

	/**
	 * @return void
	 */
	public function handleLogs()
	{
        $feedStats = Helper::getOption('feeds_report_data', [], true);

        if(empty($feedStats))
        {
            $stats = StatsCollector::collectStats();
            $feedStats = $stats['posts'];
        }

        $feed = DB::fetch('feeds', $this->feedId);

        if($feed['node_type'] === 'account' || $feed['driver'] === 'webhook')
        {
            $account = DB::fetch('accounts', $feed['node_id']);
        }
        else
        {
            $node = DB::fetch('account_nodes', $feed['node_id']);

            if($node)
            {
                $account = DB::fetch('accounts', $node['id']);
            }
        }

        if(!empty($account) && ($this->result[ 'status' ] === 'ok' || $this->result[ 'status' ] === 'error'))
        {
            if( !isset($feedStats['total']) )
            {
                $feedStats['total'] = 0;
            }

            $feedStats['total'] ++;

            if(isset($feed['shared_from']))
            {
                if(!isset($feedStats['per_shared_from'][$feed['shared_from']]))
                {
                    $feedStats['per_shared_from'][$feed['shared_from']] = 0;
                }

                $feedStats['per_shared_from'][$feed['shared_from']] ++;
            }

            if(!isset($feedStats['per_social_network'][$feed['driver']]['total']))
            {
                $feedStats['per_social_network'][$feed['driver']]['total'] = 0;
            }

            $addMethod = StatsCollector::getAccountAddMethod($account);

            if(!isset($feedStats['per_social_network'][$feed['driver']]['per_method'][$addMethod]))
            {
                $feedStats['per_social_network'][$feed['driver']]['per_method'][$addMethod] = 0;
            }

            $feedStats['per_social_network'][$feed['driver']]['total'] ++;
            $feedStats['per_social_network'][$feed['driver']]['per_method'][$addMethod] ++;

            if(!isset($feedStats['per_status'][$this->result[ 'status' ]]))
            {
                $feedStats['per_status'][$this->result[ 'status' ]] = 0;
            }

            $feedStats['per_status'][$this->result[ 'status' ]] ++;

            Helper::setOption('feeds_report_data', $feedStats, true);
        }

		if ( ! Helper::getOption( 'keep_logs', '1' ) )
		{
			DB::DB()->delete( DB::table( 'feeds' ), [ 'id' => $this->feedId ] );
			return;
		}

		$updateDate = [
			'is_sended'       => 1,
			'send_time'       => Date::dateTimeSQL(),
			'status'          => $this->result[ 'status' ] === 'ok' && $this->postData->isDriver( 'planly' ) ? 'processing' : $this->result[ 'status' ],
			'error_msg'       => isset( $this->result[ 'error_msg' ] ) ? Helper::cutText( $this->result[ 'error_msg' ], 797 ) : '',
			'driver_post_id'  => isset( $this->result[ 'id' ] ) ? $this->result[ 'id' ] : NULL,
			'driver_post_id2' => isset( $this->result[ 'id2' ] ) ? $this->result[ 'id2' ] : NULL
		];

		if ( $this->postData->isDriver( 'webhook' ) && isset( $this->result[ 'response' ] ) )
		{
			$updateDate[ 'data' ] = json_encode( [ 'response' => $this->result[ 'response' ] ] );
		}

		if ( $this->postData->isDriver( 'blogger' ) )
		{
			$updateDate[ 'feed_type' ] = isset( $this->result[ 'feed_type' ] ) ? $this->result[ 'feed_type' ] : NULL;
		}

		DB::DB()->update( DB::table( 'feeds' ), $updateDate, [ 'id' => $this->feedId ] );
	}

	/**
	 * @return array
	 */
	public function result()
	{
		if ( ! isset( $this->result[ 'id' ] ) )
		{
			$this->result[ 'post_link' ] = admin_url( 'admin.php?page=fs-poster-logs&webhook_feed_id=' . $this->postData->feed[ 'id' ] );
		}

		if ( $this->postData->isDriver( 'google_b' ) )
		{
			$username = '';

			if ( ! empty( $this->postData->getOptions() ) )
			{
				$username = $this->postData->getNodeProfileId();
			}
		}
		else if ( $this->postData->isDriver( 'blogger' ) )
		{
			$username = $this->result[ 'id2' ];
		}
		else if ( $this->postData->isDriver( 'wordpress' ) )
		{
			$username = $this->postData->getOptions();
		}
		else
		{
			$username = isset( $this->postData->node[ 'info' ][ 'screen_name' ] ) ? $this->postData->node[ 'info' ][ 'screen_name' ] : $this->postData->node[ 'username' ];
		}

		if ( ! isset( $this->result[ 'post_link' ] ) )
		{
			$this->result[ 'post_link' ] = Helper::postLink( $this->postData->isDriver( 'discord' ) ? $this->result[ 'id2' ] : $this->result[ 'id' ], $this->postData->getDriver() . ( $this->postData->isDriver( 'instagram' ) ? $this->postData->feed[ 'feed_type' ] : '' ), $username );
		}

		if ( isset( $this->result[ 'comment' ] ) )
		{
			$dataSQL = [
				'driver'     => $this->postData->getDriver(),
				'node_type'  => $this->postData->node[ 'info' ][ 'node_type' ],
				'account_id' => $this->postData->node[ 'info' ][ 'id' ],
				'comment'    => $this->postData->comment,
			];

			if ( isset( $this->result[ 'comment' ][ 'url' ] ) )
			{
				$dataSQL[ 'comment_url' ] = $this->result[ 'comment' ][ 'url' ];
			}
			else if ( isset( $this->result[ 'comment' ][ 'error' ] ) )
			{
				$dataSQL[ 'error' ] = $this->result[ 'comment' ][ 'error' ];
			}
			else
			{
				$dataSQL[ 'error' ] = fsp__( 'Unknown error' );
			}

			DB::DB()->insert( DB::table( 'post_comments' ), $dataSQL );
		}

		return $this->result;
	}
}