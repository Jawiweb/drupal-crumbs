<?php


/**
 * This class uses the PluginOperation pattern, but it does not implement any of
 * the PluginOperation interfaces. This is because it is not supposed to be used
 * with the PluginEngine, but rather from a custom function (see above).
 */
class crumbs_PluginOperation_describe {

  // Collected data
  protected $keys = array('*' => TRUE);
  protected $keysByPlugin = array();
  protected $collectedInfo = array();

  // State variables
  protected $pluginKey;
  protected $injectedAPI_mono;
  protected $injectedAPI_multi;

  function __construct() {
    $this->injectedAPI_mono = new crumbs_InjectedAPI_describeMonoPlugin($this);
    $this->injectedAPI_multi = new crumbs_InjectedAPI_describeMultiPlugin($this);
  }

  /**
   * To be called from _crumbs_load_available_keys()
   */
  function invoke($plugin, $plugin_key) {
    $this->pluginKey = $plugin_key;

    $basic_methods = array();
    $route_methods = array();
    $rf_class = new ReflectionClass($plugin);
    foreach ($rf_class->getMethods() as $rf_method) {
      $method = $rf_method->name;
      $pos = strpos($method, '__');
      if (FALSE !== $pos && 0 !== $pos) {
        $method_base = substr($method, 0, $pos);
        if (in_array($method_base, array('findParent', 'findTitle'))) {
          $method_suffix = substr($method, $pos + 2);
          $route = crumbs_Util::routeFromMethodSuffix($method_suffix);
          $route_methods[$method_base][$route] = $method;
        }
      }
      else {
        if (in_array($method, array('findParent', 'findTitle', 'decorateBreadcrumb'))) {
          $basic_methods[$method] = $method;
        }
      }
    }

    if ($plugin instanceof crumbs_MonoPlugin) {
      $this->collectedInfo['basicMethods'][$plugin_key] = $basic_methods;
      $this->collectedInfo['routeMethods'][$plugin_key] = $route_methods;
      $result = $plugin->describe($this->injectedAPI_mono);
      if (is_string($result)) {
        $this->setTitle($result);
      }
    }
    elseif ($plugin instanceof crumbs_MultiPlugin) {
      $this->collectedInfo['basicMethods']["$plugin_key.*"] = $basic_methods;
      $this->collectedInfo['routeMethods']["$plugin_key.*"] = $route_methods;
      $result = $plugin->describe($this->injectedAPI_multi);
      if (is_array($result)) {
        foreach ($result as $key_suffix => $title) {
          $this->addRule($key_suffix, $title);
        }
      }
    }
  }

  /**
   * To be called from crumbs_InjectedAPI_describeMultiPlugin::addRule()
   */
  function addRule($key_suffix, $title) {
    $key = $this->pluginKey . '.' . $key_suffix;
    $this->_addRule($key);
    $this->_addDescription($key, $title );
  }

  /**
   * Add a description at an arbitrary wildcard key.
   * To be called from crumbs_InjectedAPI_describeMultiPlugin::addDescription()
   */
  function addDescription($description, $key_suffix) {
    if (isset($key_suffix)) {
      $key = $this->pluginKey . '.' . $key_suffix;
    }
    else {
      $key = $this->pluginKey;
    }
    $this->_addDescription($key, $description);
  }

  function _addDescription($key, $description) {
    $this->collectedInfo['descriptions'][$key][] = $description;
  }

  /**
   * To be called from crumbs_InjectedAPI_describeMonoPlugin::setTitle()
   */
  function setTitle($title) {
    $this->_addRule($this->pluginKey);
    $this->_addDescription($this->pluginKey, $title);
  }

  protected function _addRule($key) {
    $fragments = explode('.', $key);
    $partial_key = array_shift($fragments);
    while (TRUE) {
      if (empty($fragments)) break;
      $wildcard_key = $partial_key .'.*';
      $this->keys[$wildcard_key] = TRUE;
      $this->keysByPlugin[$this->pluginKey][$wildcard_key] = TRUE;
      $partial_key .= '.'. array_shift($fragments);
    }
    $this->keys[$key] = $key;
    $this->keysByPlugin[$this->pluginKey][$key] = $key;
  }

  function getKeys() {
    return $this->keys;
  }

  function getKeysByPlugin() {
    return $this->keysByPlugin;
  }

  function pluginInfo($plugin_key) {
    return $this->pluginInfo[$plugin_key];
  }

  function collectedInfo() {
    $container = new crumbs_Container_MultiWildcardData($this->keys);
    $container->__set('key', $this->keys);
    foreach ($this->collectedInfo as $key => $data) {
      $container->__set($key, $data);
    }
    return $container;
  }
}
