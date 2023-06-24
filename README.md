# yaac - Yet another ACME client

Written in PHP, this client aims to be a simplified and decoupled Let’s Encrypt client, based on [ACME V2](https://tools.ietf.org/html/rfc8555).

## Decoupled from a filesystem or webserver

Instead of, for example writing the certificate to the disk under an nginx configuration, this client just returns the 
data (the certificate and private key).

## Why

Why would I need this package? At Afosto we run our software in a multi-tenant setup, as any other SaaS would do, and
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

To start the client you need 3 things; a username for your Let’s Encrypt account, a bootstrapped Flysystem and you need to 
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

While you instantiate the client, when needed a new Let’s Encrypt account is created and then agrees to the TOS.


### Create an order

To start retrieving certificates, we need to create an order first. This is done as follows:

```php
$order = $client->createOrder(['example.org', 'www.example.org']);
```

In the example above the primary domain is followed by a secondary domain(s). Make sure that for each domain you are 
able to prove ownership. As a result the certificate will be valid for all provided domains.


### Prove ownership

Before you can obtain a certificate for a given domain you need to prove that you own the given domain(s).
We request the authorizations to prove ownership. Obtain the authorizations for order. For each domain supplied in the 
create order request an authorization is returned.                                     
```php
$authorizations = $client->authorize($order);
```
You now have an array of `Authorization` objects. These have the challenges you can use (both `DNS` and `HTTP`) to 
provide proof of ownership. 


#### HTTP validation

HTTP validation (where serve specific content at a specific url on the domain, like: 
`example.org/.well-known/acme-challenge/*`) is done as follows:

Use the following example to get the HTTP validation going. First obtain the challenges, the next step is to make the 
challenges accessible from 
```php
foreach ($authorizations as $authorization) {
    $file = $authorization->getFile();
    file_put_contents($file->getFilename(), $file->getContents());   
}
```

> If you need a wildcard certificate, you will need to use DNS validation, see below


#### DNS validation

You can also use DNS validation - to do this, you will need access to an API of your DNS 
provider to create TXT records for the target domains.

```php
foreach ($authorizations as $authorization) {
    $txtRecord = $authorization->getTxtRecord();
    
    //To get the name of the TXT record call:
    $txtRecord->getName();

    //To get the value of the TXT record call:
    $txtRecord->getValue();
}
```


### Self test

After exposing the challenges (made accessible through HTTP or DNS) we should perform a self test just to 
be sure it works before asking Let's Encrypt to validate ownership.

For a HTTP challenge test call:
```php
if (!$client->selfTest($authorization, Client::VALIDATION_HTTP)) {
    throw new \Exception('Could not verify ownership via HTTP');
}
```

For a DNS test call:

```php
if (!$client->selfTest($authorization, Client::VALIDATION_DNS)) {
    throw new \Exception('Could not verify ownership via DNS');
}
sleep(30); // this further sleep is recommended, depending on your DNS provider, see below
``` 

With DNS validation, after the `selfTest` has confirmed that DNS has been updated, it is 
recommended you wait some additional time before proceeding, e.g. `sleep(30);`. This is
because Let’s Encrypt will perform [multiple viewpoint validation](https://community.letsencrypt.org/t/acme-v1-v2-validating-challenges-from-multiple-network-vantage-points/112253),
and your DNS provider may not have completed propagating the changes across their network. 

If you proceed too soon, [Let's Encrypt will fail to validate](https://community.letsencrypt.org/t/during-secondary-validation-incorrect-txt-record/113643).


### Request validation

Next step is to request validation of ownership. For each authorization (domain) we ask Let’s Encrypt to verify the 
challenge. 

For HTTP validation:
```php
foreach ($authorizations as $authorization) {
    $client->validate($authorization->getHttpChallenge(), 15);
}
```

For DNS validation:
```php
foreach ($authorizations as $authorization) {
    $client->validate($authorization->getDnsChallenge(), 15);
}
```

The code above will first perform a self test and, if successful, will do 15 attempts to ask Let’s Encrypt to validate the challenge (with 1 second intervals) and
retrieve an updated status (it might take Let’s Encrypt a few seconds to validate the challenge).


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

>To get a seperate intermediate certificate and domain certificate:
>```php
>$domainCertificate = $certificate->getCertificate(false);
>$intermediateCertificate = $certificate->getIntermediate();
>```

### Who is using it?

Are you using this package, would love to know. Please send a PR to enlist your project or company. 
- [Afosto SaaS BV](https://afosto.com)
- [Aitrex - Free Let's Encrypt SSL Certificate Generator](https://aitrex.com/freessl.php)
- [Web Whales](https://webwhales.nl)
- [do.de](https://www.do.de)
- [punchsalad.com](https://punchsalad.com/ssl-certificate-generator/)
- [Spreadly](https://spreadly.app)
- [SslForWeb](https://sslforweb.com)
