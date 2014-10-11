<?php

/**
 * @file
 * An example code to import a set of i18n static HTML files to Drupal 7.
 *
 * This uses the Migrate module (development track, September 2014).
 * For the full description, please consult README.txt and DESCRIPTION.html
 *
 * @section overview Overview and Background
 *
 * The aim is:
 * - Import the main body.  (Of course!)
 * - Preserve the creation/modification times.
 * - Preserve all the legacy URIs.
 * - Natural-language paths, as opposed to the node number, should be displayed.
 * - All the internal links should work.
 * - Reproduce the i18n (internationalization) structure the original had,
 *   so the imported ones have a proper language code, as well as
 *   the Drupal language switcher incorporated.
 * - Make more modern-style URIs as default, while keeping the legacy ones.
 * - Preserve the creation/modification times.
 * - Preserve most Meta-tags and Link-tags information in the header.
 * - Introduce an taxonomy, based on the top directory name.
 * - The original <h1> tag is deleted, with the element imported as the page title.
 *
 * The static HTML has the following structure and features:
 * - Mainly in Japanese, but some files have its English counterpart.
 * - The top directory contains no HTML file but directories.
 *   (The legacy front page is discarded and the new one is manually created.)
 * - All the files are in the HTML format.  No image.
 * - The CSS files are not imported.
 * - All the HTML files have a suffix of ".html" (NOT ".htm" or ".HTML").
 * - Japanese HTMLs have a suffix of either:
 *    - ".html"
 *    - ".jis.html" or
 *    - ".jp.jis.html"
 * - English HTMLs have a suffix of either
 *    - ".en.html" or
 *    - ".en.us.html"
 * - There is no duplicated "index.html" for a language.
 *   That is, some directories have both "index.jis.html" and "index.en.html",
 *   and some have "index.html" only, however no directory has
 *   both "index.jis.html" and "index.html".
 * - The character-code is guaranteed to be UTF-8 (or its subset, US-ASCII).
 *   It is specified in the content attribute in the meta tag in the header.
 * - The HTML files have been "tidied" up by the command-line tool "tidy".
 * - Most have the meta tag of "keywords" and "description".
 *   Those attributes are guaranteed to be lower-cases (it is not
 *   case-sensitive in the HTML standard for UTF-8, however the routines
 *   in the QueryPath library requires them to be case-sensitive).
 * - Some have the link tag with the attribute of rel="alternate"
 *   (attribute element is guaranteed to be all lower-cases).
 * - The original files used to be in a different domain.
 *
 *
 * In the Drupal, the following modules have to be (installed and) enabled:
 * - path
 * - i18n (Internationalization, Field translation, Translation redirect)
 * - Taxonomy
 * - QueryPath
 * - Redirect
 * - Metatag
 * - Link
 * - Context
 * - langnonecontext (https://github.com/masasakano/langnonecontext)
 *
 * A content type with the machine name of "imported_html" is pre-created.
 * In its configuration (edit):
 * - Publishing Options > Multilingual support => Enabled (with translation)
 * - Multilingual settings > Extended language options => Allow Language Neutral
 *
 * In its ("imported_html") (Manage) Fields, pre-create the followings:
 * - field_original_title (Text)
 * - field_editors_note (Long Text)
 * - field_category_japanese (Term reference)
 * - field_original_html_filename (Text)
 *
 * In Taxonomy, pre-create a vocabulary:
 * - machine-name: "japanese_site"
 *
 * In i18n (/admin/config/regional/language/configure):
 * - Tick URL at least, or preferably all of them.
 * - For the URL, set it as the directory, and not the domain.
- The weight (priority) for the URL must be the highest.
 * - For all the languages, including the default language, explicitly
 *   set the language code for the path, e.g., "en" for English.
 *
 * If you change the user-ID (uid) from the default (=1), give the user
 * the permission of "Use the PHP code text format" in "Filter" section
 * of /admin/people/permissions
 *
 * Usage examples with drush:
 * - % drush migrate-register goo
 * - % drush migrate-status   --group=allbutbook
 * - % drush migrate-import   --group=allbutbook
 * - % drush migrate-rollback --group=allbutbook
 *
 *
 * Some parts of the codes were originally taken from:
 * - @link https://gist.github.com/marktheunissen/2596787 (by Mark Theunissen) @endlink
 * - @link https://gist.github.com/klaasvw/3904524 (by Klaas Van Waesberghe) @endlink
 * - @link http://www.group42.ca/creating_url_redirects_with_migrate_module (by Dale McGladdery) @endlink
 * Note they have been massively modified.  I am citing them to express
 * my appreciation!
 *
 *
 * @section detail Plan of migration and technical specification
 *
 * @subsection plan Plan of the migration:
 * 
 * - Path structure (for the new main path):
 *   - Language-related suffix is eliminated from the default path,
 *     e.g, "info/foo.en.html" => "info/foo.html"
 * - Path structure (for redirection with HTTP 301):
 *   - The original paths are redirected to the new main path, if they differ
 *     from each other, e.g., "info/index.jis.html" is redirected
 *     to "info/index.html".
 *   - For "Index files", the directory is redirected,
 *     e.g., "info/" => "info/index.html".  
 *     Note: The other way around would not work well.  The following explains why.
 *     Suppose the main path is defined as "info/uk" (for info/uk/index.html).
 *     Note a trailing forward-slash must not be included in Drupal path.
 *     Then, a hyperlink anchored from info/uk (= info/uk/index.html)
 *     with a relative path, say, "./baa.html", is recognised
 *     by the users' browsers as "info/baa.html", as opposed to the correct
 *     "info/uk/baa.html". Therefore if a user tries to open the hyperlink,
 *     the browser sends a request of "info/baa.html", which will cause 404,
 *     namely, the dead link. It is the correct interpretation for the browser.
 *     If the original path was "info/uk/", it would work as expected,
 *     but it is not the case in Drupal.
 *     If a redirection for the same "from" path is already set to a different
 *     destination, this migration overwrites it, unless there are more than one
 *     redirection is defined for it.
 * - i18n:
 *   - The primary page is always Japanese. Some may have English counterpart,
 *     but many don't. There's no English page that has no Japanese counterpart.
 *   - Therefore, the source language (for translation) is always Japanese.
 *   - If a Japanese page does not have an English counterpart,
 *     the language of the node is set to be neutral (LANGUAGE_NONE).
 *   - If it has an English counterpart, the language is set to be "ja",
 *     accordingly.  The same goes for English pages.
 *   - The language for the body is always set appropriately ("ja" or "en").
 * - Author of the node:
 *   - Administrator (uid = 1)
 * - Creation and modification time:
 *   - "changed" is taken from "mtime" of the file (in the UNIX disk-system).
 *     Note: Be careful if you preprocess files, as it may change their mtime.
 *   - "created" is taken from "ctime" of the file.
 *     If ctime is later than mtime, mtime is used.
 * - Title of the node:
 *   - The element of the <h1> tag, if there is any. <h1> tag is deleted.
 *   - If not, <title> tag is used, if the element is not empty.
 *   - And if not, the file path is used.
 * - Miscellaneous information:
 *   - field_original_title: The element of <title> tag.
 *   - field_original_html_filename: The original directory and filename
 *     (without the domain name or a preceding forward slash).
 *   - field_editors_note: Information for potential debugging.
 * - Taxonomy: japanese_site:
 *   - The top directory name.
 * - Meta-tags:
 *   - "description", "keywords", "original-source" are set.
 * - Link:
 *   - "field_link_alternative", together with customised title, is set.
 *
 *
 * @subsection flow-chart Technical flow-chart:
 *
 * - The base directory for the HTML files is defined as a constant,
 *   ALLBUTBOOK_TOPHTMLDIR
 * - The migration is done in 2 steps (necessary to construct the i18n structure):
 *   - Japanese HTMLs (AllbutbookHtmlJaMigration) first, then
 *   - English ones (AllbutbookHtmlEnMigration).
 * - Use MigrateSourceList class to define the HTML files to import.
 * - In prepareRow() the path of each file (aka row) is passed.  With this:
 *   - get all the required information from the header,
 *   - get the <body> element,
 *   - also checks if the translation is available, based on the path name.
 *   - "tnid" is undefined in Japanese HTMLs at the time of processing of
 *     AllbutbookHtmlJaMigration
 *   - "tnid" for Japanese pages is set in prepare() while processing
  *    AllbutbookHtmlEnMigration, where the relation
  *    between translation and source nodes is set.
 * - The process to handle the HTML tags is coded in html_parser.inc
 *   with the use of QueryPath library.
 * - In complete(), the redirection of the legacy URIs is set.
 * - No roll-back is defined for redirections, whereas the existing redirections
 *   are checked, and are possibly overwritten (warning message will be
 *   issued if run from drush).
 *
 *
 * @subsection strategy Strategy of migration of static HTMLs in i18n
 *
 * - The grand picture is as follows:
 *   - The static HTMLs, most of which are in Japanese but some of which
 *     have a English translation, are imported to an existing site.
 *   - The default language of the existing site is English, though
 *     some of the existing contents are translated into Japanese.
 *   - None of the top directories of the import HTMLs crashes
 *     with the existing ones.
 *   - The front-page and menu for the Japanese contents are not imported.
 *   - The Japanese and English versions of the nodes will be distinguished
 *     based on the path-prefix as the first priority in the i18n configuration.
 *   - English and Japanese contents on the site will be seamless,
 *     jumped to each other via a language-switcher in a menu,
 *     though a significant difference in contents between them will remain.
 * - The path aliases are enabled.  Hence the nominal path is not
 *   a /node/12345 type, but like /info/foobaa.html
 * - The Drupal path (URL Alias) for any imported node is set to be the filename
 *   of the HTML minus any language-related suffix, but only .html
 *   For example, for the file /info/index.jp.jis.html the path is
 *   /info/index.html
 * - For index.html, to access via the directory is allowed; for example,
 *   /info is redirected to /info/index.html
 * - When there are both Japanese and English versions available
 *   for the same content, their paths (URL aliases) are set to be the same.
 * - The language of the node is in default neutral, except when the version
 *   in the other language is available, in which case the appropriate language
 *   is set for each node.
 * - The language of the main content (body) is always set appropriately.
 *   even when the language of the node is neutral.
 * - The built-in language-switcher is disabled in default (in the paths
 *   of the imported HTMLs).  This is set with the configuration of the block
 *   (the path section for the language-switcher block).
 * - However, for the nodes that have a version in the other language,
 *   the built-in language-switcher is enabled (in a side bar), so visitors
 *   can easily identify there is a translation and can switch the language
 *   if they want.  This is configured via the Context module combined
 *   with the added langnonecontext module.
 * - When they switch the language, the language for the interface changes
 *   at the same time.
 * - The most tricky thing is the hard-coded language-switchers in some HTMLs,
 *   that is, embedded anchors to the version of the other language,
 *   break down in migration.  This problem is solved by replacing
 *   the anchor tag during migration with a small piece of PHP code
 *   (for relative paths) to determine the link to the other language version
 *   on the fly.
 *
 * @section Unresolved issues
 *
 * - When a file is a symbolic link to another file in the file list
 *   to be imported, both the files will be imported as separate files with
 *   the identical content.  Ideally the path that is a symbolic-link
 *   should be treated as a redirection to the original file.
 *   It is possible to implement, but can be tricky, if considering the case
 *   where the linked file is not included in the file list to be imported
 *   (it is maybe symlinked to the file out of the imported directory
 *   of file list, or possibly the symlinked file has been already imported,
 *   etc, etc).
 * - No roll-back is defined for redirection.
 *
 */

