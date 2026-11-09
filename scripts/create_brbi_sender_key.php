<?php

// Temporary one-release sender identity: publish only the verification key, never the private key.
$directory=dirname(__DIR__).'/storage/app/deployment-data-sync';
$privatePath=$directory.'/sender-private.pem';
$publicPath=dirname(__DIR__).'/resources/brbi-sync-sender-public.pem';
if(file_exists($privatePath)||file_exists($publicPath)) throw new RuntimeException('Sender key already exists.');
$key=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
if(!$key||!openssl_pkey_export($key,$private)) throw new RuntimeException('Cannot generate sender key.');
$public=openssl_pkey_get_details($key)['key'];
if(file_put_contents($privatePath,$private)===false||file_put_contents($publicPath,$public)===false) throw new RuntimeException('Cannot store sender keys.');
@chmod($privatePath,0600);
echo json_encode(['public_key_sha256'=>hash('sha256',$public),'private_key_uploaded'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
