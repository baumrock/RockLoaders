# Markup

By default RockLoaders will inject the necessary markup after `Page::render`. It does that with several conditions, for example it will not add markup on AJAX calls. For all conditions please refer to the source code of the `___addMarkup` method in `RockLoaders.module.php`.

## Disable Markup Injection

As you can see from the `___addMarkup` method, there is a condition that checks if `$config->noRockLoadersMarkup` is true. This is a config setting that you can set to true to disable the markup injection.

```php
// site/config.php
$config->noRockLoadersMarkup = true;
```

## Disable/Enable Markup Injection on a Specific Page

If you want to disable the markup injection on a specific page, you can use a hook. We are using this tequnique to add loaders to the settings page of the module:

```php
wire()->addHookBefore(
  'RockLoaders::addMarkup',
  function (HookEvent $event) {
    $page = $event->arguments(0);

    // only apply to module pages
    if ($page->id !== 21) return;

    // only apply to module page of RockLoaders
    if (wire()->input->name !== 'RockLoaders') return;

    // set addMarkup to true
    $event->return = true;

    // replace original method
    // otherwise the original method will be called after this hook
    // and reset our changes
    $event->replace = true;
  }
);
```