define('ALLBUTBOOK_TOPHTMLDIR', '/MY/LEGACY/HTML/ROOT');	// Change this!
define('LEGACY_DOMAIN_ROOT', 'http://example.com/');
define('MSG_TRANS_VERSION', 'Translated-Version');

require_once drupal_get_path('module', 'migrate_goo') . '/html_parser.inc';

// ***************************************************************
/**
 * An common base class for all the individual migrations.  
 * 
 * Contains some common options and methods.
 */
abstract class GooAllbutbookMigration extends Migration {

  public function __construct($arguments) {
    parent::__construct($arguments);

    // For migrate_ui.
    $this->team = array(
      new MigrateTeamMember('Masa S', 'masa@example.com', t('Implementor')),
    );
  }


  /**
   * Sets $this->map = new MigrateSQLMap().
   *
   * @param string $description
   *   Human-readable description of 'sourceid'.
   */
  public function setMapDestination($description) {

    $this->map = new MigrateSQLMap(
      $this->machineName,
      array(
        'sourceid' => array(
            'type' => 'varchar',
            'length' => 255,
            'not null' => TRUE,
            'description' => $description,
        )
      ),
      MigrateDestinationNode::getKeySchema()
    );

    $this->destination = new MigrateDestinationNode('imported_html');

    return $this;
  }

