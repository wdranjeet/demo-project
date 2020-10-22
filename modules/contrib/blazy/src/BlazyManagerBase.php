<?php

namespace Drupal\blazy;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements BlazyManagerInterface.
 */
abstract class BlazyManagerBase implements BlazyManagerInterface {

  // Fixed for EB AJAX issue: #2893029.
  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The app root.
   *
   * @var \SplString
   */
  protected $root;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The blazy IO settings.
   *
   * @var object
   */
  protected $ioSettings;

  /**
   * Checks if the image style contains crop in the effect name.
   *
   * @var array
   */
  protected $isCrop;

  /**
   * Returns available styles with crop in the effect name.
   *
   * @var array
   */
  protected $cropStyles;

  /**
   * Constructs a BlazyManager object.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache) {
    $this->entityRepository  = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler     = $module_handler;
    $this->renderer          = $renderer;
    $this->configFactory     = $config_factory;
    $this->cache             = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('cache.default')
    );

    // @todo remove and use DI at 2.x+ post sub-classes updates.
    $instance->setRoot($container->get('app.root'));
    return $instance;
  }

  /**
   * Returns the app root.
   */
  public function root() {
    return $this->root;
  }

  /**
   * Sets app root service.
   *
   * @todo remove and use DI at 2.x+ post sub-classes updates.
   */
  public function setRoot($root) {
    $this->root = $root;
    return $this;
  }

  /**
   * Returns the entity repository service.
   */
  public function getEntityRepository() {
    return $this->entityRepository;
  }

  /**
   * Returns the entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Returns the module handler.
   */
  public function getModuleHandler() {
    return $this->moduleHandler;
  }

  /**
   * Returns the renderer.
   */
  public function getRenderer() {
    return $this->renderer;
  }

  /**
   * Returns the config factory.
   */
  public function getConfigFactory() {
    return $this->configFactory;
  }

  /**
   * Returns the cache.
   */
  public function getCache() {
    return $this->cache;
  }

  /**
   * Returns any config, or keyed by the $setting_name.
   */
  public function configLoad($setting_name = '', $settings = 'blazy.settings') {
    $config  = $this->configFactory->get($settings);
    $configs = $config->get();
    unset($configs['_core']);
    return empty($setting_name) ? $configs : $config->get($setting_name);
  }

  /**
   * Returns a shortcut for loading a config entity: image_style, slick, etc.
   */
  public function entityLoad($id, $entity_type = 'image_style') {
    return $this->entityTypeManager->getStorage($entity_type)->load($id);
  }

