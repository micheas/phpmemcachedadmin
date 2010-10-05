<?php
/**
 * Copyright 2010 Cyrille Mahieux
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and limitations
 * under the License.
 *
 * ><)))°> ><)))°> ><)))°> ><)))°> ><)))°> ><)))°> ><)))°> ><)))°> ><)))°>
 *
 * Live Stats top style
 *
 * @author c.mahieux@of2m.fr
 * @since 12/04/2010
 */

# Headers
header('Content-type: text/html;');
header('Cache-Control: no-cache, must-revalidate');

# Require
require_once 'Library/Loader.php';

# Date timezone
date_default_timezone_set('Europe/Paris');

# Loading ini file
$_ini = Library_Configuration::getInstance();

# Initializing requests
$request = (isset($_GET['request_command'])) ? $_GET['request_command'] : null;

# Cookie
if(!isset($_COOKIE['live_stats_id']))
{
    $live_stats_id = rand();

    # Cookie set failed : using remote_addr
    if(!setcookie('live_stats_id', $live_stats_id, time() + 60*60*24*365))
    {
        $live_stats_id = $_SERVER['REMOTE_ADDR'];
    }
}
else
{
    # Backup from a previous request
    $live_stats_id = $_COOKIE['live_stats_id'];
}

# Live stats dump file
$file_path = $_ini->get('file_path') . DIRECTORY_SEPARATOR . 'live_stats.' . $live_stats_id;

# Display by request type
switch($request)
{
    case 'live_stats':
        # Opening old stats dump
        $previous = unserialize(file_get_contents($file_path));

        # Initializing variables
        $actual = array();
        $stats = array();
        $time = 0;

        # Requesting stats for each server
        foreach($_ini->get('server') as $server)
        {
            # Spliting server in hostname:port
            $server = preg_split('/:/', $server);

            # Start query time calculation
            $time = microtime(true);

            # Asking server for stats
            $actual[$server[0] . ':' . $server[1]] = Library_Command_Factory::instance('stats_api')->stats($server[0], $server[1]);

            # Calculating query time length
            $actual[$server[0] . ':' . $server[1]]['query_time'] = (microtime(true) - $time) * 1000;
        }

        # Analysing stats
        foreach($_ini->get('server') as $server)
        {
            # Diff between old and new dump
            $stats[$server] = Library_Analysis::diff($previous[$server], $actual[$server]);

            # Making stats for each server
            foreach($stats as $server => $array)
            {
                # Analysing request
                if((isset($stats[$server]['uptime'])) && ($stats[$server]['uptime'] > 0))
                {
                    # Computing stats
                    $stats[$server] = Library_Analysis::stats($stats[$server]);

                    # Because we make a diff on every key, we must reasign some values
                    $stats[$server]['bytes_percent'] = sprintf('%.1f', $actual[$server]['bytes'] / $actual[$server]['limit_maxbytes'] * 100);
                    $stats[$server]['bytes'] = $actual[$server]['bytes'];
                    $stats[$server]['limit_maxbytes'] = $actual[$server]['limit_maxbytes'];
                    $stats[$server]['curr_connections'] = $actual[$server]['curr_connections'];
                    $stats[$server]['query_time'] = $actual[$server]['query_time'];
                }
            }
        }

        # Saving new stats dump
        file_put_contents($file_path, serialize($actual));

        # Showing stats
        include 'View/LiveStats/Stats.tpl';
        break;

        # Default : No command
    default :
        # Showing header
        include 'View/Header.tpl';

        # Showing live stats frame
        include 'View/LiveStats/Frame.tpl';

        # Showing footer
        include 'View/Footer.tpl';

        # Initializing : making stats dump
        $stats = array();
        foreach($_ini->get('server') as $server)
        {
            # Spliting server in hostname:port
            $server = preg_split('/:/', $server);
            $stats[$server[0] . ':' . $server[1]] = Library_Command_Factory::instance('stats_api')->stats($server[0], $server[1]);
        }

        # Saving first stats dump
        file_put_contents($file_path, serialize($stats));
        break;
}