  /**
   * Gets fields to be used for MigrateSourceList().
   *
   * @return array
   *   Array of fields.
   */
  public function getFieldsMigrateSourceList() {

    return array(
      'row_title' => t('Title'),
      'body' => t('Body'),
      'uid' => t('User id'),
      // 'row_tnid' => t('Translation id'),	// Only for class AllbutbookHtmlEnMigration
      'lang_html' => t('Language of the source HTML'),
      'lang_dest' => t('Language of the destination node, maybe und'),
      'created'  => t('ctime'),
      'changed'  => t('mtime'),
      'title_orig'    => t('Original title'),
      'filename_orig' => t('Original filename'),
      'filename_dest' => t('Filename at destination'),	// that without language-related suffix.
      'taxonomy' => t('Taxonomy'),
      'row_description' => t('Metatag Description'),
      'row_keywords'    => t('Metatag Keywords'),
      'uri_orig'      => t('Original URI'),
      'header_links'       => t('Header Links'),
      'header_link_keys'   => t('Header Link Keys'),
      'row_note' => t('Editors note'),
      // 'old_legacy_path' => t('Old Legacy Path'),	// Used only in complete()
    );

  }	// public function getFieldsMigrateSourceList() {


  /**
   * Common addFieldMapping().
   *
   * @return
   *   $this
   */
  public function commonAddFieldMapping() {

    $this->addFieldMapping('uid', 'uid');	// Default is set to be 1 (=admin) in prepareRow($row)
    $this->addFieldMapping('title', 'row_title');
                        // dest_name, source_name
    $this->addFieldMapping('body',  'body');
      // ->arguments(array('format' => 'full_html'));	// Obsolete. See below.
    $this->addFieldMapping('body:format')->defaultValue('php_code');
	// Whereas 'full_html' is safer, 'php_code' is necessary because
    // $html_parser->getBody() may return a HTML that contains PHP code.
	// Make sure the user with the above uid has the permission to use it.
    $this->addFieldMapping('body:language',  'lang_html');

    $this->addFieldMapping('language',  'lang_dest');
    $this->addFieldMapping('created',   'created');
    $this->addFieldMapping('changed',   'changed');
    // $this->addFieldMapping('timestamp', 'timestamp');	// The most recent time the node has been viewed.  (statistics module)

    // Path (Main)
    $destination_fields = $this->destination->fields();
    if (isset($destination_fields['path'])) {
      $this->addFieldMapping('path', 'filename_dest');
      if (isset($destination_fields['pathauto'])) {
        $this->addFieldMapping('pathauto')->defaultValue(0);
        //     ->issueGroup(t('DNM'));
      }
    }

    // // Path (Redirection)	// Not supported, yet, as in Sep 2014.
    // $this->addFieldMapping('migrate_redirects', 'old_legacy_path')
    //     ->defaultValue('MigrateRedirectEntity');

    // File-related info
    $this->addFieldMapping('field_original_title', 'title_orig')
         ->description(t('Original title tag.'));
    $this->addFieldMapping('field_original_html_filename', 'filename_orig')
         ->description(t('Original filename without preceding slash.'));

    // Taxonomy - The top directory name into Vocabulary of 'japanese_site',
    //  which has been created for this migration.
    $this->addFieldMapping('field_category_japanese', 'taxonomy');

    // Metatag support
    $this->addFieldMapping('metatag_description', 'row_description');
    $this->addFieldMapping('metatag_keywords',    'row_keywords');
    $this->addFieldMapping('metatag_original-source', 'uri_orig');

    // Link support
    $this->addFieldMapping('field_link_alternative',       'header_links');
    $this->addFieldMapping('field_link_alternative:title', 'header_link_keys');

    // i18n support (translation)
    // $this->addFieldMapping('tnid',  'row_tnid');	// Only for class AllbutbookHtmlEnMigration
    $this->addFieldMapping('field_editors_note', 'row_note');

    // Miscelloneous (Constants)
    //  cf. https://www.drupal.org/node/1349696
    $this->addFieldMapping('status')->defaultValue(1);	// (1 for published and 0 for unpublished)
    $this->addFieldMapping('promote')->defaultValue(0);	// (1 for promoted, 0 for not promoted)
    $this->addFieldMapping('sticky')->defaultValue(0);	// (1 for sticky, 0 for not sticky).
    $this->addFieldMapping('revision')->defaultValue(0);	// (1 to create a revision, 0 to overwrite if the node already exists).
    $this->addFieldMapping('field_original_html_filename:language')->defaultValue(LANGUAGE_NONE);	// 'und'

    // Unmapped destination fields (DNM: Do Not Map)
    $this->addUnmigratedDestinations(array(
      'body:summary',
      'comment',
      'is_new',
      'log',
      'revision_uid',
      // 'tnid',	// The case only for class AllbutbookHtmlJaMigration
      'translate',	// (TRUE: Translation needs to be updated).
      'is_new',

      'metatag_title',
      'metatag_abstract',
      'metatag_robots',
      'metatag_news_keywords',
      'metatag_standout',
      'metatag_generator',
      'metatag_rights',
      'metatag_image_src',
      'metatag_canonical',
      'metatag_shortlink',
      'metatag_publisher',
      'metatag_author',
      'metatag_revisit-after',
      'metatag_content-language',
    ));

    return $this;
  }	// public function commonAddFieldMapping() {


