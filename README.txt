
Migration of i18n static HTML pages to Drupal 7.

Structure of this directory
---------------------------
- CHANGELOG : ChangeLog
- DESCRIPTION.html : Detailed description of the concept, not the code.
- LICENSE.txt : MIT License
- README.txt : This file - Installation, Usage, Specification, Bugs, etc
- migrate_goo.info : Info file for Drupal module.
- *.php : Main PHP files.


Overview
--------
The migrate_goo module is a child module of the migrate module and
provides a migration from a set of static HTML pages to Drupal,
taking into account the i18n (internationalization), importing
custom terms for Taxonomy, Meta-tags and Link-tags, and preserving
all the legacy URIs.

This is <b>not</b> the generic module for the purpose, but
is tuned specifically for a particular case the author has experienced.
Also, though I give extensive comments in and out of codes, they are not
self-explanatory. You must understand how the (parent) Migrate module works.

Nevertheless, given the situation the related documentation and
reference, particularly those about the migration of the static HTMLs
with the full i18n feature, are limited, this may give you some
starting and/or reference point.  The main code is in allbutbook.inc.php


Install
-------
Basically, follow the standard procedure.
Make sure the following modules this module depends on are
(installed and) enabled beforehand.

They are in short,
- path
- i18n (Internationalization, Field translation, Translation redirect)
- Taxonomy
- QueryPath (Manual install is fine, as long as you adjust the library path accordingly)
- Redirect
- Metatag
- Link
- Context
- langnonecontext (https://github.com/masasakano/langnonecontext)

Place this directory (migrate_goo) into your user module directory, usually:
    /YOUR_DRUPAL_ROOT/sites/all/modules/
preserving the directory structure.
Make sure to rename the filenames so the suffix '.php' is deleted.
For example:
    allbutbook.inc.php => allbutbook.inc

Then enable the module via /admin/modules or drush
    % drush en migrate_goo

In addition, the following settings and preparation are required in
default before running the migration.

A content type with the machine name of "imported_html" is pre-created.
In its configuration (edit):
- Publishing Options > Multilingual support => Enabled (with translation)
- Multilingual settings > Extended language options => Allow Language Neutral

In its ("imported_html") (Manage) Fields, create the followings with the relevant type:
- field_original_title (Text)
- field_editors_note (Long Text)
- field_category_japanese (Term reference)
- field_original_html_filename (Text)

In Taxonomy, create a vocabulary:
- machine-name: "japanese_site"

In i18n (/admin/config/regional/language/configure):
- Tick URL at least, or preferably all of them.
- For the URL, set it as the path (directory), and not the domain.
- The weight (priority) for the URL must be the highest.
- For all the languages, including the default language, explicitly
  set the language code for the path, e.g., "en" for English.

If you change the user-ID (uid) from the default (=1), give the user
the permission of "Use the PHP code text format" in "Filter" section
of /admin/people/permissions


Usage
-----
At the very least, change the hard-coded value of the constant
ALLBUTBOOK_TOPHTMLDIR in allbutbook.inc

Though the GUI is available to a limited extent, drush is highly
recommended.  Diagnostic messages will be printed if you use drush.
 - % drush migrate-register goo
 - % drush migrate-status   --group=allbutbook
 - % drush migrate-import   --group=allbutbook
 - % drush migrate-rollback --group=allbutbook


Background and Goal
-------------------
The reference for importing static HTML files to Drupal is sparse, or
old and/or incomplete at best.  As I found out, the import_html module
does not work well for the nodes that contain UTF-8 characters
    https://www.drupal.org/node/2339097

That is why I switched over to the migrate module.  Anyway it provides a much
better flexibility, and indeed is essential to implement the i18n feature.

Nevertheless the i18n feature was I found very tricky to deal with.
It is to some extent an inherent problem for the i18n, because
practically every multilingual site is different and so has different
requirements. Also, as I eventually found, the default Apache feature for
the suffix-based language negotiation with the static HTMLs does not match well
with Drupal's i18n feature, hence I ended up developing a small module to
deal with it.  Unsurprisingly, as I realise now, I have experienced a great
difficulty for all these processes and it has taken a lot of time to complete,
even though one of the major reasons was my sheer lack of experience.
So, here am I sharing my code in the hope this may help some one else.

The aim of this migration is summarised as:
- Import the main body.  (Of course!)
- Preserve the creation/modification times.
- Preserve all the legacy URIs.
- Natural-language paths, as opposed to the node number, should be displayed.
- All the internal links between the imported files should work.
- Reproduce the i18n (internationalization) structure the original had,
  so the imported ones have a proper language code, as well as
  the Drupal language switcher incorporated.
- Make more modern-style URIs as default, while keeping the legacy ones.
- Preserve the creation/modification times.
- Preserve most Meta-tags and Link-tags information in the header.
- Introduce an taxonomy, based on the top directory name.
- The original <h1> tag is deleted, with the element imported as the page title.


The structure of the HTML files to be imported
----------------------------------------------
The static HTML has the following structure and features:
- Mainly in Japanese, but some files have its English counterpart.
- The top directory contains no HTML file but directories.
  (The legacy front page is discarded and the new one is manually created.)
- All the files are in the HTML format.  No image.
- The CSS files are not imported.
- All the HTML files have a suffix of ".html" (NOT ".htm" or ".HTML").
- Japanese HTMLs have a suffix of either:
   - ".html"
   - ".jis.html" or
   - ".jp.jis.html"
- English HTMLs have a suffix of either:
   - ".en.html" or
   - ".en.us.html"
- There is no duplicated "index.html" for a language.
  That is, some directories have both "index.jis.html" and "index.en.html",
  and some have "index.html" only, however no directory has
  both "index.jis.html" and "index.html".
- The character-code is guaranteed to be UTF-8 (or its subset, US-ASCII).
  It is specified in the content attribute in the meta tag in the header.
- The HTML files have been "tidied" up by the command-line tool "tidy".
- Most have the meta tag of "keywords" and "description".
  Those attributes are guaranteed to be lower-cases (it is not
  case-sensitive in the HTML standard for UTF-8, however the routines
  in the QueryPath library requires them to be case-sensitive).
- Some have the link tag with the attribute of rel="alternate"
  (attribute element is guaranteed to be all lower-cases).
- The original files used to be in a different domain.


Plan of migration and technical specification
---------------------------------------------
- Path structure (for the new main path):
  - Language-related suffix is eliminated from the default path,
    e.g, "info/foo.en.html" => "info/foo.html"
- Path structure (for redirection with HTTP 301):
  - The original paths are redirected to the new main path, if they differ
    from each other, e.g., "info/index.jis.html" is redirected
    to "info/index.html".
  - For "Index files", the directory is redirected,
    e.g., "info/" => "info/index.html".  
    Note: The other way around would not work well.  The following explains why.
    Suppose the main path is defined as "info/uk" (for info/uk/index.html).
    Note a trailing forward-slash must not be included in Drupal path.
    Then, a hyperlink anchored from info/uk (= info/uk/index.html)
    with a relative path, say, "./baa.html", is recognised
    by the users' browsers as "info/baa.html", as opposed to the correct
    "info/uk/baa.html". Therefore if a user tries to open the hyperlink,
    the browser sends a request of "info/baa.html", which will cause 404,
    namely, the dead link. It is the correct interpretation for the browser.
    If the original path was "info/uk/", it would work as expected,
    but it is not the case in Drupal.
  - If a redirection for the same "from" path is already set to a different
    destination, this migration overwrites it, unless there are more than one
    redirection is defined for it.
- i18n:
  - The primary page is always Japanese. Some may have English counterpart,
    but many don't. There's no English page that has no Japanese counterpart.
  - Therefore, the source language (for translation) is always Japanese.
  - If a Japanese page does not have an English counterpart,
    the language of the node is set to be neutral (LANGUAGE_NONE).
  - If it has an English counterpart, the language is set to be "ja",
    accordingly.  The same goes for English pages.
  - The language for the body is always set appropriately ("ja" or "en").
- Author of the node:
  - Administrator (uid = 1)
- Creation and modification time:
  - "changed" is taken from "mtime" of the file (in the UNIX disk-system).
    Note: Be careful if you preprocess files, as it may change their mtime.
  - "created" is taken from "ctime" of the file.
    If ctime is later than mtime, mtime is used.
- Title of the node:
  - The element of the <h1> tag, if there is any. <h1> tag is deleted.
  - If not, <title> tag is used, if the element is not empty.
  - And if not, the file path is used.
- Miscellaneous information:
  - field_original_title: The element of <title> tag.
  - field_original_html_filename: The original directory and filename
    (without the domain name or a preceding forward slash).
  - field_editors_note: Information for potential debugging.
- Taxonomy: japanese_site:
  - The top directory name.
- Meta-tags:
  - "description", "keywords", "original-source" are set.
- Link:
  - "field_link_alternative", together with customised title, is set.


Technical flow-chart
--------------------
- The base directory for the HTML files is defined as a constant,
  ALLBUTBOOK_TOPHTMLDIR in allbutbook.inc - please modify it.
- The migration is done in 2 steps (necessary to construct the i18n structure):
  - Japanese HTMLs (AllbutbookHtmlJaMigration) first, then
  - English ones (AllbutbookHtmlEnMigration).
- Use MigrateSourceList class to define the HTML files to import.
- In prepareRow() the path of each file (aka row) is passed.  With this:
  - get all the required information from the header,
  - get the <body> element,
  - also checks if the translation is available, based on the path name.
  - "tnid" is undefined in Japanese HTMLs at the time of processing of
    AllbutbookHtmlJaMigration
  - "tnid" for Japanese pages is set in prepare() while processing
    AllbutbookHtmlEnMigration, where the relation
    between translation and source nodes is set.
- The process to handle the HTML tags is coded in html_parser.inc
  with the use of QueryPath library.
- In complete(), the redirection of the legacy URIs is set.
- No roll-back is defined for redirections, whereas the existing redirections
  are checked, and are possibly overwritten (warning message will be
  issued if run from drush).


Known issues
------------
- When a file is a symbolic link to another file in the file list
  to be imported, both the files will be imported as separate files with
  the identical content.  Ideally the path that is a symbolic-link
  should be treated as a redirection to the original file.
  It is possible to implement, but can be tricky, if considering the case
  where the linked file is not included in the file list to be imported
  (it is maybe symlinked to the file out of the imported directory
  of file list, or possibly the symlinked file has been already imported,
  etc, etc).
- No roll-back is defined for redirection.


Disclaimer
----------
As I was learning a lot while developing this, there must be loads of
things that can be written or structured better.  For example, there
is no doubt the file structure can be a lot simplified (I have simply
taken the template from the standard migrate_example module, and modified them
for my use).  Please modify it as you like for whatever your case!


Acknowledgements 
----------------
Some parts of the codes were originally taken from:
- @link https://gist.github.com/marktheunissen/2596787 (by Mark Theunissen) @endlink
- @link https://gist.github.com/klaasvw/3904524 (by Klaas Van Waesberghe) @endlink
- @link http://www.group42.ca/creating_url_redirects_with_migrate_module (by Dale McGladdery) @endlink
Note they have been massively modified.
I am citing them to express my appreciation!


Authors
-------
Masa Sakano - http://www.drupal.org/user/3022767

