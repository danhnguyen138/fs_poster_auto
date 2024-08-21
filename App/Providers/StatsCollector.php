<?php

namespace FSPoster\App\Providers;

use Exception;

class StatsCollector
{
    public static function getAccountAddMethod( $account )
    {
        $driver = $account['driver'];

        switch ($driver) {
            case 'fb':
            case 'twitter':
            case 'google_b':
            case 'pinterest':
                $method = empty($account['options']) ? 'app' : 'cookie';
                break;
            case 'instagram':
                $method = ($account['options'] === '*****') ? 'cookie' : (($account['options'] === '#####') ? 'app' : 'password');
                break;
            case 'threads':
            case 'wordpress':
                $method = 'password';
                break;
            case 'linkedin':
            case 'ok':
            case 'vk':
            case 'blogger':
            case 'discord':
            case 'mastodon':
            case 'reddit':
                $method = 'app';
                break;
            case 'planly':
                $method = 'access_token';
                break;
            case 'telegram':
                $method = 'bot_token';
                break;
            case 'medium':
                $method = 'integration_token';
                break;
            case 'tumblr':
                $method = empty($account['options']) ? 'app' : 'password';
                break;
            case 'youtube_community':
            case 'xing':
                $method = 'cookie';
                break;
            default:
                $method = 'default';
                break;
        }

        return $method;
    }

