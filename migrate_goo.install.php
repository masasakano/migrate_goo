<?php

/**
 * @file
 * Set up the migration Goo module.
 */

require_once DRUPAL_ROOT . '/' . drupal_get_path('module', 'migrate_goo') .
  '/allbutbook.install.inc';

function migrate_goo_install() {
  migrate_goo_allbutbook_install();
  migrate_static_registration();
}

function migrate_goo_uninstall() {
  migrate_goo_allbutbook_uninstall();
}

function migrate_goo_disable() {
  migrate_goo_allbutbook_disable();
}

