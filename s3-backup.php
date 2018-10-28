<?php

define('REGION', 'eu-central-1');

// require the AWS SDK for PHP library
require './vendor/autoload.php';

use Aws\S3\S3Client;

if(count($argv) != 2){
    exit("Usage: {$argv[0]} <directory_to_be_uploaded>\n");
}

$source_dir_full_path = rtrim($argv[1], '/');
$source_dir_name = substr($source_dir_full_path, strrpos($source_dir_full_path, '/') + 1);
if(strlen($source_dir_name) < 1)
    exit("Invalid path given: {$source_dir_full_path}.\n");

// Establish connection with DreamObjects with an S3 client.

$client = new Aws\S3\S3Client([
    'version'     => '2006-03-01',
    'region'      => REGION
]);

// Credentials are fetch via env variables
// via AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY

$buckets = $client->listBuckets();
$backup_bucket='';

echo "Listing available buckets:\n";
try {
    foreach ($buckets['Buckets'] as $bucket){
        echo "{$bucket['Name']}\t{$bucket['CreationDate']}\n";
        if(strpos($bucket['Name'], 'backup') !== false)
            $backup_bucket = $bucket['Name'];
    }
} catch (S3Exception $e) {
    echo $e->getMessage() . "\n";
}

if($backup_bucket == "" )
    exit("Could not find backup bucket\n");

// By default, Transfer will automatically switch from PutObject to
// MultiPart upload for files bigger than 16mb. This is awesome!
// ACL by default is 'private'.

// It is necessary to manually append the source name
// in the destination to get the path right.
$dest = 's3://'. $backup_bucket . '/' . $source_dir_name;
$manager = new \Aws\S3\Transfer($client, $source_dir_full_path, $dest);

echo "Uploading {$source_dir_full_path} to {$dest}.\n";

// Initiate the transfer and get a promise
$promise = $manager->promise();

// Do something when the transfer is complete using the then() method
$promise->then(function () {
        echo "Upload successful!\n";
});

// Error handler
$promise->otherwise(function ($reason) {
        $cwd=getcwd();
        echo "Transfer failed, see {$cwd}/s3-failed.log for more information.\n";
        $fp = fopen("{$cwd}/s3-failed.log", 'w');
        fwrite($fp, serialize($reason));
        fclose($fp);
        var_dump($reason);
});

echo "Uploading... ";