    public static function collectStats()
    {
        $reportData = [];

        $accounts = DB::fetchAll('accounts') ?: [];
        $activeAccounts = DB::DB()->get_results('SELECT driver, count(driver) as c from ' . DB::table('accounts') . ' WHERE id IN (SELECT DISTINCT account_id from ' . DB::table('account_status') . ') GROUP BY driver', 'ARRAY_A') ?: [];
        $activeAccountsPerSn = [];

        foreach ($activeAccounts as $activeAccount)
        {
            $activeAccountsPerSn[$activeAccount['driver']] = $activeAccount['c'];
        }

        $accountInfo = [];

        foreach ($accounts as $account)
        {
            $driver = $account['driver'];

            $accountInfo[(string) $account['id']] = [
                'driver' => $driver,
                'method' => self::getAccountAddMethod($account),
                'type'   => 'account',
                'disconnected' => $account['status'] === 'error'
            ];
        }

        unset($accounts);

        $nodes    = DB::fetchAll('account_nodes') ?: [];
        $activeNodes = DB::DB()->get_results('SELECT driver, count(driver) as c from ' . DB::table('account_nodes') . ' WHERE id IN (SELECT DISTINCT node_id from ' . DB::table('account_node_status') . ') GROUP BY driver', 'ARRAY_A') ?: [];

        $activeNodesPerSn = [];

        foreach ($activeNodes as $activeNode)
        {
            $activeNodesPerSn[$activeNode['driver']] = $activeNode['c'];
        }

        $nodesInfo = [];

        foreach ($nodes as $node)
        {
            if(!isset($accountInfo[(string) $node['account_id']]))
            {
                continue;
            }

            if(isset($reportData['channels']['per_channel_type'][$node['node_type']]))
            {
                $reportData['channels']['per_channel_type'][$node['node_type']] += 1;
            }
            else
            {
                $reportData['channels']['per_channel_type'][$node['node_type']] = 1;
            }

            $nodesInfo[$node['id']] = $accountInfo[(string) $node['account_id']];
            $nodesInfo[$node['id']]['type'] = $node['node_type'];
        }

        unset($nodes);

        $reportData['channels']['per_channel_type']['account'] = count($accountInfo);
        $reportData['channels']['total'] = count($accountInfo) + count($nodesInfo);

        $reportData['channels']['per_social_network'] = [];

        foreach ($accountInfo as $ai)
        {
            if(! isset($reportData['channels']['per_social_network'][$ai['driver']]['total']))
            {
                $reportData['channels']['per_social_network'][$ai['driver']]['total'] = 0;
            }

            if(!isset($reportData['channels']['per_social_network'][$ai['driver']]['per_method'][$ai['method']]))
            {
                $reportData['channels']['per_social_network'][$ai['driver']]['per_method'][$ai['method']] = 0;
            }

            if(!isset($reportData['channels']['per_social_network'][$ai['driver']]['disconnected']))
            {
                $reportData['channels']['per_social_network'][$ai['driver']]['disconnected'] = 0;
            }

            $reportData['channels']['per_social_network'][$ai['driver']]['total'] ++;
            $reportData['channels']['per_social_network'][$ai['driver']]['per_method'][$ai['method']] ++;

            if($ai['disconnected'])
            {
                $reportData['channels']['per_social_network'][$ai['driver']]['disconnected'] ++;
            }
        }

        foreach ($nodesInfo as $ai)
        {
            if(! isset($reportData['channels']['per_social_network'][$ai['driver']]['total']))
            {
                $reportData['channels']['per_social_network'][$ai['driver']]['total'] = 0;
            }

            if(!isset($reportData['channels']['per_social_network'][$ai['driver']]['per_method'][$ai['method']]))
            {
                $reportData['channels']['per_social_network'][$ai['driver']]['per_method'][$ai['method']] = 0;
            }

            if(!isset($reportData['channels']['per_social_network'][$ai['driver']]['disconnected']))
            {
                $reportData['channels']['per_social_network'][$ai['driver']]['disconnected'] = 0;
            }

            $reportData['channels']['per_social_network'][$ai['driver']]['total'] ++;
            $reportData['channels']['per_social_network'][$ai['driver']]['per_method'][$ai['method']] ++;

            if($ai['disconnected'])
            {
                $reportData['channels']['per_social_network'][$ai['driver']]['disconnected'] ++;
            }
        }


        foreach ($reportData['channels']['per_social_network'] as $driver => $perSnData)
        {
            $activeA = isset($activeAccountsPerSn[$driver]) ? $activeAccountsPerSn[$driver] : 0;
            $activeN = isset($activeNodesPerSn[$driver]) ? $activeNodesPerSn[$driver] : 0;

            $totalActive = $activeA + $activeN;

            $reportData['channels']['per_social_network'][$driver]['active'] = $totalActive;

            $totalDeactive = $reportData['channels']['per_social_network'][$driver]['total'] - $totalActive;

            $reportData['channels']['per_social_network'][$driver]['deactive'] = $totalDeactive;
        }

        $reportData['channel_groups']['total'] = count(DB::fetchAll('account_groups') ?: []);
        $reportData['schedules']['total'] = count(DB::fetchAll('schedules') ?: []);

        $reportData['posts'] = self::getFeedsStats($accountInfo, $nodesInfo);

        $blogs = Helper::getBlogs();

        $reportData['direct_share']['drafts'] = 0;

        foreach ($blogs as $blog)
        {
            Helper::setBlogId($blog);

            $drafts = DB::DB()->get_results('SELECT count(*) as c from ' . DB::WPtable('posts', true) . ' WHERE post_type = \'fs_post\'', 'ARRAY_A') ?: [['c' => 0]];

            $reportData['direct_share']['drafts'] += $drafts[0]['c'];
            Helper::resetBlogId();
        }

        $apps = DB::fetchAll('apps');

        $reportData['apps'] = [];
        $reportData['apps']['total'] = count($apps);
        $reportData['apps']['per_type']['standard'] = 0;
        $reportData['apps']['per_type']['custom'] = 0;
        $reportData['apps']['per_social_network'] = [];

        foreach ($apps as $app)
        {
            if(!isset($reportData['apps']['per_social_network'][$app['driver']]))
            {
                $reportData['apps']['per_social_network'][$app['driver']]['total'] = 0;
                $reportData['apps']['per_social_network'][$app['driver']]['per_type']['custom'] = 0;
                $reportData['apps']['per_social_network'][$app['driver']]['per_type']['standard'] = 0;
            }

            $reportData['apps']['per_social_network'][$app['driver']]['total'] ++;

            if(!empty($app['is_standard']) || !empty($app['is_standart']) || !empty($app['slug']) )
            {
                $reportData['apps']['per_type']['standard'] ++;
                $reportData['apps']['per_social_network'][$app['driver']]['per_type']['standard'] ++;
            }
            else
            {
                $reportData['apps']['per_type']['custom'] ++;
                $reportData['apps']['per_social_network'][$app['driver']]['per_type']['custom'] ++;
            }
        }

        $reportData['settings']['allowed_post_types'] = explode( '|', Helper::getOption( 'allowed_post_types', 'post|page|attachment|product' ) );
        $reportData['settings']['show_in_column'] = (int) Helper::getOption( 'show_fs_poster_column', '1' ) === 1;
        $reportData['settings']['hide_notifications'] = (int) Helper::getOption( 'hide_notifications', '0' ) === 1;
        $reportData['settings']['share_in_background'] = (int) Helper::getOption( 'share_on_background', '1' ) === 1;
        $reportData['settings']['short_url'] = (int) Helper::getOption( 'url_shortener', '0' ) === 1;
        $reportData['settings']['url_shortener'] = Helper::getOption( 'shortener_service' ) ?: '';
        $reportData['settings']['import_facebook_comments'] = (int) Helper::getOption( 'fetch_fb_comments', 0 ) === 1;


        return $reportData;
    }

