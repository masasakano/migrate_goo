<?php

/**
 * @file
 * Set up for the Goo HTML migration.
 */
function migrate_goo_allbutbook_schema() {
  return $schema;
}

function migrate_goo_allbutbook_install() {
}

function migrate_goo_allbutbook_uninstall() {
}

function migrate_goo_allbutbook_disable() {
  MigrateGroup::deregister('allbutbook');
}
