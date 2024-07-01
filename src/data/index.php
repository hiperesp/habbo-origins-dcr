<?php
if(\is_dir('latest')) {
    \header("Content-Type: text/plain");
    $dirs = \scandir(__DIR__);
    echo "Available DCRs:\n\n";
    $prefix = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'];
    foreach($dirs as $dir) {
        if(\is_dir($dir) && $dir !== '.' && $dir !== '..') {
            if($dir=="latest") {
                echo "Always latest DCRs:\n";
            } else {
                echo "v{$dir}:\n";
            }
            $files = \scandir(__DIR__.DIRECTORY_SEPARATOR.$dir);
            if($files) {
                foreach($files as $file) {
                    if(\is_file(__DIR__.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$file) && \pathinfo($file, PATHINFO_EXTENSION) === 'dcr') {
                        echo "{$prefix}/{$dir}/{$file}\n";
                    }
                }
                echo "\n";
            }
        }
    }
    die;
}
include "/app/verify-update.php";