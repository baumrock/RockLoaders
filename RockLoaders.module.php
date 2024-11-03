<?php

namespace ProcessWire;

/**
 * @author Bernhard Baumrock, 03.10.2024
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */

function rockloaders(): RockLoaders
{
  return wire()->modules->get('RockLoaders');
}

class RockLoaders extends WireData implements Module, ConfigurableModule
{
  const compress = true;
  const forceRecompile = false;

  public $cssfile;
  private $loaders = [];

  public function init()
  {
    $this->cssfile = wire()->config->paths->assets . 'rockloaders.min.css';

    // add checked internal loaders
    foreach ($this->internalLoaders as $key) $this->add($key);

    wire()->addHookBefore('Page::render', $this, 'compileLoaders');
    wire()->addHookAfter('Modules::refresh', $this, 'clearCache');
    wire()->addHookAfter('Page::render', $this, 'addMarkupHook');
    wire()->addHookBefore('RockLoaders::addMarkup', $this, 'addMarkupForDemo');
  }

  /**
   * Attach a loader
   *
   * Usage (regular syntax):
   * rockloaders()->add(['name' => '/path/to/files']);
   *
   * Short syntax (for loaders of this module):
   * rockloaders()->add('email');
   *
   * @param array|string $loaders
   * @return void
   */
  public function add(array|string $loaders): void
  {
    // if loader is provided as string, we convert it into an array
    // looking for the loader in the /loaders folder of this module
    if (is_string($loaders)) {
      $loaders = [$loaders => __DIR__ . "/loaders"];
    }

    // loop through all loaders and sanitize the path
    foreach ($loaders as $key => $path) {
      // sanitize path
      // we add prefix / to support usage of "site/templates/..."
      $path = Paths::normalizeSeparators($path);
      $path = '/' . trim($path, '/') . "/$key";

      // turn path into array and remove all before "site"
      $parts = array_filter(explode('/', $path));
      $path = array_slice($parts, array_search('site', $parts) - 1);
      $path = implode('/', $path);

      $this->loaders[$key] = $path;
    }

    // sort loaders by key
    ksort($this->loaders);
  }

  public function addAll(string $dir): void
  {
    foreach (glob($dir . "/*.less") as $file) {
      $name = substr(basename($file), 0, -5);
      $dir = dirname($file);
      $this->add([$name => $dir]);
    }
  }

  /**
   * Add markup to given page?
   * See docs how you can modify this method via hook.
   */
  public function ___addMarkup(Page $page): bool
  {
    $config = wire()->config;
    if ($config->noRockLoadersMarkup) return false;
    if ($config->ajax) return false;
    if ($config->external) return false;
    if (!$page->viewable()) return false;
    if ($page->template == 'admin') return false;
    return true;
  }

  /**
   * Hook to add markup to the rockloaders settings page
   */
  protected function addMarkupForDemo(HookEvent $event): void
  {
    $page = $event->arguments(0);
    if ($page->id !== 21) return;
    if (wire()->input->name !== 'RockLoaders') return;
    $event->return = true; // set addMarkup to true
    $event->replace = true; // replace original method
  }

  /**
   * Hook to inject markup into the pages html
   */
  protected function addMarkupHook(HookEvent $event): void
  {
    $page = $event->object;
    $html = $event->return;

    // add markup?
    if ($this->addMarkup($page)) {
      $loaders = $this->getMarkup();
      $html = str_replace(
        '</body>',
        $loaders . '</body>',
        $html
      );
    }

    // add stylesheet?
    if ($this->addStylesheet($page)) {
      $file = $this->cssfile;
      $url = str_replace(
        wire()->config->paths->root,
        wire()->config->urls->root,
        $file
      );
      $url = wire()->config->versionUrl($url);
      $markup = "<link rel='stylesheet' href='$url' defer>";
      $html = str_replace(
        '</head>',
        $markup . '</head>',
        $html
      );
    }

    $event->return = $html;
  }

  public function ___addStylesheet(Page $page): bool
  {
    return $this->addMarkup($page);
  }

  /**
   * Clear cache on modules refresh to force recompile
   */
  protected function clearCache(HookEvent $event): void
  {
    wire()->cache->delete("rockloaders");
  }