  /**
   * Common part to Prepare a row.
   *
   * @param $row
   *   Row (to be used in the migration).
   * @param $lang
   *   Language code of the caller, either /ja|en/, depending which class calls this.
   *
   * @return
   *   $this
   */
  public function commonPrepareRow($row, $lang) {

    $relpathorig = substr($row->sourceid, 1);
    // $row->sourceid ("sourceid" defined in MigrateSQLMap) is the filename,
    // with the first character of '/', after the base directory is stripped
    // from the beginning.

    drush_print(sprintf('Drush:NOTE: Processing: %s', $row->sourceid));

    $row->uid = 1;	// Set to admin (as default).
 
    // Create a new HtmlParser to handle HTML content.
    $html_parser = new HtmlParser($relpathorig, $row->filedata);
    $row->body = $html_parser->getBody();	// Import HTML main body.
 
    // Taxonomy (= Top Directory)
    $s = preg_replace('@^\.?/@', '', dirname($row->sourceid));
    $row->taxonomy        = preg_replace('@/.+@', '', $s);

    $row->row_title       = $html_parser->title;
    $row->title_orig      = $html_parser->title_orig;
    $row->uri_orig        = LEGACY_DOMAIN_ROOT . $relpathorig;
    $row->filename_orig   = $relpathorig;
    // drush_print(sprintf('Drush:NOTE: Parsed and importing: %s', $row->filename_orig));

    $row->filename_dest = preg_replace('@\.[^/]*$@', '', $relpathorig) . '.html';
	// Filename without language-related suffix: eg. foo.jis.html => foo.html

    $ctime = filectime(ALLBUTBOOK_TOPHTMLDIR . '/' . $relpathorig);
    $mtime = filemtime(ALLBUTBOOK_TOPHTMLDIR . '/' . $relpathorig);
    if ($ctime > $mtime) {
      $ctime = $mtime;	// When the file is copied (perhaps prior to the migration), this could happen, depending on the filesystem.
    }
    $row->created = $ctime;
    $row->changed = $mtime;
    // $row->timestamp = fileatime(ALLBUTBOOK_TOPHTMLDIR . '/'. $row->filedata);	// The most recent time the node has been viewed (in 'statistics' module).

    // Metatag and Link
    $row->row_description  = $html_parser->metatag_description;
    $row->row_keywords     = $html_parser->metatag_keywords;
    $row->header_links     = $html_parser->header_links;
    $row->header_link_keys = $html_parser->header_link_keys;

    // Language (Source HTML => Destination Body)
    $row->lang_html = $html_parser->lang_html;

    // Language-specific routines (Japanese or English).
    $row->row_note = '';
    switch ($lang) {
    case 'ja':
      $re_langcode = '/((\.jp)?\.jis)?\.html/';
      $arysuffix = array('.en.', '.en.us.');	// Suffix list of the other language
      break;
    case 'en':
      $row->row_tnid = NULL;	// Initialisation
      $re_langcode = '/\.en(\.us)?\.html/';
      $arysuffix = array('.jis.', '.jp.jis.', '.');	// Suffix list of the other lang
      break;
    default:
      // Must NOT happen!!
      $msg = sprintf("Wrong Language-code=(%s)", $lang);
      $row->row_note .= $msg;
      drush_print("Drush:Warning: $msg in ($row->filename_orig).");
    }

    // Determine the language of the Destination Node, which can be 'und'
    // if there is only one language, Japanese, for the HTML file.
    if ($html_parser->lang_html == $lang) {
      if (preg_match($re_langcode, $relpathorig)) {
        // $row->lang_dest = $html_parser->lang_html;	// Language in HTML in Default
        $row->lang_dest = LANGUAGE_NONE;	// Language Neutral in Default

        foreach ($arysuffix as $keyw) {
          $fbasename_trans = preg_replace($re_langcode, $keyw . 'html', $relpathorig);

          if (file_exists(ALLBUTBOOK_TOPHTMLDIR . '/' . $fbasename_trans)) {
            // The translation (HTML) exists for this one, so the language is set.

            $row->lang_dest = $html_parser->lang_html;

            if ($lang == 'ja') {
              $row->row_note .= sprintf("%s: %s ", MSG_TRANS_VERSION, $fbasename_trans);
            }
            elseif ($lang == 'en') {
              // Find Node-ID of the translated counterpart (Japanese).
              $field_name = 'field_original_html_filename';
              $query = sprintf("SELECT n.entity_id FROM {field_data_%s} n WHERE n.%s_value = '%s'",
                               $field_name, $field_name, $fbasename_trans);	// entiti_id == nid of Node
              $result = db_query($query);
              $nid_trans = $result->fetchCol()[0];
              $row->row_tnid = $nid_trans;	// The filename is guaranteed to be unique.
              if ($nid_trans == 0) {
                // The nid (Node-Id) of the source translation is zero(!).
                // Nid for any node should not be zero, and this should not happen.
                $msg = sprintf("WARNING: query=(%s) \n", $query);
                $row->row_note .= $msg;
                drush_print("Drush:Warning: $msg in ($row->filename_orig).");
              }
                $row->row_note .= sprintf("%s: %s : NiD=(%d) ", MSG_TRANS_VERSION,
                                          $fbasename_trans, $nid_trans);
              unset($field_name, $query, $result, $nid_trans);
            }
            break;
          }	//  if (file_exists(ALLBUTBOOK_TOPHTMLDIR . '/' . $fbasename_trans))
        }	// foreach ($arysuffix as $keyw) {

        if (! (preg_match('/' . MSG_TRANS_VERSION . '/', $row->row_note))) {
          $row->row_note .= sprintf("%s: %s", MSG_TRANS_VERSION, 'None.');
        }

        unset($re_langcode, $keyw, $fbasename_trans);

      }
      else {
        $row->lang_dest = LANGUAGE_NONE;
        $msg = 'WARNING: RegExp failed in detecting the translation.';
        $row->row_note .= $msg;	// This should not happen.
        drush_print("Drush:Warning: $msg in ($row->filename_orig).");

      }	// if (preg_match($re_langcode, $relpathorig)) {

    }
    else {
      $row->lang_dest = LANGUAGE_NONE;
      $msg = sprintf("WARNING: Language-code=(%s), which should be ja", $html_parser->lang_html);
      $row->row_note .= sprintf("WARNING: Language-code=(%s), which should be ja", $html_parser->lang_html);	// This should not happen.
      drush_print("Drush:Warning: $msg in ($row->filename_orig).");

    }	// if ($html_parser->lang_html == $lang) {

    // For path redirections.
    $row->old_legacy_path = array();
    if ($row->filename_dest != $relpathorig) {
      array_push($row->old_legacy_path, $relpathorig);
      // Original path: e.g., info/index.jis.html
    }

    if ($lang == 'ja') {
      if (preg_match('/^index\./', basename($relpathorig))) {
        array_push($row->old_legacy_path, dirname($relpathorig));
        // Directory like "info/" (as well as "info" without a trailing
        // forward-slash) will be redirected to index.html: e.g.,
        //   info/ => info/index.html
        // No English HTML lacks of Japanese translation, hence this is done
        // only in class AllbutbookHtmlJaMigration .
        // Note dirname($relpathorig) has no trailing slash, and that is right
        // because Drupal does not accept the path with a trailing slash.
      }
    }

    return $this;
  }	// public function commonPrepareRow($row) {


