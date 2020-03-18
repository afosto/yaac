# yaac - Yet another ACME client

Written in PHP, this client aims to be a simplified and decoupled LetsEncrypt client, based on ACME V2.

## Decoupled from a filesystem or webserver

In stead of, for example writing the certificate to the disk under an nginx configuration, this client just returns the 
data (the certificate and private key).

## Why

Why whould I need this package? At Afosto we run our software in a multi-tenant setup, as any other SaaS would do, and
therefore we cannot make use of the many clients that are already out there. 

Almost all clients are coupled to a type of webserver or a fixed (set of) domain(s). This package can be extremely 
useful in case you need to dynamically fetch and install certificates.


## Requirements

- PHP7+
- openssl
- [Flysystem](http://flysystem.thephpleague.com/) (any adapter would do) - to store the Lets Encrypt account information


## Getting started

Getting started is easy. First install the client, then you need to construct a flysystem filesystem, instantiate the 
client and you can start requesting certificates.

### Installation

Installing this package is done easily with composer. 
```bash
composer require afosto/yaac
```

### Instantiate the client

To start the client you need 3 things; a username for your LetsEncrypt account, a bootstrapped Flysystem and you need to 
decide whether you want to issue `Fake LE Intermediate X1` (staging: `MODE_STAGING`) or `Let's Encrypt Authority X3` 
(live: `MODE_LIVE`, use for production) certificates.

```php
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Afosto\Acme\Client;
 
//Prepare flysystem
$adapter = new Local('data');
$filesystem = new Filesystem($adapter);
 
//Construct the client
$client = new Client([
    'username' => 'example@example.org',
    'fs'       => $filesystem,
    'mode'     => Client::MODE_STAGING,
]);
```

While you instantiate the client, when needed a new LetsEcrypt account is created and then agrees to the TOS.


### Create an order

To start retrieving certificates, we need to create an order first. This is done as follows:

```php
$order = $client->createOrder(['example.org', 'www.example.org']);
```

In the example above the primary domain is followed by a secondary domain(s). Make sure that for each domain you are 
able to prove ownership. As a result the certificate will be valid for all provided domains.

### Prove ownership

Before you can obtain a certificate for a given domain you need to prove that you own the given domain(s). In this 
example we will show you how to do this for http-01 validation (where serve specific content at a specific url on the
domain, like: `example.org/.well-known/acme-challenge/*`).

Obtain the authorizations for order. For each domain supplied in the create order request an authorization is returned.

```php
$authorizations = $client->authorize($order);
```


You now have an array of `Authorization` objects. These have the challenges you can use (both `DNS` and `HTTP`) to 
provide proof of ownership.

Use the following example to get the HTTP validation going. First obtain the challenges, the next step is to make the 
challenges accessible from 
```php
foreach ($authorizations as $authorization) {
    $file = $authorization->getFile();
    file_put_contents($file->getFilename(), $file->getContents());   
}
```

Now that the challenges are in place and accessible through `example.org/.well-known/acme-challenge/*` we can request 
validation. 

### Request validation

Next step is to request validation of ownership. For each authorization (domain) we ask LetsEncrypt to verify the 
challenge. 

```php
foreach ($authorizations as $authorization) {
    if ($client->selfTest($authorization, Client::VALIDATION_HTTP)) {
        $client->validate($authorization->getHttpChallenge(), 15);
    }
   
}
```

The code above will first perform a self test and, if successful, will do 15 attempts to ask LetsEncrypt to validate the challenge (with 1 second intervals) and
retrieve an updated status (it might take Lets Encrypt a few seconds to validate the challenge).

### Get the certificate

Now to know if we can request a certificate for the order, test if the order is ready as follows:

```php
if ($client->isReady($order)) {
    //The validation was successful.
}
```

We now know validation was completed and can obtain the certificate. This is done as follows:

```php
$certificate = $client->getCertificate($order);
```

We now have the certificate, to store it on the filesystem:
```php
//Store the certificate and private key where you need it
file_put_contents('certificate.cert', $certificate->getCertificate());
file_put_contents('private.key', $certificate->getPrivateKey());
```

### Who is using it?

Are you using this package, would love to know. Please send a PR to enlist your project or company. 
- [Afosto SaaS BV](https://afosto.com)