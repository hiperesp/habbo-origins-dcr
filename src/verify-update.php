<?php
$configs = [
    "ua" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36",
    "clienturls" => "https://origins.habbo.com/gamedata/clienturls",
    "dataDir" => "/var/www/html",
];
$context = \stream_context_create([ "http" => [ "header" => $configs["ua"] ] ]);

$json = \json_decode(\file_get_contents($configs["clienturls"], false, $context), true);
$latestVersion = $json["shockwave-windows-version"];

echo "latest version: {$latestVersion}\n";
if(\is_dir("{$configs["dataDir"]}/{$latestVersion}")) {
    echo "up to date\n";
    die;
}

if(\file_exists("{$configs["dataDir"]}/{$latestVersion}.lock")) {
    // if the lock file is older than 1 hour, delete it and continue
    if(\filemtime("{$configs["dataDir"]}/{$latestVersion}.lock") < \time() - 60 * 60 * 1) { // 1 hour
        echo "lock expired\n";
        \unlink("{$configs["dataDir"]}/{$latestVersion}.lock");
    } else {
        echo "locked\n";
        die;
    }
}

\file_put_contents("{$configs["dataDir"]}/{$latestVersion}.lock", "locked");
$zip = \file_get_contents($json["shockwave-windows"], false, $context);
// store zip contents in tmp folder to extract
$tmpfile = \tmpfile();
\fwrite($tmpfile, $zip);
\fseek($tmpfile, 0);
$tmpfileUri = \stream_get_meta_data($tmpfile)["uri"];

$zip = new \ZipArchive();
$zip->open($tmpfileUri);
$zip->extractTo("{$configs["dataDir"]}/{$latestVersion}/");
$zip->close();

\unlink("{$configs["dataDir"]}/{$latestVersion}.lock");
echo "updated\n";

// create a symlink to the latest version
if(\file_exists("{$configs["dataDir"]}/latest")) {
    \unlink("{$configs["dataDir"]}/latest");
}
\symlink("{$configs["dataDir"]}/{$latestVersion}", "{$configs["dataDir"]}/latest");