  /**
   * Returns a shortcut for loading multiple configuration entities.
   */
  public function entityLoadMultiple($entity_type = 'image_style', $ids = NULL) {
    return $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function attach(array $attach = []) {
    $load   = [];
    $switch = empty($attach['media_switch']) ? '' : $attach['media_switch'];

    if ($switch && $switch != 'content') {
      $attach[$switch] = $switch;

      if (in_array($switch, $this->getLightboxes())) {
        $load['library'][] = 'blazy/lightbox';

        if (!empty($attach['colorbox'])) {
          BlazyAlter::attachColorbox($load, $attach);
        }
      }
    }

    // Allow both variants of grid or column to co-exist for different fields.
    if (!empty($attach['style'])) {
      $attach[$attach['style']] = $attach['style'];
    }

    if (!empty($attach['fx']) && $attach['fx'] == 'blur') {
      $load['library'][] = 'blazy/fx.blur';
    }

    foreach (['column', 'filter', 'grid', 'media', 'photobox', 'ratio'] as $component) {
      if (!empty($attach[$component])) {
        $load['library'][] = 'blazy/' . $component;
      }
    }

    // Allows Blazy libraries to be disabled by a special flag _unblazy.
    if (empty($attach['_unblazy'])) {
      $load['library'][] = 'blazy/load';
      $load['drupalSettings']['blazy'] = $this->configLoad('blazy');
      $load['drupalSettings']['blazyIo'] = $this->getIoSettings($attach);
    }

    // Adds AJAX helper to revalidate Blazy/ IO, if using VIS, or alike.
    if (!empty($attach['use_ajax'])) {
      $load['library'][] = 'blazy/bio.ajax';
    }

    $this->moduleHandler->alter('blazy_attach', $load, $attach);
    return $load;
  }

  /**
   * {@inheritdoc}
   */
  public function getIoSettings(array $attach = []) {
    if (!isset($this->ioSettings)) {
      $thold = trim($this->configLoad('io.threshold')) ?: '0';
      $number = strpos($thold, '.') !== FALSE ? (float) $thold : (int) $thold;
      $thold = strpos($thold, ',') !== FALSE ? array_map('trim', explode(',', $thold)) : [$number];

      // Respects hook_blazy_attach_alter() for more fine-grained control.
      foreach (['enabled', 'disconnect', 'rootMargin', 'threshold'] as $key) {
        $default = $key == 'rootMargin' ? '0px' : FALSE;
        $value = $key == 'threshold' ? $thold : $this->configLoad('io.' . $key);
        $io[$key] = isset($attach['io.' . $key]) ? $attach['io.' . $key] : ($value ?: $default);
      }

      $this->ioSettings = (object) $io;
    }

    return $this->ioSettings;
  }

  /**
   * Returns the common UI settings inherited down to each item.
   */
  public function getCommonSettings(array &$settings) {
    $settings += array_intersect_key($this->configLoad(), BlazyDefault::uiSettings());
    $settings['media_switch'] = $switch = empty($settings['media_switch']) ? '' : $settings['media_switch'];
    $settings['iframe_domain'] = $this->configLoad('iframe_domain', 'media.settings');
    $settings['is_preview'] = Blazy::isPreview();
    $settings['lightbox'] = ($switch && in_array($switch, $this->getLightboxes())) ? $switch : FALSE;
    $settings['namespace'] = empty($settings['namespace']) ? 'blazy' : $settings['namespace'];
    $settings['route_name'] = Blazy::routeMatch() ? Blazy::routeMatch()->getRouteName() : '';

    if ($switch) {
      // Allows lightboxes to provide its own optionsets, e.g.: ElevateZoomPlus.
      $settings[$switch] = empty($settings[$switch]) ? $switch : $settings[$switch];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLightboxes() {
    $lightboxes = [];
    foreach (['colorbox', 'photobox'] as $lightbox) {
      if (function_exists($lightbox . '_theme')) {
        $lightboxes[] = $lightbox;
      }
    }

    if (is_file($this->root . '/libraries/photobox/photobox/jquery.photobox.js')) {
      $lightboxes[] = 'photobox';
    }

    $this->moduleHandler->alter('blazy_lightboxes', $lightboxes);
    return array_unique($lightboxes);
  }

  /**
   * {@inheritdoc}
   */
  public function getImageEffects() {
    $effects[] = 'blur';

    $this->moduleHandler->alter('blazy_image_effects', $effects);
    $effects = array_unique($effects);
    return array_combine($effects, $effects);
  }

  /**
   * {@inheritdoc}
   */
  public function isBlazy(array &$settings, array $item = []) {
    // Retrieves Blazy formatter related settings from within Views style.
    $content = !empty($settings['item_id']) && isset($item[$settings['item_id']]) ? $item[$settings['item_id']] : $item;
    $image = isset($item['item']) ? $item['item'] : NULL;
    // @todo replace first_item with _item for consistency with _width, _height.
    $settings['first_item'] = $image;

    // 1. Blazy formatter within Views fields by supported modules.
    if (isset($item['settings'])) {
      $blazy = $item['settings'];

      // Merge the first found (Responsive) image data.
      if (!empty($blazy['blazy_data'])) {
        $settings['blazy_data'] = empty($settings['blazy_data']) ? $blazy['blazy_data'] : array_merge($settings['blazy_data'], $blazy['blazy_data']);
        $settings['_dimensions'] = !empty($settings['blazy_data']['dimensions']);
      }

      $cherries = BlazyDefault::cherrySettings() + ['uri' => ''];
      foreach ($cherries as $key => $value) {
        $fallback = isset($settings[$key]) ? $settings[$key] : $value;
        $settings[$key] = isset($blazy[$key]) && empty($fallback) ? $blazy[$key] : $fallback;
      }

      // @todo replace first_uri with _uri for consistency with _width, _height.
      $uri = empty($settings['first_uri']) ? $settings['uri'] : $settings['first_uri'];
      $settings['_uri'] = $settings['first_uri'] = empty($settings['_uri']) ? $uri : $settings['_uri'];
      unset($settings['uri']);
    }

    // 2. Blazy Views fields by supported modules.
    // Prevents edge case with unexpected flattened Views results which is
    // normally triggered by checking "Use field template" option.
    if (is_array($content) && isset($content['#view']) && ($view = $content['#view'])) {
      if ($blazy_field = BlazyViews::viewsField($view)) {
        $settings = array_merge(array_filter($blazy_field->mergedViewsSettings()), array_filter($settings));
      }
    }

    unset($settings['first_image']);
  }

  /**
   * Return the cache metadata common for all blazy-related modules.
   */
  public function getCacheMetadata(array $build = []) {
    $settings          = isset($build['settings']) ? $build['settings'] : $build;
    $max_age           = $this->configLoad('cache.page.max_age', 'system.performance');
    $max_age           = empty($settings['cache']) ? $max_age : $settings['cache'];
    $id                = isset($settings['id']) ? $settings['id'] : Blazy::getHtmlId($settings['namespace']);
    $suffixes[]        = empty($settings['count']) ? count(array_filter($settings)) : $settings['count'];
    $cache['tags']     = Cache::buildTags($settings['namespace'] . ':' . $id, $suffixes, '.');
    $cache['contexts'] = ['languages'];
    $cache['max-age']  = $max_age;
    $cache['keys']     = isset($settings['cache_metadata']['keys']) ? $settings['cache_metadata']['keys'] : [$id];

    if (!empty($settings['cache_tags'])) {
      $cache['tags'] = Cache::mergeTags($cache['tags'], $settings['cache_tags']);
    }

    return $cache;
  }

  /**
   * Returns available image styles with crop in the name.
   */
  public function cropStyles() {
    if (!isset($this->cropStyles)) {
      $this->cropStyles = [];
      foreach ($this->entityLoadMultiple('image_style') as $style) {
        foreach ($style->getEffects() as $effect) {
          if (strpos($effect->getPluginId(), 'crop') !== FALSE) {
            $this->cropStyles[$style->getName()] = $style;
            break;
          }
        }
      }
    }
    return $this->cropStyles;
  }

  /**
   * {@inheritdoc}
   */
  public function isCrop($style) {
    if (!isset($this->isCrop[$style])) {
      $this->isCrop[$style] = $this->cropStyles() && isset($this->cropStyles()[$style]) ? $this->cropStyles()[$style] : FALSE;
    }
    return $this->isCrop[$style];
  }

  /**
   * Collects defined skins as registered via hook_MODULE_NAME_skins_info().
   *
   * @todo deprecate for sub-modules own skins as plugins at blazy:8.x-2+.
   * @see https://www.drupal.org/node/2233261
   * @see https://www.drupal.org/node/3105670
   */
  public function buildSkins($namespace, $skin_class, $methods = []) {
    $cid = $namespace . ':skins';

    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    $classes = $this->moduleHandler->invokeAll($namespace . '_skins_info');
    $classes = array_merge([$skin_class], $classes);
    $items   = $skins = [];
    foreach ($classes as $class) {
      if (class_exists($class)) {
        $reflection = new \ReflectionClass($class);
        if ($reflection->implementsInterface($skin_class . 'Interface')) {
          $skin = new $class();
          if (empty($methods) && method_exists($skin, 'skins')) {
            $items = $skin->skins();
          }
          else {
            foreach ($methods as $method) {
              $items[$method] = method_exists($skin, $method) ? $skin->{$method}() : [];
            }
          }
        }
      }
      $skins = NestedArray::mergeDeep($skins, $items);
    }

    $count = isset($items['skins']) ? count($items['skins']) : count($items);
    $tags  = Cache::buildTags($cid, ['count:' . $count]);

    $this->cache->set($cid, $skins, Cache::PERMANENT, $tags);

    return $skins;
  }

}