  /**
   * Complete - to set up the redirction of legacy URIs.
   *
   * Some code fragments are taken from 
   * @link http://www.group42.ca/creating_url_redirects_with_migrate_module the one by Dale McGladdery @endlink
   * No roll-back is defined so far (See the comment in the website).
   * There is an on-going effort to implement this feature in Migrate/Redirect
   * themselves, but as in September 2014, it has not been officially released.
   * @link https://www.drupal.org/node/1116408 See this. @endlink
   */
  public function complete($entity, $row) {
    // Create a redirect from the old path to the new one

    foreach ($row->old_legacy_path as $old_path) {
      if (isset($old_path)) {
        // Create an object with our redirect parameters

        $redirect = new stdClass();
        $redirect->source = $old_path;	// From URL
        $redirect->source_options = array();
        $redirect->redirect = $row->filename_dest;	// To URL
        $redirect->redirect_options = array();
        $redirect->status_code = 0;					// Redirect Status
        $redirect->type = 'redirect';
   
        // Check if the redirection to set is already defined, perhaps due to
        // the previous attempt of migrate (there is no roll-back defined
        // for this setting-up of redirections).
        $query = sprintf("SELECT n.rid,n.redirect FROM {redirect} n WHERE n.source = '%s'", $old_path);
        $result = db_query($query);
        $aryhsres = $result->fetchAll();	// Array of Hash.

        if (count($aryhsres) > 1) {
          drush_print(sprintf("Drush:Note: Redirection from (%s) as (%s) has more than 1 entry registered, hence not updated.", $row->filename_orig, $old_path));
          // To update by rewriting, the language must be checked.  Not bother.
        }
        elseif (count($aryhsres) == 1) {
          if ($aryhsres[0]->redirect == $row->filename_dest) {
            continue;
            // Does nothing, as it is already defined identically (though
            // the language is not checked here, hence it may not be quite identical).
          }
          else {
            $redirect->rid = $aryhsres[0]->rid;
            // rid is set to overwrite the existing one.
            drush_print(sprintf("Drush:Note: Redirection from (%s) as (%s) is defined to (%s), but is now overwritten to (%s) with the rid=(%d).", $row->filename_orig, $old_path, $aryhsres[0]->redirect, $redirect->redirect, $redirect->rid));
          }
        }
        else {
          // No existing redirect for this.
        }	// if (count($aryhsres) > 1) {

        // Set the language.
        $redirect->language = LANGUAGE_NONE;

        // Create or update the redirect
        redirect_save($redirect);
      }
    }
  }	// public function complete($entity, $row) {

}	// abstract class GooAllbutbookMigration extends Migration {


