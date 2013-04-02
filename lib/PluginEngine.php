<?php


class crumbs_PluginEngine {

  protected $plugins;
  protected $weightKeeper;
  protected $pluginLibrary;

  protected $finderPluginMethods = array();

  /**
   * @param array $plugins
   *   Plugins, not sorted.
   * @param crumbs_RuleWeightKeeper $weight_keeper
   *   The weight keeper
   */
  function __construct($plugins, $weight_keeper) {
    $this->plugins = $plugins;
    $this->weightKeeper = $weight_keeper;
    $this->pluginLibrary = new crumbs_PluginLibrary($plugins, $weight_keeper);
  }

  /**
   * Invoke all relevant plugins to find the parent for a given path.
   *
   * @param string $path
   * @param array $item
   */
  function findParent($path, $item, &$all_candidates = array(), &$best_candidate_key = NULL) {
    $plugin_methods = $this->pluginLibrary->routeFinderPluginMethods('findParent', $item['route']);
    $result = $this->find($plugin_methods, array($path, $item), function ($parent_raw) {
      return drupal_get_normal_path($parent_raw);
    }, $all_candidates, $best_candidate_key);
    return $result;
  }

  /**
   * Invoke all relevant plugins to find the title for a given path.
   *
   * @param string $path
   * @param array $item
   * @param array $breadcrumb
   */
  function findTitle($path, $item, $breadcrumb, &$all_candidates = array(), &$best_candidate_key = NULL) {
    $plugin_methods = $this->pluginLibrary->routeFinderPluginMethods('findTitle', $item['route']);
    $result = $this->find($plugin_methods, array($path, $item, $breadcrumb), function ($title_raw) {
      return $title_raw;
    }, $all_candidates, $best_candidate_key);
    return $result;
  }

  /**
   * Invoke all relevant plugins to find title or parent for a given path.
   *
   * @param array $plugin_methods
   * @param array $args
   * @param array &$all_candidates
   *   Collect information during the operation.
   * @param string &$best_candidate_key
   */
  protected function find($plugin_methods, $args, $process, &$all_candidates = array(), &$best_candidate_key = NULL) {
    $best_candidate = NULL;
    $best_candidate_weight = 999999;
    foreach ($plugin_methods as $plugin_key => $method) {
      $plugin = $this->plugins[$plugin_key];
      if ($plugin instanceof crumbs_MultiPlugin) {
        // That's a MultiPlugin
        $keeper = $this->weightKeeper->prefixedWeightKeeper($plugin_key);
        if ($best_candidate_weight <= $keeper->getSmallestWeight()) {
          return $best_candidate;
        }
        $candidates = call_user_func_array(array($plugin, $method), $args);
        if (!empty($candidates)) {
          foreach ($candidates as $candidate_key => $candidate_raw) {
            if (isset($candidate_raw)) {
              $candidate_weight = $keeper->findWeight($candidate_key);
              $candidate = $process($candidate_raw);
              $all_candidates["$plugin_key.$candidate_key"] = array($candidate_weight, $candidate_raw, $candidate);
              if ($best_candidate_weight > $candidate_weight && isset($candidate)) {
                $best_candidate = $candidate;
                $best_candidate_weight = $candidate_weight;
                $best_candidate_key = $candidate_key;
              }
            }
          }
        }
      }
      elseif ($plugin instanceof crumbs_MonoPlugin) {
        // That's a MonoPlugin
        $candidate_weight = $this->weightKeeper->findWeight($plugin_key);
        if ($best_candidate_weight <= $candidate_weight) {
          return $best_candidate;
        }
        $candidate_raw = call_user_func_array(array($plugin, $method), $args);
        if (isset($candidate_raw)) {
          $candidate = $process($candidate_raw);
          $all_candidates[$plugin_key] = array($candidate_weight, $candidate_raw, $candidate);
          if (isset($candidate)) {
            $best_candidate = $candidate;
            $best_candidate_weight = $candidate_weight;
            $best_candidate_key = $plugin_key;
          }
        }
      }
    }
    return $best_candidate;
  }
}
