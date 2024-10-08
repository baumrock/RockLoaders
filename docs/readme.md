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

The path should be relative to the PW root folder!

### Example

Let's say you want to add the loader "foo" which has its files in `/site/templates/loaders/foo.less|html`.

```php
// site/ready.php
rockloaders()->add([
  'foo' => 'site/templates/loaders',
]);
```

### Short Syntax

For all loaders shipped with this module, you can use the short syntax by just passing the loader name:

```php
// site/ready.php
rockloaders()->add('email');
```

## Showing a Loader

To show a loader, all you need to do is add the `rockloader` attribute to the body tag of your page:

```html
<body rockloader="email">
  <!-- your content -->
</body>
```

Usually you'd do this via JavaScript:

```js
document.body.setAttribute('rockloader', 'email');
setTimeout(() => {
  document.body.removeAttribute('rockloader');
}, 2000);
```

## Troubleshooting

If anything does not work as expected, check the file `/site/assets/rockloaders.min.css`. This is the CSS generated by this module and it should contain the CSS for all added loaders.

If that file is not correct, please delete it and reload your page. This should trigger a recompile of the file.
