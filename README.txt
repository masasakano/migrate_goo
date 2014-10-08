
Migration of i18n static HTML pages to Drupal 7.

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
self-explanatory. You must understand how the (parent) Migrate module
works.

Nevertheless, given the situation the related documentation and
reference, particularly those about the migration of the static HTMLs
with the full i18n feature, are limited, this may give you some
starting and/or reference point.  The main code is in allbutbook.inc.php


Install
-------
Basically, follow the standard procedure.
Make sure the modules this module depends on are installed and enabled beforehand.

They are in short,
- path
- i18n (Internationalization, Field translation, Translation redirect)
- Taxonomy
- Querypath (Manual install is fine, as long as you adjust the library path accordingly)
- Redirect
- Metatag
- Link
- Context
- langnonecontext (https://github.com/masasakano/langnonecontext)

Place everything in this directory into your user module directory, usually:
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
- For the URL, set it as the directory, and not the domain.
- The weight (priority) for the URL must be the highest.
- For all the languages, including the default language, explicitly
  set the language code for the path, e.g., "en" for English.

If you change the user-ID (uid) from the default (=1), give the user the permission of
"Use the PHP code text format" in "Filter" section of /admin/people/permissions


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

That is why I switched over to the migrate module.  Anyway it provides
a much better flexibility, and indeed is essential to implement the i18n
feature.

Nevertheless the i18n feature was I found very tricky to deal with.
It is to some extent an inherent problem for the i18n, because
practically every multilingual site is different and so has different
requirements.  The fact I am, and I was even more so, a Drupal novice,
and was not familiar with Drupal, let alone its i18n feature, did not help.
Also, as I eventually found, the default Apache feature for the
language negotiation with the static HTMLs does not match well with
Drupal's i18n feature, hence I ended up developing a small module to
deal with it.  Unsurprisingly, as I realise now, I have experienced a
great difficulty for all these processes and it has taken a lot of time to complete.
So, here am I sharing my code in the hope this may help some one else.

The aim of this migration is summarised as:
- Preserve all the legacy URIs.
- Natural-language paths, as opposed to the node number, should be displayed.
- All the internal links should work.
- Reproduce the i18n (internatinalization) structure the original had,
  so the imported ones have a proper language code, as well as
  the Drupal language switcher incorporated.
- Make more modern-style URIs as default, while keeping the legacy ones.
- Preserve most Meta-tags and Link-tags information in the header.
- Introduce an taxonomy, based on the top directory name.
- The original <h1> tag is deleted, imported as the page title.

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

In fact, not all of them were guaranteed in the original HTMLs.  So,
I preprocessed all the files with a separate script, which filtered out
some header and footers, fixed ill-written HTML tags, converted
the character code, and ran "tidy" finally.


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
    Note: The other way would not work well.  The following explains why.
    Suppose the main path is defined as "info/uk" (for info/uk/index.html).
    Note a trailing forward-slash must not be included in Drupal path.
    Then, a link anchored from info/uk (= info/uk/index.html)
    with a relative path, say, "./baa.html", is recognised
    by the users' browsers as "info/baa.html", as opposed to the correct
    "info/uk/baa.html". Therefore if a user tries to open the linke,
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


Recap of i18n (internationalizatio) in Drupal
---------------------------------------------
First, the i18n feature of Drupal 7 is as follows:
- The language of a page in the website has 2 meanings (at least):
  - Language for the interface, like a menu bar,
  - Language of the main content and information directly related to it,
    such as, the title.
- The default language switcher changes both of the above, as long as
  the translation of the node is available.
- In Drupal, every node has a property of a single language,
  which can be Neutral.
- Optionally (by enabling it in the i18n configuration),
  each field in a node can have its own translation (I think...).
  But it is basically unrelated with the language of the node.
- The language of the node has nothing to do with the character set
  of the content.  It is possible (if confusing to any one) to set
  the language of the node as English, where the main content
  uses only Japanese characters, and vice versa.
- If the language of the node is set to be neutral, the page can be accessed
  and viewed in any language setting, where the language-switcher 
  merely means the setting of the language for the interface.
- If the language of the node is set to be a specific one, be it English or
  Japanese, the node is accessed and viewed only when the particular
  language environment is set.
- How to set, or to provide users with the way to set,
  the language environment depends how you configure the site.
  Users could manually switch the language like inputting a particular path,
  but the language switcher would provide the easiest way.
- If all the (default) options to detect the language with the default priority
  are set as described above, switching of language is normally done by adding
  the language-code path at the top directory, e.g., /en/info/index.html
- When setting the path, the path should not include the language code;
  e.g., Not /en/info/foo.html but /info/foo.html for Japanese page.
  Then when a user accesses /info/foo.html it will be automatically
  transferred to /ja/info/foo.html (exception applied as described below)
- The same path can be set for a page for different languages, such as,
  /info/index.html for both /en/info/foo.en.html and /ja/info/foo.ja.html 
  as long as they are registered as the translation to each other.
  Even completed different paths can be set for them, such as, 
  /info/End.html and /undo/Jab.html respectively for English and Japanese.
- When a user accesses a path in an language, if the page (path)
  has a translation for the user's chosen language, that translated page
  is always shown. For example, in the above example, if the user's
  chosen language is Japanese, when s/he accesses /info/index.html or
  even /info/End.html, the page that comes up is always /ja/info/index.html 
- Note the "user's chosen language" is determined in the priority as set
  in the i18n configuration, as described above.
- In default, English is the default language of Drupal, and 
  the language code for the path is undefined(!).  This default setting
  can lead to a confusing situation (it took a long time for me
  to figure it out...). Suppose there are Japanese and English pages
  /info/foo.ja.html and /info/foo.en.html respectively and their
  paths are set to be /info/index.html .  This means their respective paths,
  as you see in the address bar in your browser, are /ja/info/index.html
  and /info/index.html (the latter is Not /en/info/index.html in default!).
  Then a user opens /info/index.html it will be an English version,
  no matter what her/his preferred language in the browser setting is,
  because /info/index.html is the proper path for English version.
- In the above example, if a user opens the same path from an internal link from
  a Japanese page, that is a different story, because the embedded link
  in the HTML file, which was originally written as "/info/index.html",
  has been modified to be /ja/info/index.html when displayed.  In other words,
  in this case, there is no language-neutral path, as long as
  the site-preference for the language selection places the URI selection
  at the top priority, as in default.
- As explained above, if the language-code for the path for the default
  language is set, this confusing situation would be unlikely to happen. For example,
  /info/index.html does not belong to either Japanese or English,
  hence the method with the following priority to decide the language
  will be used: Session, User, Broser in this order in default.
- As a reference, in the Apache server envitonment, it is common (to set up)
  example.html.en and example.html.ja mean English and Japanese contents,
  respectively, and if a user accesses example.html, the Apache server
  decides which language-version it will bring up, depending on
  the environments of the user, site, etc.  And if s/he requests explicitly
  example.html.ja then it will always bring up the Japanese version.
- The path-based language system in Drupal i18n, however, does not work well
  with this suffix-naming-based language negotiation of Apache.
  Drupal can decide which language-version it would show when
  a neutral path/file (such as, example.html in the case above),
  just as the Apache server does.  However, when a specific language-version
  like example.html.ja is requested, most notably from the hard-coded link
  in a HTML file, Drupal will still decide what to show, thorougly
  depending on the other environments and it does not care
  about the requested URI (because the filename is a mere alias for the content
  that has multiple-launguage versions, as mentioned above).
  For that reason, the hard-coded links to the different-language version
  would not work, providing those nodes with different-languages
  are registered as translation to one another in Drupal.
- The fact Drupal automatically adds a language prefix to internal links
  hard-coded in the page content may surprise those uninitiated.
  Suppose a hard-coded link in a Japanese page originally points to an
  English-only page explicitly, say, /english_only.en.html, and
  suppose it does not have any translation.  Then, when the Japanese page
  is displayed the link becomes /ja/english_only.en.html already.
  There is no node of /ja/english_only.en.html (but /english_only.en.html
  and maybe /en/english_only.en.html if the "en" prefix is already configured),
  hence, 404 (Not found) error will be returned.   
- You can disable the language selection by Drupal based on the path
  (or domain) prefix entirely.  For example, Google.com seems to decide
  the language of the page, depending on the user's browser's preference
  and where geographycally the accessed IP is (the latter is not included
  in the default Drupal i18n functionality).  That is another way for sure.
- Note that showing different contents for the same URI, just depending on
  user's setting or session parameters, can be bad for SEO, namely,
  penalised in the rating by search engines, allegedly.
- There is a bug in Drupal i18n:
  - @link https://www.drupal.org/node/1294946 "Language detection based on session doesn't work with URL aliases" @endlink
  If you access a path with the session parameter,
  the URL aliases do not work as of October 2014.
  For example, if you access to /info/index.html?language=ja
  it will bring up a path like /node/12345 .
- The path feature can be disabled, or you choose not to set the path. 
  Still, any node can be always accessed via its node number like /node/123 .
- Each language in a pair of translated contents has its own node,
  that is, they do not share the node-id.
- There are three disadvantages for node-based path, particularly
  for the imported static HTMLs.  First, any internal link in the relative
  path hard-coded in the HTML would not work from that sort of node-type
  paths. The relative path is, for example, "./baa.html", but obviously
  there is no node with the path "/node/baa.html", hence those links break.
  Second, this type of meaningless paths are bad for SEO (Search-Engine
  Optimization).  Third, it is less portable, because potential migration
  to any (CMS) system, including another Drupal system, can be problematic.


Strategy of migration of static HTMLs in i18n
---------------------------------------------
- The grand picture is as follows:
  - The static HTMLs, most of which are in Japanese but some of which
    have a English translation, are imported to an existing site.
  - The default language of the existing site is English, though
    some of the existing contents are translated into Japanese.
  - None of the top directories of the import HTMLs crashes
    with the existing ones.
  - The front-page and menu for the Japanese contents are newly created
    (not imported).
  - The Japanese and English versions of the sites will be distinguished
    based on the path-prefix as the first priority in the i18n configuration.
  - English and Japanese contents on the site will be seamless,
    jumped to each other via a language-switcher in a menu,
    though a significant difference in contents between them will remain.
- The path aliases are enabled.  Hence the nominal path is not
  a /node/12345 type, but like /info/foobaa.html
- The Drupal path for any imported node is set to be the filename
  of the HTML minus any language-related suffix, but only .html
  For example, for the file /info/index.jp.jis.html the path is
  /info/index.html
- For index.html, to access via the directory is allowed; for example,
  /info/ is redirected to /info/index.html
- When there are both Japanese and English versions available
  for the same content, their pathes are set to be the same.
- The language of the node is in default neutral, except when the version
  in the other language is available, in which case the appropriate language
  is set for each node.
- The language of the main content (body) is always set appropriately,
  even when the language of the node is neutral.
- The language-neutral means any one, whatever their language
  setting/environment is, can view the page via the neutral path,
  e.g., without adding the language-code prefix.
- Conversely if there was only one language-version for a content,
  and if the language of the node was not neutral,
  when a visitor with the different language accesses the neutral path,
  s/he would get the 404 (Not found), which is not desirable in my case.
- The built-in language-switcher is disabled in default (in those nodes
  of the imported HTMLs).  This is set with the configuration of the block
  (the path section for the language-switcher block).
- However, for the nodes that have a version in the other language,
  the built-in language-switcher is enabled (in a side bar), so visitors
  can easily identify there is a translation and can switch the language
  if they want.  This is configured via the Context module combined
  with the added langnonecontext module.
- When they switch the language, the language for the interface changes
  at the same time.
- The most tricky thing is the hard-coded language-switcher in some HTMLs,
  that is, embedded anchors to the version of the other language,
  break down in migration.  I have solved this problem by replacing
  the anchor tag during migration with a small piece of PHP code
  to determine the link to the other language version on the fly.


Known issues
------------
- When a file is a sympolic link to another file in the file list
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
Note they have been massivly modified.  I am citing them to express my appreciation!


Authors
-------
Masa Sakano - http://www.drupal.org/user/3022767

