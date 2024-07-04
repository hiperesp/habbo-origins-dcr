<?php
require "/app/aws.phar";
$bucket = new Bucket;

$configs = [
    "ua" => \getenv("USER_AGENT") ?: "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36",
    "clienturls" => \getenv("CLIENTURLS") ?: "https://origins.habbo.com/gamedata/clienturls",
];
$context = \stream_context_create([ "http" => [ "header" => $configs["ua"] ] ]);

$json = \json_decode(\file_get_contents($configs["clienturls"], false, $context), true);
$latestVersion = $json["shockwave-windows-version"];

\file_put_contents('/tmp/last-check.txt', \date("Y-m-d H:i:s")."\n");
$bucket->uploadFile("/tmp/last-check.txt", "last-check.txt");
\unlink("/tmp/last-check.txt");

echo "latest version: {$latestVersion}\n";
if($bucket->hasFile("latest.txt")) {
    if($bucket->readFile("latest.txt") == $latestVersion) {
        echo "up to date\n";
        die;
    }
}

if(\file_exists("/tmp/{$latestVersion}.lock")) {
    // if the lock file is older than 1 hour, delete it and continue
    if(\filemtime("/tmp/{$latestVersion}.lock") < \time() - 60 * 60 * 1) { // 1 hour
        echo "lock expired\n";
        \unlink("/tmp/{$latestVersion}.lock");
    } else {
        echo "locked\n";
        die;
    }
}

\file_put_contents("/tmp/{$latestVersion}.lock", "locked");
$zip = \file_get_contents($json["shockwave-windows"], false, $context);
// store zip contents in tmp folder to extract
$tmpfile = \tmpfile();
\fwrite($tmpfile, $zip);
\fseek($tmpfile, 0);
$tmpfileUri = \stream_get_meta_data($tmpfile)["uri"];

$zip = new \ZipArchive();
$zip->open($tmpfileUri);
$zip->extractTo("/tmp/{$latestVersion}/");
$zip->close();

\file_put_contents('/tmp/latest.txt', $latestVersion);

$bucket->uploadDir("/tmp/{$latestVersion}", "{$latestVersion}");
$bucket->deleteDir("latest");
$bucket->copyRemoteDir("{$latestVersion}", "latest");
$bucket->uploadFile("/tmp/latest.txt", "latest.txt");

\unlink('/tmp/latest.txt');
\unlink("/tmp/{$latestVersion}.lock");
\rmdir("/tmp/{$latestVersion}");

echo "updated\n";

class Bucket {
    private $s3;

    public function __construct() {
        if(!\getenv("AWS_ENDPOINT")) {
            throw new \Exception("AWS_ENDPOINT not set");
        }
        if(!\getenv("AWS_ACCESS_KEY_ID")) {
            throw new \Exception("AWS_ACCESS_KEY_ID not set");
        }
        if(!\getenv("AWS_SECRET_ACCESS_KEY")) {
            throw new \Exception("AWS_SECRET_ACCESS_KEY not set");
        }
        if(!\getenv("AWS_BUCKET")) {
            throw new \Exception("AWS_BUCKET not set");
        }

        $this->s3 = new \Aws\S3\S3Client([
            "version" => "latest",
            "region" => "auto",
            "endpoint" => \getenv("AWS_ENDPOINT"),
            "credentials" => [
                "key" => \getenv("AWS_ACCESS_KEY_ID"),
                "secret" => \getenv("AWS_SECRET_ACCESS_KEY"),
            ],
        ]);
    }

    public function uploadFile($localFile, $remoteFile) {
        $result = $this->s3->putObject([
            "Bucket" => \getenv("AWS_BUCKET"),
            "Key" => "{$remoteFile}",
            "Body" => \fopen($localFile, "r"),
        ]);
        return $result;
    }

    public function uploadDir($localDir, $remoteDir) {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localDir),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach($files as $file) {
            if($file->isDir()) {
                continue;
            }
            $this->uploadFile($file->getPathname(), \str_replace($localDir, $remoteDir, $file->getPathname()));
        }
    }

    public function deleteDir($remoteDir) {
        $objects = $this->s3->listObjects([
            "Bucket" => \getenv("AWS_BUCKET"),
            "Prefix" => "{$remoteDir}/",
        ]);
        foreach($objects["Contents"] as $object) {
            $this->s3->deleteObject([
                "Bucket" => \getenv("AWS_BUCKET"),
                "Key" => $object["Key"],
            ]);
        }
    }

    public function readFile($remoteFile) {
        $result = $this->s3->getObject([
            "Bucket" => \getenv("AWS_BUCKET"),
            "Key" => "{$remoteFile}",
        ]);
        return $result["Body"];
    }

    public function hasFile($remoteFile) {
        try {
            $this->s3->headObject([
                "Bucket" => \getenv("AWS_BUCKET"),
                "Key" => "{$remoteFile}",
            ]);
            return true;
        } catch(\Aws\S3\Exception\S3Exception $e) {
            return false;
        }
    }

    public function copyRemoteDir($oldDir, $newDir) {
        $objects = $this->s3->listObjects([
            "Bucket" => \getenv("AWS_BUCKET"),
            "Prefix" => "{$oldDir}/",
        ]);
        foreach($objects["Contents"] as $object) {
            $this->s3->copyObject([
                "Bucket" => \getenv("AWS_BUCKET"),
                "CopySource" => \getenv("AWS_BUCKET") . "/" . $object["Key"],
                "Key" => \str_replace($oldDir, $newDir, $object["Key"]),
            ]);
        }
    }

}