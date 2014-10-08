<?php

/**
 * @file
 * Because the name of this file is the module name plus '.migrate.inc', when
 * hook_migrate_api is invoked by the Migrate module this file is automatically
 * loaded - thus, you don't need to implement your hook in the .module file.
 */

/*
 * You must implement hook_migrate_api(), setting the API level to 2, if you are
 * implementing any migration classes. 
 */
function migrate_goo_migrate_api() {

  $api = array(
    // Required - tells the Migrate module that you are implementing version 2
    // of the Migrate API.
    'api' => 2,
    'groups' => array(
      'allbutbook' => array(
        'title' => t('Goo All-but-book Imports'),
      ),
    ),

    'migrations' => array(
      'AllbutbookHtmlJa' => array(
        // Japanese HTML migration class.
        'class_name' => 'AllbutbookHtmlJaMigration',
        'group_name' => 'allbutbook',
      ),
      'AllbutbookHtmlEn' => array(
        // English HTML migration class.
        'class_name' => 'AllbutbookHtmlEnMigration',
        'group_name' => 'allbutbook',
      ),
    ),
  );
  return $api;
}