// ***************************************************************
/**
 * Migrates Japanese HTMLs (source language for translation).
 */
class AllbutbookHtmlJaMigration extends GooAllbutbookMigration {

  public function __construct($arguments) {
    parent::__construct($arguments);
    $this->description = t('Migrate Japanese HTMLs');

    $this->setMapDestination($this->description);
    // A map of source HTML filename -> destination node id.

    $fields = $this->getFieldsMigrateSourceList();
    // The source fields.  Define all the $row keywords here (NOT destination)

    $options = array( 'nomask' => '/(\.\.?|CVS|^\..+|\.en\.(us\.)?html)$/' ); 
    // List of the files - all the *.html except for *.en.html and *.en.us.html
    $list_files   = new MigrateListFiles(
        array(ALLBUTBOOK_TOPHTMLDIR),	// List of Directories,
        ALLBUTBOOK_TOPHTMLDIR,			// Root directory to strip,
        '/.+\.html$/',					// Filter,
        $options						// Option(Exception)
    );

    $item_file    = new MigrateItemFile(ALLBUTBOOK_TOPHTMLDIR);

    $this->source = new MigrateSourceList($list_files, $item_file, $fields);

    $this->commonAddFieldMapping();
    $this->addUnmigratedDestinations(array('tnid'));
  }

