<?php
 
/**
 * You must implement hook_migrate_api(), setting the API level to 2, for
 * your migration classes to be recognized by the Migrate module.
 */
function news_migration_migrate_api() {
  $api = array(
    'api' => 2,
    'migrations' => array(
	'News' => array('class_name' => "JSONNewsMigration")
    )
  );
  return $api;
}
