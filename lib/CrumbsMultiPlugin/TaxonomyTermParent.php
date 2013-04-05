<?php

class crumbs_CrumbsMultiPlugin_TaxonomyTermParent extends crumbs_CrumbsMultiPlugin_EntityParentAbstract {

  function describe($api) {
    return $this->describeGeneric($api, 'taxonomy_term', t('Vocabulary'));
  }

  /**
   * Find candidates for the parent path.
   *
   * @param string $path
   *   The path that we want to find a parent for.
   * @param array $item
   *   Item as returned from crumbs_get_router_item()
   *
   * @return array
   *   Parent path candidates
   */
  function findParent__taxonomy_term_x($path, $item) {
    $term = $item['map'][2];
    // Load the term if it hasn't been loaded due to a missing wildcard loader.
    $term = is_numeric($term) ? taxonomy_term_load($term) : $term;
    if (empty($term) || !is_object($term)) {
      return;
    }

    $parent = $this->plugin->entityFindParent($term, 'taxonomy_term', $term->vocabulary_machine_name);
    if (!empty($parent)) {
      return array($term->vocabulary_machine_name => $parent);
    }
  }
}
