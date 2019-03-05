AEngine Slim Memory
====
Work with Key-Value storage by user-friendly interface.

#### Requirements
* PHP >= 7.0
* Slim >= 3.0.0

#### Supporting
* Memcache
* Redis

#### Installation
Run the following command in the root directory of your web project:
  
> `composer require aengine/slim-memory`

### Usage
Connect to the server  
Note: by default connect to Memcache
```php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Memory settings
        'memory' => [
            [
                'host'    => 'localhost',
                'port'    => '11211',
                'timeout' => 10,
                // additional can be passed options, server-role and pool name:
                // 'driver' => 'memcache', // or redis
            ]
        ],
```

Add function in DI by edit src/dependencies.php file
```php
// register memory plugin
$container['memory'] = function ($c) {
    $settings = $c->get('settings')['memory'];
    $mem = new AEngine\Slim\Memory\Mem($settings);

    return $mem;
};
```

Example read and write
```php
$app->get('/example-route', function ($request, $response, $args) {
    if ($this->memory->get('kilobyte', -1) < 0) {
        $this->memory->set('kilobyte', 1024);
    }
});
```

Write data to storage
```php
$this->memory->set('foo', 'bar');
```

Read data form storage
```php
$this->memory->get('foo', /* 'default value' */);

// -- or --

$this->memory->get('foo', function () {
    // some action, e.g. just return string
    return 'baz';
});
```

#### Get or Set Multiple (like a PSR-16)

```php
// set rows
$this->memory->setMultiple([
    'cat:0' => 'Kiki',
    'cat:1' => 'Lucky',
    'dog:0' => 'Bucks',
    'cat:2' => 'Simon',
    'dog:1' => 'Eugene',
    'cat:3' => 'Rocky',
], 3600, 'animal');

// get data
$animals = $this->memory->getMultiple(['cat:0', 'cat:1', 'dog:0', 'cat:2', 'dog:1', 'cat:3']);

// remove data
$this->memory->deleteMultiple(['cat:0', 'cat:1', 'dog:0', 'cat:2', 'dog:1', 'cat:3']);
```

#### Tags

```php
// set few rows
$this->memory->set('cat:0', 'Kiki', 3600, 'animal');
$this->memory->set('cat:1', 'Lucky', 3600, 'animal');
$this->memory->set('dog:0', 'Bucks', 3600, 'animal');
$this->memory->set('cat:2', 'Simon', 3600, 'animal');
$this->memory->set('dog:1', 'Eugene', 3600, 'animal');
$this->memory->set('cat:3', 'Rocky', 3600, 'animal');

// get data as array
$animal = $this->memory->getByTag('animal');

// remove data
$this->memory->deleteByTag('animal');
```

#### Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

#### License
The Orchid Memory is licensed under the MIT license. See [License File](LICENSE.md) for more information.
