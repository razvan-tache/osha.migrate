<?php
/**
 * Created by PhpStorm.
 * User: razvan
 * Date: 7/9/14
 * Time: 9:09 AM
 */

function nace_migration_drush_command() {
    $items['hotfix_save_tid'] = array(
        'description' => 'Save the tid\'s properly',
        'required-arguments' => FALSE
    );
    return $items;
}

function drush_nace_migration_hotfix_save_tid() {
    $t_s = time();
    $term_options_list = array();
    $i = $c = 0;
    if ($range_vocab = taxonomy_vocabulary_machine_name_load('nace_v3')) {
        $efq = new EntityFieldQuery();
        $result = $efq->entityCondition('entity_type', 'taxonomy_term')
            ->propertyCondition('vid', $range_vocab->vid)
            ->propertyOrderBy('weight')
            ->execute();

        if (!empty($result['taxonomy_term'])) {
            // The results contain an array of entity stubs keyed by {term}.tid.
            // The stub only has {term}.tid and vocabulary_machine_name.
            $first_term_stub = current($result['taxonomy_term']);
            $first_term_tid = key($result['taxonomy_term']);
            // To load all terms.
            $terms = taxonomy_term_load_multiple(array_keys($result['taxonomy_term']));
            $c = count($terms);
            // To generate an options list.
            foreach ($terms as $term) {
                // To hook into i18n and everything else, use entity_label().
                $term_options_list[$term->tid] = entity_label('taxonomy_term', $term);
                taxonomy_term_save($term);
                $i++;
            }
        }
    }
    $t_e = time();
    drupal_set_message(t('@i out of @c nodes were updated in @s seconds', array('@s' => ($t_e - $t_s), '@i' => $i, '@c' => $c)), 'status');
}