  /**
   * Prepare a row.
   */
  public function prepareRow($row) {
    // This part adopted from the manual: https://www.drupal.org/node/1132582
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;	// parent classes can ignore rows.
    }
    $this->commonPrepareRow($row, 'ja');
  }		// public function prepareRow($row) {

}	// class AllbutbookHtmlJaMigration extends GooAllbutbookMigration {



// ***************************************************************
/*
 * Migrates English HTMLs, referring to the source in Japanese.
 */
class AllbutbookHtmlEnMigration extends GooAllbutbookMigration {

  public function __construct($arguments) {
    parent::__construct($arguments);
    $this->description = t('Migrate English HTMLs');

    // Make sure this is run AFTER AllbutbookHtmlJa.
    $this->dependencies = array('AllbutbookHtmlJa');

    $this->setMapDestination($this->description);
    // A map of source HTML filename -> destination node id.
 
    $fields = $this->getFieldsMigrateSourceList();
    $fields['row_tnid'] = t('Node ID of the Translation, maybe itself');
    // The source fields.  Define all the $row keywords here (NOT destination)
 
    $list_files   = new MigrateListFiles(
        array(ALLBUTBOOK_TOPHTMLDIR),	// List of Directories,
        ALLBUTBOOK_TOPHTMLDIR,			// Root directory to strip,
        '/.+\.en\..*html$/'				// Filter	// *.en.html or *.en.us.html (etc)
    );
 
    $item_file    = new MigrateItemFile(ALLBUTBOOK_TOPHTMLDIR);

    $this->source = new MigrateSourceList($list_files, $item_file, $fields);

    $this->commonAddFieldMapping();
    $this->addFieldMapping('tnid',  'row_tnid');	// i18n (translation)

  }

 
  /**
   * Prepares a row.
   */
  public function prepareRow($row) {
    if (parent::prepareRow($row) === FALSE) {
      return FALSE;	// parent classes can ignore rows.
    }
    $this->commonPrepareRow($row, 'en');
  }		// public function prepareRow($row) {


  /**
   * Prepares.
   *
   * Taken from
   * @link https://gist.github.com/klaasvw/3904524 (by Klaas Van Waesberghe) @endlink
   *
   * For the translations between two nodes to work in Drupal,
   * tnid of the source node needs to be set to its own nid.
   * Drupal does this on insert when a node has a its
   * translation source node assigned to a translation_source property.
   */
  public function prepare(&$node, $row) {
    if (isset($node->tnid) && ($source = node_load($node->tnid))) {
      $node->translation_source = $source;
    }
  }
 
}	// class AllbutbookHtmlEnMigration extends GooAllbutbookMigration {