  /**
   * Compile loaders to CSS
   */
  protected function compileLoaders(HookEvent $event): void
  {
    if (!self::forceRecompile) {
      if (wire()->config->debug) {
        // if debug mode is on we use filemtime to check if the loaders have changed
        if (!$this->filesChanged()) return;
      } else {
        // on production we compare added loaders from cache
        $new = implode(',', array_keys($this->loaders));
        $old = wire()->cache->get("rockloaders");

        // no change, nothing to do
        if ($new === $old) return;

        // save new loaders to cache
        wire()->cache->save("rockloaders", $new);
      }
    }

    // recompile loader css
    // bd('compile');
    $less = wire()->modules->get('Less');
    $less->setOption('compress', self::compress);
    $less->addStr($this->getCSS());
    wire()->files->filePutContents($this->cssfile, $less->getCss());
    wire()->session->forceRockLoadersRecompile = false;
  }

  public function __debugInfo(): array
  {
    return [
      'loaders' => $this->loaders,
    ];
  }

  /**
   * Check if any file has changed
   */
  private function filesChanged(): bool
  {
    if (wire()->session->forceRockLoadersRecompile) {
      return true;
    }

    // if css file does not exist, we need to compile
    if (!is_file($this->cssfile)) return true;
    $mCSS = filemtime($this->cssfile);
    // loop attached loaders and check if any file has changed
    foreach ($this->loaders as $key => $path) {
      foreach (['html', 'less'] as $ext) {
        $file = "$path.$ext";
        if (!is_file($file)) continue;
        $m = filemtime($file);
        if ($m > $mCSS) return true;
      }
    }

    // check stubs folder
    foreach (glob(__DIR__ . '/stubs/*') as $file) {
      if (filemtime($file) > $mCSS) return true;
    }

    // no changes
    return false;
  }

  private function getCSS(): string
  {
    $str = wire()->files->fileGetContents(__DIR__ . '/stubs/prefix.less');

    // for every loader add some rules
    foreach ($this->loaders as $key => $path) {
      $file = wire()->config->paths->root . $path . ".less";
      if (!is_file($file)) continue;
      $content = wire()->files->fileGetContents("$path.less");
      $attr = "[rockloader='$key']";
      $str .= "body$attr div$attr { opacity: 1; pointer-events: all; }";
      $str .= "div$attr { $content }";
    }

    // convert less to css
    $less = wire()->modules->get('Less');
    $less->setOption('compress', self::compress);
    $less->addStr($str);
    $css = $less->getCss();
    // bd($css);

    return $css;
  }

  /**
   * Get markup that will be injected into the page
   */
  public function getMarkup(): string
  {
    // add html for every loader
    $loaders = '';
    foreach ($this->loaders as $key => $path) {
      $htmlFile = "$path.html";
      if (!is_file($htmlFile)) $htmlFile = __DIR__ . '/stubs/loader.html';
      $html = wire()->files->fileGetContents($htmlFile);
      $html = "<div rockloader='$key'>$html</div>";
      $loaders .= $html;
    }
    return $loaders;
  }

