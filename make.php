#!/usr/bin/php
<?php


$command = isset($argv[1])  ? $argv[1] : "list";

switch ($command) {

    case 'path':
        action_path();
        break;

    case 'vcs':
        action_vcs();
        break;

    default:
        action_list();
        break;
}

function get_games()
{
    return array_merge(glob(__DIR__.'/*.boscot.com'), array('stub', 'core'));
}

function action_list()
{
    $games = array();
    foreach (get_games() as $gameDir) {
        $game = array();

        // Game name
        $game['name'] = preg_replace("#.*/(.*)\.boscot.com$#", "$1", $gameDir);

        action_list_git($game, $gameDir);

        // Readme
        $game['readme'] = file_exists($gameDir."/README.md") ? echo_ok("OK") : echo_error("NOK");

        action_list_composer($game, $gameDir);

        action_list_tests($game, $gameDir);

        
        $games[] = $game;

    }
    display_list($games);

}

function action_list_composer(&$game, $gameDir)
{
    // Composer
    $composerFile = $gameDir."/composer.json";
    if (file_exists($composerFile)) {
        $composerContent = file_get_contents($composerFile);
        if (json_decode($composerContent)) {
            $game['composer'] = echo_ok("OK");
        } else {
            $game['composer'] = echo_error("Invalid");
        }
        if (preg_match('#  "Boscot/core"#mis', $composerContent)) {
            $core_version = preg_replace('#.*"Boscot/core" *: *"([^"]*)".*#mis', "$1", $composerContent);
        } else {
            $core_version = "--";
        }
        $game['core_version'] = echo_ok($core_version);

        if (preg_match('#  "type"#mis', $composerContent)) {
            $vcs = preg_replace('#.*"type" *: *"(vcs|path)".*#mis', "$1", $composerContent);
        } else {
            $vcs = "--";
        }
        $game['vcs'] = echo_ok($vcs);
    } else {
        $game['composer'] = echo_error("Missing");
        $game['core_version'] = echo_error("NOK");
        $game['vcs'] = echo_error("NOK");
    }

}

function action_list_tests(&$game, $gameDir)
{
    // Tests
    $testsResults = exec("phpunit $gameDir/tests");
    //print_r($testsResults."\n");
    if (preg_match("#OK \((\d+) test, (\d+) assertions.*\)#", $testsResults, $match)) {
        if ($match[2] > 0) {
            $game['tests'] =  echo_ok($match[1].' / '.$match[1]);
        }
        $game['tests'] =  echo_error("0 / 0");

    } elseif (preg_match("#Tests: (\d+).*Failures: (\d+), Errors: (\d+)#", $testsResults, $match)) {
        $game['tests'] =  echo_error($match[2] + $match[3].' / '.$match[1]);

    } elseif (preg_match("#Tests: (\d+).*(Errors|Failures): (\d+)#", $testsResults, $match)) {
        $game['tests'] =  echo_error($match[3].' / '.$match[1]);
    }
    if (basename($gameDir) == "core") {
        $game['tests'] =  echo_ok("--");
    }
    $game['tests'] =  echo_error('None');
}

function action_list_git(&$game, $gameDir)
{
    // Git origin
    $originResults = exec("cd $gameDir ;  git remote -v | grep 'origin' | grep 'push' | awk '{print $2}'");
    if ($originResults) {
        $repository = basename($originResults);
        if ($repository != "stub" && $repository != "core" && $repository == "game-".$game['name']) {
            $game['git_origin'] = echo_ok(basename($originResults));
        } else {
            $game['git_origin'] = echo_error(basename($originResults));
        }
    } else {
        $game['git_origin'] = echo_error("None");
    }

    // Git Status
    $statusResults = exec("cd $gameDir ; git status -s | cut -c1 | paste -sd ''");
    if ($statusResults) {
        $game['git_status'] = echo_error($statusResults);
    } else {
        $game['git_status'] = echo_ok("OK");
    }
}

function display_list($games)
{
    $title = "-=(   BOSCOT GAMES STATUS   )=-";

    //print_r($games);
    $headers = array();
    foreach ($games as $game) {
        foreach ($game as $param => $value) {
            if (!isset($headers[$param])) {
                $headers[$param] = 0;
            }
            $cleanValue = preg_replace("#(\033|\[\d+m)#", "", $value);
            //echo "$value => $cleanValue\n";
            $headers[$param] = max(strlen($cleanValue), $headers[$param], strlen($param));
        }
    }
    //print_r($headers);

    $line = "";
    foreach ($headers as $header => $value) {
        $line .= "+".str_repeat("-", $value + 2);
    }
    $line .= "+\n";

    echo "\n".str_repeat(" ", (strlen($line)-1-strlen($title))/2).$title."\n\n";

    echo $line;
    foreach ($headers as $header => $value) {
        echo "| ".str_pad(str_replace("_", " ", ucfirst($header)), $value). " ";
    }
    echo "|\n";
    echo $line;
    $footer = false;
    foreach ($games as $game) {
        if (in_array($game['name'], array('stub', 'core')) && !$footer) {
            echo $line;
            $footer = true;
        }
        foreach ($headers as $header => $value) {
            echo "| ".str_pad($game[$header], $value + count_color_chars($game[$header])). " ";
        }
        echo "|\n";
    }

    echo $line;

    // -2 to ignore stub & core
    echo "Total : ".echo_ok(sizeof($games)-2)."\n";
}

function action_path()
{
    foreach (get_games() as $gameDir) {
        $composerFile = $gameDir."/composer.json";
        if (file_exists($composerFile)) {
            exec('sed -i \'s/"type": "vcs"/"type": "path"/\' '.$composerFile);
            #echo 'sed -i \'s/"type": "vcs"/"type": "path"/\' '.$composerFile;
        }
    }
}


function action_vcs()
{
    foreach (get_games() as $gameDir) {
        $composerFile = $gameDir."/composer.json";
        if (file_exists($composerFile)) {
            exec('sed -i \'s/"type": "path"/"type": "vcs"/\' '.$composerFile);
        }
    }
}

function count_color_chars($var)
{
    return substr_count($var, "\033")*1
         + substr_count($var, "[0m")*3
         + substr_count($var, "[1m")*3
         + substr_count($var, "[31m")*4
         + substr_count($var, "[32m")*4
         + substr_count($var, "[33m")*4
    ;
}

function echo_bold($var)
{
    return "\033[1m$var\033[0m";
}

function echo_error($var)
{
    return "\033[1m\033[31m$var\033[0m";
}

function echo_ok($var)
{
    return "\033[1m\033[32m$var\033[0m";
}

function echo_warning($var)
{
    return "\033[1m\033[33m$var\033[0m";
}
