Memory Component
====
Work with Key-Value storage via user-friendly interface.

#### Requirements
* PHP >= 7.0

#### Supporting
* Memcache
* Redis

#### Installation
Run the following command in the root directory of your web project:
  
> `composer require Alksily/memory`

### Usage
Connect to the server  
```php
Alksily\Memory\Mem::initialize([
    [
        'host'    => 'localhost',
        'port'    => '11211',
        'timeout' => 10,
        // additional can be passed options, server-role and pool name:
        // 'driver' => 'memcache', // or redis
    ]
]);
```

Write data to storage
```php
Alksily\Memory\Mem::set('foo', 'bar');
```

Read data form storage
```php
Alksily\Memory\Mem::get('foo', /* 'default value' */);

// -- or --

Alksily\Memory\Mem::get('foo', function () {
    // some action, e.g. just return string
    return 'baz';
});
```

#### Get or Set Multiple (like a PSR-16)

```php
// set rows
Alksily\Memory\Mem::setMultiple([
    'cat:0' => 'Kiki',
    'cat:1' => 'Lucky',
    'dog:0' => 'Bucks',
    'cat:2' => 'Simon',
    'dog:1' => 'Eugene',
    'cat:3' => 'Rocky',
], 3600, 'animal');

// get data
$animals = Alksily\Memory\Mem::getMultiple(['cat:0', 'cat:1', 'dog:0', 'cat:2', 'dog:1', 'cat:3']);

// remove data
Alksily\Memory\Mem::deleteMultiple(['cat:0', 'cat:1', 'dog:0', 'cat:2', 'dog:1', 'cat:3']);
```

#### Tags

```php
// set few rows
Alksily\Memory\Mem::set('cat:0', 'Kiki', 3600, 'animal');
Alksily\Memory\Mem::set('cat:1', 'Lucky', 3600, 'animal');
Alksily\Memory\Mem::set('dog:0', 'Bucks', 3600, 'animal');
Alksily\Memory\Mem::set('cat:2', 'Simon', 3600, 'animal');
Alksily\Memory\Mem::set('dog:1', 'Eugene', 3600, 'animal');
Alksily\Memory\Mem::set('cat:3', 'Rocky', 3600, 'animal');

// get data as array
$animal = Alksily\Memory\Mem::getByTag('animal');

// remove data
Alksily\Memory\Mem::deleteByTag('animal');
```

#### Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

#### License
The Alksily Memory is licensed under the MIT license. See [License File](LICENSE.md) for more information.