    public static function getFeedsStats($accountInfo, $nodeInfo)
    {
        $feedStats = Helper::getOption('feeds_report_data', [], true);

        if(!empty($feedStats))
        {
            return $feedStats;
        }

        $accountsPerSn = [];

        foreach ($accountInfo as $id => $info)
        {
            $accountsPerSn[$info['driver']][$info['method']][] = $id;
        }

        $nodesPerSn = [];

        foreach ($nodeInfo as $id => $info)
        {
            $nodesPerSn[$info['driver']][$info['method']][] = $id;
        }

        $accountFeeds = [];

        foreach ($accountsPerSn as $driver => $methodsArr)
        {
            foreach ($methodsArr as $method => $accounts){
                if(!empty($accounts))
                {
                    $inArr = implode(',', $accounts);
                    $feeds = DB::DB()->get_results('SELECT shared_from, count(*) as c FROM ' . DB::table('feeds') . ' WHERE is_sended=1 and (node_type = \'account\' or driver = \'webhook\') and driver = \'' . $driver . '\' and node_id in(' . $inArr . ') group by shared_from', 'ARRAY_A') ?: [];

                    foreach ($feeds as $feed)
                    {
                        $accountFeeds[$driver][$method][$feed['shared_from']] = $feed['c'];
                    }
                }
            }
        }

        $nodeFeeds = [];

        foreach ($nodesPerSn as $driver => $methodsArr)
        {
            foreach ($methodsArr as $method => $accounts){
                if(!empty($accounts))
                {
                    $inArr = implode(',', $accounts);
                    $feeds = DB::DB()->get_results('SELECT shared_from, count(*) as c FROM ' . DB::table('feeds') . ' WHERE is_sended=1 and node_type <> \'account\' and driver <> \'webhook\' and driver = \'' . $driver . '\' and node_id in(' . $inArr . ') group by shared_from', 'ARRAY_A');

                    foreach ($feeds as $feed)
                    {
                        $nodeFeeds[$driver][$method][$feed['shared_from']] = $feed['c'];
                    }
                }
            }
        }

        $feedsReport = [];
        $feedsReport['per_shared_from'] = [];

        foreach ($accountFeeds as $sn => $details)
        {
            $feedsReport['per_social_network'][$sn]['total'] = 0;

            foreach ($details as $method => $perSharedFrom)
            {
                $feedsReport['per_social_network'][$sn]['per_method'][$method] = 0;
                foreach ($perSharedFrom as $sharedFrom => $count){
                    $feedsReport['per_social_network'][$sn]['total'] += $count;
                    $feedsReport['per_social_network'][$sn]['per_method'][$method] += $count;

                    $feedsReport['per_shared_from'][$sharedFrom] = isset($feedsReport['per_shared_from'][$sharedFrom]) ? ($feedsReport['per_shared_from'][$sharedFrom] + $count) : (int) $count;
                }
            }
        }

        foreach ($nodeFeeds as $sn => $details)
        {
            $feedsReport['per_social_network'][$sn]['total'] = 0;

            foreach ($details as $method => $perSharedFrom)
            {
                $feedsReport['per_social_network'][$sn]['per_method'][$method] = 0;
                foreach ($perSharedFrom as $sharedFrom => $count){
                    $feedsReport['per_social_network'][$sn]['total'] += $count;
                    $feedsReport['per_social_network'][$sn]['per_method'][$method] += $count;

                    $feedsReport['per_shared_from'][$sharedFrom] = isset($feedsReport['per_shared_from'][$sharedFrom]) ? ($feedsReport['per_shared_from'][$sharedFrom] + $count) : (int) $count;
                }
            }
        }

        $total   = DB::DB()->get_results('SELECT count(*) as c from ' . DB::table('feeds'), 'ARRAY_A' ) ?: [['c' => 0]];
        $success = DB::DB()->get_results('SELECT count(*) as c from ' . DB::table('feeds') . ' where status=\'ok\'', 'ARRAY_A' ) ?: [['c' => 0]];
        $fail    = DB::DB()->get_results('SELECT count(*) as c from ' . DB::table('feeds') . ' where status=\'error\'', 'ARRAY_A' ) ?: [['c' => 0]];

        $feedsReport['total'] = (int) $total[0]['c'];
        $feedsReport['per_status'] = [
            'success' => (int) $success[0]['c'],
            'fail' => (int) $fail[0]['c']
        ];

        Helper::setOption('feeds_report_data', $feedsReport, true);

        return $feedsReport;
    }


    public static function sendStats()
    {
        if(defined('FS_POSTER_DISABLE_ANALYTICS'))
        {
            return;
        }

        if(Helper::getOption('stats_collected_at', 0, true) < Date::epoch() - 86400)
        {
            Helper::setOption('stats_collected_at', Date::epoch(), true);

            $data = self::collectStats();

            if(!$data)
            {
                return;
            }

            try
            {
                (new GuzzleClient())->get(FS_API_URL . 'api.php', [
                    'query' => [
                        'act'           => 'collect',
                        'version'       => Helper::getVersion(),
                        'purchase_code' => Helper::getOption( 'poster_plugin_purchase_key', '', TRUE ),
                        'domain'        => network_site_url(),
                        'data'          => json_encode($data),
                        'product'       => 'fs_poster_v6'
                    ]
                ]);
            }
            catch (Exception $e){}
        }
    }
}