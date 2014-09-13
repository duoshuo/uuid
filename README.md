uuid
====

A PHP 5.4+ library for generating RFC 4122 version 1, 3, 4, and 5 universally unique identifiers (UUID).

## Dependency
* PHP 5.4+
* 64-bit Linux System

## Installation
```
Append dependency into composer.json
    ...
    "require": {
        ...
        "duoshuo/uuid": "dev-master"
    }
    ...
```

## Usage
```php
use Uuid\Uuid;

// At beginning, set your Ethernet Controller MAC address.
Uuid::setMAC('56:84:7a:fe:97:99');

// Version 1
Uuid::now();

// Version 1, first argument is timestamp, second argument is micro seconds.
Uuid::fromTimestamp(1410584506, 792720);

// Version 3
Uuid::fromMd5(md5($string));

// Version 4
Uuid::fromRandom();

// Version 5
Uuid::fromSha1(sha1($string));	
```
