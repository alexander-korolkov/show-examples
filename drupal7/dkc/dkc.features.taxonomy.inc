<?php
/**
 * @file
 * dkc.features.taxonomy.inc
 */

/**
 * Implements hook_taxonomy_default_vocabularies().
 */
function dkc_taxonomy_default_vocabularies() {
  return array(
    'quick_links' => array(
      'name' => 'Quick links',
      'machine_name' => 'quick_links',
      'description' => 'Represents list of quick lists for front page.',
      'hierarchy' => 0,
      'module' => 'taxonomy',
      'weight' => 0,
      'rdf_mapping' => array(
        'rdftype' => array(
          0 => 'skos:ConceptScheme',
        ),
        'name' => array(
          'predicates' => array(
            0 => 'dc:title',
          ),
        ),
        'description' => array(
          'predicates' => array(
            0 => 'rdfs:comment',
          ),
        ),
      ),
    ),
  );
}
