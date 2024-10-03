# RockLoaders Module

The `RockLoaders` module is a ProcessWire module designed to easily add animated loading animations/spinners to your website.

## Adding Loaders

To add a new loader, use the `add` method and pass an array of loader configurations:

```php
// site/ready.php
rockloaders()->add([
  'name' => 'path/to/loader',
]);
```