  public function getModuleConfigInputfields($inputfields)
  {
    // reset cache when visiting this page
    if (wire()->input->post->submit_save_module) {
      wire()->session->forceRockLoadersRecompile = true;
    }

    $name = strtolower($this);
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Documentation & Updates',
      'icon' => 'life-ring',
      'value' => "<p>Hey there, coding rockstars! 👋</p>
      <ul>
        <li><a href=https://www.baumrock.com/en/processwire/modules/$name/docs>Read the docs</a> and level up your coding game! 🚀💻😎</li>
        <li><a href=https://github.com/baumrock/$name>Show some love by starring the project</a> and keep us motivated to build more awesome stuff for you! 🌟💻😊</li>
        <li><a href=https://www.baumrock.com/rock-monthly>Sign up now for our monthly newsletter</a> and receive the latest updates and exclusive offers right to your inbox! 🚀💻📫</li>
      </ul>
      <div>The development of this module has been driven by my commercial modules <a href='https://www.baumrock.com/processwire/module/rockcommerce/'>RockCommerce</a>, <a href='https://www.baumrock.com/processwire/module/rockforms/'>RockForms</a> and <a href='https://www.baumrock.com/processwire/module/rockgrid/'>RockGrid</a>. If you want to support my work, please consider to buy one of my modules. Thank you! 🤗</div>",
    ]);

    // step 1
    $fs = new InputfieldFieldset();
    $fs->label = 'Step 1: Attach Loaders';
    $inputfields->add($fs);

    $f = new InputfieldCheckboxes();
    $f->name = 'internalLoaders';
    $f->label = 'Internal Loaders';
    foreach ($this->lessArray() as $name) {
      $f->addOption($name);
    }
    $f->value = $this->internalLoaders;
    $f->notes = 'Note: Internal loaders can also be loaded via API short syntax: `rockloaders()->add("email")`';
    $fs->add($f);

    $fs->add([
      'type' => 'markup',
      'label' => 'External Loaders',
      'value' => '<p>Add external loaders by adding the following to your ready.php file:</p>
      <pre class="uk-margin-small-top uk-margin-remove-bottom">rockloaders()->add(["name" => "path/to/loader"]);</pre>',
      'notes' => 'To update the CSS, you need to save this page!',
    ]);

    // usage
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Step 2: Add loader to your DOM via JavaScript',
      'value' => 'To show a loadeing animation simply add <em>rockloader=xxx</em> to the body tag of your website, where xxx is the name of the loader you want to show:
      <pre class="uk-margin-small-top uk-margin-remove-bottom">' .
        'document.body.setAttribute("rockloader", "rocket");' .
        "\n" . 'setTimeout(() => document.body.removeAttribute("rockloader"), 2000);' .
        '</pre>',
    ]);

    // attached loaders
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Attached Loaders',
      'description' => 'DEMO: Click on a loader to see it in action.',
      'value' => $this->loadersTable(),
      'notes' => 'Use `rockloaders()->add(["name" => "path/to/loader"])` to attach more loaders.
        Or use `rockloaders()->addAll("path/to/loaders")` to attach all loaders in a directory.',
    ]);

    // resources
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Resources',
      'value' => '<p>CSS Loaders that work with this module:</p><ul>
        <li><a href="https://cssloaders.github.io/" target="_blank">cssloaders.github.io</a></li>
        <li><a href="https://www.cssportal.com/css-loader-generator/" target="_blank">cssportal.com/css-loader-generator</a></li>
        </ul>',
      'notes' => 'ATTENTION: You need to make sure that the keyframes definitions are Know more? Let me know!',
    ]);

    return $inputfields;
  }

  private function lessArray(): array
  {
    $arr = [];
    foreach (glob(__DIR__ . "/loaders/*.less") as $file) {
      $arr[] = substr(basename($file), 0, -5);
    }
    return $arr;
  }

  /**
   * Get table with loaders for settings page
   */
  private function loadersTable(): string
  {
    if (!count($this->loaders)) {
      return "No loaders attached. Check the checkbox above and save the page to try out the loaders that are shipped with this module.";
    }
    $html = '<div class="uk-overflow-auto"><table class="uk-table uk-table-small uk-table-striped">';
    $html .= '<thead><tr><th>Name</th><th>Path & Setup</th></tr></thead>';
    foreach ($this->loaders as $key => $path) {
      $dir = dirname($path);
      $html .= "<tr>
        <td class=uk-text-nowrap><a href class=demo>{$key}</a></td>
        <td class=uk-text-nowrap>
        <div>{$path} <span class='uk-text-small uk-text-muted'>[.html/.less]</span></div>
        <pre class='uk-margin-small-top uk-margin-remove-bottom' style='font-size:0.75em'>rockloaders()->add(['$key' => '$dir']);</pre>
        </td>
      </tr>";
    }
    $html .= '</table></div>
    <script>
    // on click of .demo add class .rockloaders-[key] to body
    $("a.demo").on("click", function(e) {
      e.preventDefault();
      const key = $(this).text();
      $("body").attr("rockloader", key);
      setTimeout(() => {
        $("body").removeAttr("rockloader");
      }, 2000);
    });
    </script>
    ';
    return $html;
  }
}
